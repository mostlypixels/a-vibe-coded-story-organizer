<?php

namespace App\Support;

/**
 * One match in a scene, with plain-text context for the preview.
 *
 * `index` is the place of the match in the scene's match list. The apply step
 * receives the index, never the offset, so a client cannot cut a word in two.
 */
final readonly class FindReplaceMatch
{
    public function __construct(
        public int $index,
        public int $offset,
        public string $before,
        public string $found,
        public string $after,
    ) {}
}
