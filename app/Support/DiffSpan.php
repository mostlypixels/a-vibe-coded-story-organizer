<?php

namespace App\Support;

use App\Enums\DiffChange;

/**
 * A run of consecutive words inside a changed block that share one fate: kept,
 * inserted, or removed.
 *
 * Runs rather than single words, because that is what the reader sees — "three
 * words were replaced" is one visual marker, not three.
 */
final readonly class DiffSpan
{
    /**
     * @param  list<InlineToken>  $tokens
     */
    public function __construct(
        public DiffChange $change,
        public array $tokens,
    ) {}

    /**
     * The words of this run, joined by single spaces.
     */
    public function text(): string
    {
        return implode(' ', array_map(fn (InlineToken $token): string => $token->word, $this->tokens));
    }
}
