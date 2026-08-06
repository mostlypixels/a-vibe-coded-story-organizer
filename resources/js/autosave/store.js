/**
 * Autosave client decision logic — a plain, side-effect-free module (no DOM,
 * no Alpine, no axios) that holds the autosave state machine.
 *
 * The Alpine adapter (`resources/js/autosave/field.js`) is the thin layer that
 * fires the requests, touches `localStorage`, and updates
 * `Alpine.store('autosave')`. Every *decision* — which state to be in, how long
 * to wait before a retry, what to do with a stray `localStorage` draft — lives
 * here, so vitest can exercise it with no browser.
 *
 * `scheduleRetry()` is the one function that is not a pure transform: it calls
 * `setTimeout`. It stays thin, so the adapter has one place to schedule a retry
 * and vitest's fake timers can drive it directly.
 */

/**
 * The full autosave indicator state enum. `architecture.md` and `ui.md` describe
 * every state except `forbidden-after-replay` — see `FORBIDDEN_AFTER_REPLAY`
 * below.
 */
export const STATES = Object.freeze({
    IDLE: 'idle',
    SAVING: 'saving',
    SAVED: 'saved',
    RETRYING: 'retrying',
    CONFLICT: 'conflict',
    SESSION_EXPIRED: 'session-expired',
    FORBIDDEN_AFTER_REPLAY: 'forbidden-after-replay',
    ERROR: 'error',
});

/**
 * Worst-first precedence for the global lower-right badge. A save that
 * "definitely did not land and needs a human decision" always outranks the
 * softer `error`/`retrying`/`saving` states, so `forbidden-after-replay` sits
 * directly after `conflict`.
 */
const PRECEDENCE = [
    STATES.SESSION_EXPIRED,
    STATES.CONFLICT,
    STATES.FORBIDDEN_AFTER_REPLAY,
    STATES.ERROR,
    STATES.RETRYING,
    STATES.SAVING,
    STATES.SAVED,
    STATES.IDLE,
];

/**
 * Given every per-field state currently in play on the page, return the one
 * global badge state — worst-state-wins. An empty list
 * (nothing autosaving yet) is `idle`; an unrecognized string is ignored
 * rather than crashing the badge, since a caller passing garbage should not
 * take down the one thing meant to report trouble.
 */
export function worstState(states) {
    if (!states || states.length === 0) {
        return STATES.IDLE;
    }

    let worst = STATES.IDLE;
    let worstRank = PRECEDENCE.length;

    for (const state of states) {
        const rank = PRECEDENCE.indexOf(state);

        if (rank !== -1 && rank < worstRank) {
            worstRank = rank;
            worst = state;
        }
    }

    return worst;
}

/**
 * Case-insensitive `Retry-After` lookup. Axios normalizes response header
 * keys to lowercase, but this accepts a plain object either way so tests
 * (and any future caller) don't have to guess the casing.
 */
function retryAfterMsFromHeaders(headers) {
    if (!headers) {
        return undefined;
    }

    const key = Object.keys(headers).find((name) => name.toLowerCase() === 'retry-after');

    if (!key) {
        return undefined;
    }

    const seconds = Number(headers[key]);

    return Number.isFinite(seconds) && seconds >= 0 ? seconds * 1000 : undefined;
}

/**
 * The HTTP status → indicator state mapping that `architecture.md`'s table
 * defines. Takes an explicit `wasReplay` flag rather than an inferred one, so
 * the adapter never has to guess whether a 403 followed a session-expired
 * recovery.
 *
 * A missing/`null`/`0` status (axios's shape for a network failure — no
 * response ever arrived) maps to `retrying`, same as 429, just without a
 * `Retry-After` to honor.
 *
 * Returns `{ state, retryAfterMs }` — `retryAfterMs` is only ever set for
 * `retrying`, and only when the server supplied `Retry-After`; absent it, the
 * caller falls back to `retryDelayMs()`'s own schedule.
 */
