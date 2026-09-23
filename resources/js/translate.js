/**
 * Looks up the text that Laravel translated for a script.
 *
 * The key is the English source string, the same key `__()` uses. Blade passes
 * `App\Support\ScriptTranslations` into the component config. A missing key shows
 * the English text, the same fallback as `__()`.
 */
export function translate(strings, key) {
    return strings?.[key] ?? key;
}
