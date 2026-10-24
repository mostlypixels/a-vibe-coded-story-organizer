<?php

namespace App\Support;

use App\Models\Book;
use App\Models\Scene;

/**
 * The matches of one scene, with the hash of the contents they were found in.
 *
 * The apply step trusts the match indexes only while the hash still equals
 * {@see FieldHash::of()} of the stored contents.
 */
final readonly class FindReplaceSceneMatches
{
    /** @param list<FindReplaceMatch> $matches */
    public function __construct(
        public Scene $scene,
        public Book $book,
        public string $hash,
        public array $matches,
    ) {}
}