export function mapResponse(status, { headers = {}, wasReplay = false } = {}) {
    switch (status) {
        case 200:
            return { state: STATES.SAVED };

        case 401:
        case 419:
            // Indistinguishable from the writer's chair, so both collapse into
            // one state deliberately. Never `error`.
            return { state: STATES.SESSION_EXPIRED };

        case 403:
            // A first-attempt 403 "should not exist in practice" (the UI
            // never lets an unauthorized user open the field), but the
            // mapping stays explicit about the distinction anyway, so the
            // adapter never has to guess. Only a 403 on a *replayed* save (after a
            // session-expired sign-in-as-someone-else) becomes the dedicated
            // `forbidden-after-replay` state.
            return { state: wasReplay ? STATES.FORBIDDEN_AFTER_REPLAY : STATES.ERROR };

        case 409:
            return { state: STATES.CONFLICT };

        case 422:
            return { state: STATES.ERROR };

        case 429: {
            // 429 never becomes `error` — always `retrying`, and it honors
            // `Retry-After` when present.
            return { state: STATES.RETRYING, retryAfterMs: retryAfterMsFromHeaders(headers) };
        }

        default:
            // No status at all (network failure) — same soft `retrying`
            // treatment as a rate limit, just without a server-given delay.
            return { state: STATES.RETRYING };
    }
}

/** Base and ceiling for the exponential retry schedule, in milliseconds. */
const RETRY_BASE_DELAY_MS = 2_000;
const RETRY_MAX_DELAY_MS = 60_000;

/**
 * Deterministic retry backoff: doubling from `RETRY_BASE_DELAY_MS`, capped at
 * `RETRY_MAX_DELAY_MS`. `attempt` is 1-based (the first retry is attempt 1).
 * When the server supplied a `Retry-After` value (via `mapResponse`'s
 * `retryAfterMs`), that value always wins — the server's own rate-limit
 * window is authoritative over our guessed schedule.
 */
export function retryDelayMs(attempt, retryAfterMs) {
    if (typeof retryAfterMs === 'number' && retryAfterMs >= 0) {
        return retryAfterMs;
    }

    const exponential = RETRY_BASE_DELAY_MS * 2 ** Math.max(0, attempt - 1);

    return Math.min(exponential, RETRY_MAX_DELAY_MS);
}

/**
 * Thin `setTimeout` wrapper so the adapter has one place to schedule a retry
 * rather than reaching for the global directly. The only function in
 * this module with a side effect — kept to a single line so it stays
 * trivially testable with vitest's fake timers (no real waits in the suite).
 */
export function scheduleRetry(callback, delayMs) {
    return setTimeout(callback, delayMs);
}

/**
 * How long a `localStorage` draft stays eligible for recovery, in milliseconds — a
 * flat 4-hour duration from `savedAt`, not a calendar-day boundary (a draft written
 * at 11:58pm keeps its full ~4 hours, it does not reset at midnight).
 */
export const DRAFT_TTL_MS = 4 * 60 * 60 * 1000;

/**
 * Read-time pre-filter in front of `triageDraft()`: an expired draft is treated
 * identically to "no draft" and never reaches the three-way triage below. `now`
 * is injectable so tests do not depend on the real clock.
 */
export function isDraftExpired(draft, now = Date.now()) {
    return now - draft.savedAt > DRAFT_TTL_MS;
}

/**
 * The three-way `localStorage` draft-triage decision. `draft` is what was
 * mirrored while typing (`{ value, baseHash, savedAt }`); `server` is the
 * value/hash the page just loaded (`{ value, hash }` — the hash the server
 * rendered for the current stored value, never client-computed).
 *
 * > [!WARNING]
 * > Never return a bare "restore" when the base hash does not match the current
 * > server value. A stale draft from a different session must never silently
 * > offer to clobber newer server text; it gets `offer-compare-only`.
 */
export function triageDraft(draft, server) {
    if (draft.value === server.value) {
        // It landed (or was undone) — nothing to recover.
        return 'drop-silently';
    }

    if (draft.baseHash === server.hash) {
        // Genuinely unsaved work sitting on top of the value it was typed
        // against — safe to offer a straight restore.
        return 'offer-restore';
    }

    // The server has moved on since this draft was written; never offer to
    // silently overwrite newer data.
    return 'offer-compare-only';
}
