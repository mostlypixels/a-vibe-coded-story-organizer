<?php

namespace App\Enums;

/**
 * How the search query terms are combined when matching entities.
 *
 * Backed string enum so the value round-trips through the `mode` query-string
 * parameter (see the search form). Mirrors the `SceneStatus`/`CodexEntryType`
 * pattern: a `label()` method supplies the human-readable text for the form's
 * mode control.
 */
enum SearchMode: string
{
    /** Every term must appear somewhere across the entity's searchable fields (AND). */
    case AllTerms = 'all';

    /** Any single term matching is enough (OR). */
    case AnyTerm = 'any';

    /** The whole query string must match verbatim in a single field. */
    case ExactPhrase = 'exact';

    /**
     * Human-readable label for the search-mode control (mirrors SceneStatus::label()).
     */
    public function label(): string
    {
        return match ($this) {
            self::AllTerms => 'Match all words',
            self::AnyTerm => 'Match any word',
            self::ExactPhrase => 'Exact phrase',
        };
    }
}
