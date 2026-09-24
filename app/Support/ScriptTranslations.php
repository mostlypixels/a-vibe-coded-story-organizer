<?php

namespace App\Support;

/**
 * Translated text for JavaScript components. Blade passes it into the component config.
 *
 * Each key is the English source string, the same key that `__()` uses. The script
 * looks it up with `translate()` in `resources/js/translate.js`.
 *
 * > [!WARNING]
 * > A key that the script uses must be in a list here. Else the writer sees English.
 * > The Vitest suite compares the lists with the keys in the script.
 */
final class ScriptTranslations
{
    /** Labels of `resources/js/autosave/badge.js`. */
    private const AUTOSAVE_BADGE = [
        'Saving…',
        'Saved',
        'Reconnecting…',
        'Save conflict — needs your attention',
        'Session expired — your work is safe.',
        "You're signed in as a different account — copy your text before switching back.",
        "Couldn't save — check your connection.",
    ];

    /** Messages and slash menu titles of `resources/js/wysiwyg.js`. */
    private const WYSIWYG = [
        'Use a web address that starts with http:// or https://.',
        'No matches',
        'Text',
        'Heading 1',
        'Heading 2',
        'Heading 3',
        'Heading 4',
        'Bold',
        'Italic',
        'Underline',
        'Strikethrough',
        'Subscript',
        'Superscript',
        'Bulleted list',
        'Numbered list',
        'Blockquote',
        'Inline code',
        'Code block',
        'Link',
        'Horizontal rule',
        'Table',
        'Image',
        'Task list',
        'Callout',
        'New codex entry',
        'Align center',
        'Align right',
        'Align justify',
        'Colour red',
        'Colour green',
        'Colour amber',
        'Colour blue',
        'Colour grey',
    ];

    /** @return array<string, string> */
    public static function autosaveBadge(): array
    {
        return self::translate(self::AUTOSAVE_BADGE);
    }

    /** @return array<string, string> */
    public static function wysiwyg(): array
    {
        return self::translate(self::WYSIWYG);
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private static function translate(array $keys): array
    {
        return array_combine($keys, array_map(fn (string $key) => __($key), $keys));
    }
}
