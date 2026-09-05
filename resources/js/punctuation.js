/**
 * The canonical punctuation convention, applied to a whole block of text.
 *
 * This is the third implementation of the definition in
 * `tests/Fixtures/punctuation.json`, next to `App\Support\CanonicalPunctuation`
 * (import) and the editor input rules in `wysiwyg.js` (typing). The input rules
 * cannot serve a paste: each one is anchored to the character that was just
 * typed, so it never sees a quote in the middle of a pasted paragraph.
 */

const EN_DASH = '–';
const EM_DASH = '—';
const ELLIPSIS = '…';

const OPEN_SINGLE = '‘';
const CLOSE_SINGLE = '’';
const OPEN_DOUBLE = '“';
const CLOSE_DOUBLE = '”';

/**
 * Characters that may stand before an opening quote, beyond whitespace and the
 * start of the text. Keep in step with CanonicalPunctuation::OPENERS.
 */
const OPENERS = ['(', '[', '{', '<', EN_DASH, EM_DASH, '-', '/'];

/** Longest first, so `---` never matches as `--` and a hyphen. */
const RUNS = /---|--|\.\.\./g;

const RUN_REPLACEMENTS = {
    '---': EM_DASH,
    '--': EN_DASH,
    '...': ELLIPSIS,
};

/**
 * A quote opens after the start of the text, whitespace or an opening bracket,
 * unless a digit follows it — a digit means an elision (`the '90s`), which is a
 * closing mark. Everything else closes, which also covers `don't`.
 */
function curlQuotes(text) {
    let result = '';

    for (let index = 0; index < text.length; index++) {
        const character = text[index];

        if (character !== "'" && character !== '"') {
            result += character;
            continue;
        }

        const before = index > 0 ? text[index - 1] : '';
        const after = index + 1 < text.length ? text[index + 1] : '';

        const opens =
            (before === '' || /\s/.test(before) || OPENERS.includes(before)) && !/\d/.test(after);

        if (character === "'") {
            result += opens ? OPEN_SINGLE : CLOSE_SINGLE;
        } else {
            result += opens ? OPEN_DOUBLE : CLOSE_DOUBLE;
        }
    }

    return result;
}

/**
 * Normalize one run of prose. Idempotent: the rules only ever consume ASCII, so
 * text that is already canonical comes back unchanged.
 */
export function normalizePunctuation(text) {
    return curlQuotes(text.replace(RUNS, (run) => RUN_REPLACEMENTS[run]));
}
