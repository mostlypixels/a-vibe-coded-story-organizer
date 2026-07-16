<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * A single entity-level search match.
 *
 * A matching entity produces ONE row listing every field it matched — a Scene
 * matching in both `contents` and `notes` yields one row whose $fieldLabels is
 * ['Contents', 'Notes']. The view uses `$entity` to build an edit URL,
 * `matchedFields()` for the "Matched in" column, and `$snippet` for the
 * highlighted text preview (built from the FIRST matching field, in the
 * entity's declared field order — the matched-fields column tells the reader
 * where else the terms appeared).
 *
 * `$snippet` is the pre-escaped, highlighted HTML produced by SearchSnippet — it
 * is the single value in the search feature intended for `{!! !!}` output.
 */
class SearchResultRow
{
    /**
     * @param  Model  $entity  the matched entity (Act, Scene, CodexEntry, …)
     * @param  array<int, string>  $fieldLabels  human-readable labels of every field that matched, in field order (e.g. ['Name', 'Contents'])
     * @param  string  $snippet  pre-escaped, highlighted HTML from SearchSnippet (first matching field)
     */
    public function __construct(
        public readonly Model $entity,
        public readonly array $fieldLabels,
        public readonly string $snippet,
    ) {}

    /**
     * The matched field labels joined for display, e.g. "Name, Contents".
     */
    public function matchedFields(): string
    {
        return implode(', ', $this->fieldLabels);
    }
}
