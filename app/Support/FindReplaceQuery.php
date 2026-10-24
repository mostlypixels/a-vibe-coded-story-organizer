<?php

namespace App\Support;

/**
 * What the author wants to find and what to put in its place.
 *
 * The find text is literal: it is never a pattern. Both texts keep their
 * whitespace, because a replace of double spaces is a valid job.
 */
final readonly class FindReplaceQuery
{
    public const MAX_FIND_LENGTH = 500;

    public const MAX_REPLACE_LENGTH = 5000;

    public function __construct(
        public string $find,
        public string $replace = '',
        public bool $matchCase = true,
        public bool $wholeWord = true,
    ) {}

    /** True when there is nothing to look for, so no query may run. */
    public function isBlank(): bool
    {
        return $this->find === '';
    }

    /**
     * The form values that rebuild this query, for links and redirects.
     *
     * @return array<string, string|int>
     */
    public function toQuery(): array
    {
        return [
            'find' => $this->find,
            'replace' => $this->replace,
            'match_case' => (int) $this->matchCase,
            'whole_word' => (int) $this->wholeWord,
        ];
    }
}
