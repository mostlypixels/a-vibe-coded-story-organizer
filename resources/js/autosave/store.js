/** Autosave states that the field and global badge share. */
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

/** Show states that need user action before transient states. */
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

/** Accept normalized Axios headers and plain header objects. */
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

/** Map an HTTP result to a client state and optional retry delay. */
export function mapResponse(status, { headers = {}, wasReplay = false } = {}) {
    switch (status) {
        case 200:
            return { state: STATES.SAVED };

        case 401:
        case 419:
            return { state: STATES.SESSION_EXPIRED };

        case 403:
            // A replay can run under a different signed-in user.
            return { state: wasReplay ? STATES.FORBIDDEN_AFTER_REPLAY : STATES.ERROR };

        case 409:
            return { state: STATES.CONFLICT };

        case 422:
            return { state: STATES.ERROR };

        case 429: {
            return { state: STATES.RETRYING, retryAfterMs: retryAfterMsFromHeaders(headers) };
        }

        default:
            // A missing status means that no response arrived.
            return { state: STATES.RETRYING };
    }
}

const RETRY_BASE_DELAY_MS = 2_000;
const RETRY_MAX_DELAY_MS = 60_000;

/** Use Retry-After when present. Otherwise, use capped exponential backoff. */
export function retryDelayMs(attempt, retryAfterMs) {
    if (typeof retryAfterMs === 'number' && retryAfterMs >= 0) {
        return retryAfterMs;
    }

    const exponential = RETRY_BASE_DELAY_MS * 2 ** Math.max(0, attempt - 1);

    return Math.min(exponential, RETRY_MAX_DELAY_MS);
}

export function scheduleRetry(callback, delayMs) {
    return setTimeout(callback, delayMs);
}
