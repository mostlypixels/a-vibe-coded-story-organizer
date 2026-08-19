import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { STATES, mapResponse, retryDelayMs, scheduleRetry, worstState } from './store.js';

describe('mapResponse — status-code mapping table', () => {
    it('200 maps to saved', () => {
        expect(mapResponse(200)).toEqual({ state: STATES.SAVED });
    });

    it('401 maps to session-expired', () => {
        expect(mapResponse(401)).toEqual({ state: STATES.SESSION_EXPIRED });
    });

    it('419 maps to session-expired, indistinguishable from 401', () => {
        expect(mapResponse(419)).toEqual({ state: STATES.SESSION_EXPIRED });
    });

    it('409 maps to conflict', () => {
        expect(mapResponse(409)).toEqual({ state: STATES.CONFLICT });
    });

    it('422 maps to error', () => {
        expect(mapResponse(422)).toEqual({ state: STATES.ERROR });
    });

    it('429 maps to retrying, honoring a Retry-After header', () => {
        expect(mapResponse(429, { headers: { 'Retry-After': '5' } })).toEqual({
            state: STATES.RETRYING,
            retryAfterMs: 5_000,
        });
    });

    it('429 maps to retrying with no retryAfterMs when the header is absent', () => {
        expect(mapResponse(429, { headers: {} })).toEqual({ state: STATES.RETRYING, retryAfterMs: undefined });
    });

    it('429 header lookup is case-insensitive (axios lowercases response headers)', () => {
        expect(mapResponse(429, { headers: { 'retry-after': '2' } })).toEqual({
            state: STATES.RETRYING,
            retryAfterMs: 2_000,
        });
    });

    it('a network failure (no status) maps to retrying, never error', () => {
        expect(mapResponse(undefined)).toEqual({ state: STATES.RETRYING });
        expect(mapResponse(null)).toEqual({ state: STATES.RETRYING });
        expect(mapResponse(0)).toEqual({ state: STATES.RETRYING });
    });
});

describe('mapResponse — 403 forbidden-after-replay distinction', () => {
    it('a bare first-attempt 403 (no replay) maps to the plain error state', () => {
        expect(mapResponse(403)).toEqual({ state: STATES.ERROR });
        expect(mapResponse(403, { wasReplay: false })).toEqual({ state: STATES.ERROR });
    });

    it('a 403 on a replayed save maps to the dedicated forbidden-after-replay state', () => {
        expect(mapResponse(403, { wasReplay: true })).toEqual({ state: STATES.FORBIDDEN_AFTER_REPLAY });
    });
});

describe('worstState — global badge precedence', () => {
    it('is idle with nothing in play', () => {
        expect(worstState([])).toBe(STATES.IDLE);
        expect(worstState(undefined)).toBe(STATES.IDLE);
    });

    it.each([
        [[STATES.IDLE, STATES.SAVED], STATES.SAVED],
        [[STATES.SAVED, STATES.SAVING], STATES.SAVING],
        [[STATES.SAVING, STATES.RETRYING], STATES.RETRYING],
        [[STATES.RETRYING, STATES.ERROR], STATES.ERROR],
        [[STATES.ERROR, STATES.FORBIDDEN_AFTER_REPLAY], STATES.FORBIDDEN_AFTER_REPLAY],
        [[STATES.FORBIDDEN_AFTER_REPLAY, STATES.CONFLICT], STATES.CONFLICT],
        [[STATES.CONFLICT, STATES.SESSION_EXPIRED], STATES.SESSION_EXPIRED],
        // Full house, in scrambled order — the worst one always wins regardless of position.
        [
            [STATES.SAVED, STATES.IDLE, STATES.ERROR, STATES.RETRYING, STATES.SAVING, STATES.FORBIDDEN_AFTER_REPLAY],
            STATES.FORBIDDEN_AFTER_REPLAY,
        ],
        [[STATES.SAVING, STATES.SAVED, STATES.SESSION_EXPIRED, STATES.CONFLICT], STATES.SESSION_EXPIRED],
    ])('given %j the badge state is %s', (states, expected) => {
        expect(worstState(states)).toBe(expected);
    });

    it('ignores unrecognized values rather than crashing the badge', () => {
        expect(worstState(['not-a-real-state', STATES.SAVING])).toBe(STATES.SAVING);
    });
});

describe('retryDelayMs — deterministic exponential backoff', () => {
    it('doubles from a 2s base for successive attempts', () => {
        expect(retryDelayMs(1)).toBe(2_000);
        expect(retryDelayMs(2)).toBe(4_000);
        expect(retryDelayMs(3)).toBe(8_000);
        expect(retryDelayMs(4)).toBe(16_000);
    });

    it('caps at 60 seconds no matter how many attempts', () => {
        expect(retryDelayMs(10)).toBe(60_000);
        expect(retryDelayMs(100)).toBe(60_000);
    });

    it('never goes below the base delay for attempt 0 or negative attempts', () => {
        expect(retryDelayMs(0)).toBe(2_000);
        expect(retryDelayMs(-5)).toBe(2_000);
    });

    it('a server-supplied Retry-After always wins over the computed schedule', () => {
        expect(retryDelayMs(1, 30_000)).toBe(30_000);
        expect(retryDelayMs(10, 1_000)).toBe(1_000);
    });
});

describe('scheduleRetry — fake-timer-driven, no real waits', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('does not fire before the delay elapses', () => {
        const callback = vi.fn();

        scheduleRetry(callback, 4_000);

        vi.advanceTimersByTime(3_999);

        expect(callback).not.toHaveBeenCalled();
    });

    it('fires exactly once the delay elapses', () => {
        const callback = vi.fn();

        scheduleRetry(callback, 4_000);

        vi.advanceTimersByTime(4_000);

        expect(callback).toHaveBeenCalledTimes(1);
    });
});
