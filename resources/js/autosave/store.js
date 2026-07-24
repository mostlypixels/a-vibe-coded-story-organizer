/**
 * Autosave client decision logic — a plain, side-effect-free module (no DOM,
 * no Alpine, no axios) implementing the state machine `handoff.md` §3.4/§9.12
 * calls for. Task 8's Alpine adapter (`resources/js/autosave/field.js`) is the
 * thin layer that actually fires requests, touches `localStorage`, and
 * updates `Alpine.store('autosave')`; every *decision* — which state to be
 * in, how long to wait before retrying, what to do with a stray `localStorage`
 * draft — lives here so it can be exercised by vitest with no browser
 * (`.specs/planned/2026-07/autosave-with-revisions/handoff.md` §9.12).
 *
 * `scheduleRetry()` is the one function that isn't a pure transform (it calls
 * `setTimeout`), kept deliberately thin — a one-line wrapper task 8's adapter
 * can call without duplicating `setTimeout` all over the codebase, and small
 * enough that vitest's fake timers exercise it directly.
 */

/**
 * The full autosave indicator state enum (task 07's scope; `handoff.md` §3.4,
 * §9.5, §9.6). `forbidden-after-replay` is this task's own addition to the
 * set `architecture.md`/`ui.md` already describe — see `FORBIDDEN_AFTER_REPLAY`
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
 * Worst-first precedence for the global lower-right badge (`handoff.md` §9.5,
 * refined by this task's own file to insert `forbidden-after-replay` directly
 * after `conflict`): a save that "definitely did not land and needs a human
 * decision" always outranks the softer `error`/`retrying`/`saving` states.
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
 * global badge state (`handoff.md` §9.5 — "worst-state-wins"). An empty list
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
 * The HTTP status → indicator state mapping `architecture.md`'s table and
 * `handoff.md` §9.6/§9.8 define. Takes an explicit `wasReplay` flag (per this
 * task's scope) rather than inferring it, so task 8's adapter never has to
 * guess whether a 403 followed a session-expired recovery.
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
            // Indistinguishable from the writer's chair (handoff.md §9.6) —
            // collapsed into one state deliberately, never `error`.
            return { state: STATES.SESSION_EXPIRED };

        case 403:
            // A first-attempt 403 "should not exist in practice" (the UI
            // never lets an unauthorized user open the field), but the
            // mapping stays explicit about the distinction anyway so task 8
            // never has to guess. Only a 403 on a *replayed* save (after a
            // session-expired sign-in-as-someone-else) becomes the dedicated
            // `forbidden-after-replay` state.
            return { state: wasReplay ? STATES.FORBIDDEN_AFTER_REPLAY : STATES.ERROR };

        case 409:
            return { state: STATES.CONFLICT };

        case 422:
            return { state: STATES.ERROR };

        case 429: {
            // 429 never becomes `error` (handoff.md §9.8) — always `retrying`,
            // honoring `Retry-After` when present.
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
 * Thin `setTimeout` wrapper so task 8's adapter has one place to schedule a
 * retry rather than reaching for the global directly. The only function in
 * this module with a side effect — kept to a single line so it stays
 * trivially testable with vitest's fake timers (no real waits in the suite).
 */
export function scheduleRetry(callback, delayMs) {
    return setTimeout(callback, delayMs);
}

/**
 * The three-way `localStorage` draft-triage decision, `handoff.md` §9.7's
 * table. `draft` is what was mirrored while typing (`{ value, baseHash,
 * savedAt }`); `server` is the value/hash the page just loaded
 * (`{ value, hash }`, per §9.13 — the hash the server rendered for the
 * current stored value, never client-computed).
 *
 * Deliberately never returns a bare "restore" when the base hash doesn't
 * match the current server value (§9.7's closing rule) — a stale draft from
 * a different session must never silently offer to clobber newer server
 * text, only `offer-compare-only`.
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
