<?php

namespace App\Support;

use App\Services\ProjectRevisionsBrowser;

/** One field with history, a leaf of the {@see ProjectRevisionsBrowser} sidebar. */
final readonly class RevisionTreeField
{
    public function __construct(
        public string $field,
        public string $label,
        public int $count,
        public string $url,
        public string $entity,
    ) {}
}
