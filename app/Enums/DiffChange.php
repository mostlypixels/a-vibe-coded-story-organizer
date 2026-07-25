<?php

namespace App\Enums;

/**
 * What happened to one piece of content between two revisions.
 *
 * Used at both levels of the visual diff: a whole block (paragraph, list item,
 * …) and a run of words inside a changed block. Word runs only ever use
 * `Unchanged`, `Inserted` and `Removed` — the other two describe a block as a
 * whole.
 *
 * String-backed because these values reach a Blade view and a stored summary,
 * where a stable name is worth more than an integer nobody can read.
 */
enum DiffChange: string
{
    case Unchanged = 'unchanged';

    case Inserted = 'inserted';

    case Removed = 'removed';

    /**
     * The block exists on both sides and its words changed — the only status
     * that carries inline spans.
     */
    case Replaced = 'replaced';

    /**
     * Same words, different formatting: a mark was added or removed without the
     * text changing. Reported as its own status rather than as a replacement,
     * because a word-level diff of two identical word streams would show
     * nothing at all and read as "nothing changed" — even though the writer
     * did save something.
     */
    case FormattingChanged = 'formatting-changed';

    /**
     * Whether this status means the content differs between the two revisions.
     */
    public function isChange(): bool
    {
        return $this !== self::Unchanged;
    }
}
