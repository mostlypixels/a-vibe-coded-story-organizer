<?php

namespace App\Support;

use App\Services\ProjectRevisionsBrowser;
use Illuminate\Support\Collection;

/** One entity type, for example "Scenes", in the {@see ProjectRevisionsBrowser} sidebar. */
final readonly class RevisionTreeGroup
{
    /**
     * @param  string  $type  The registry slug, for example `scene`.
     * @param  list<string>  $filterNames
     * @param  Collection<int, RevisionTreeBook>  $books
     */
    public function __construct(
        public string $type,
        public string $label,
        public int $entityCount,
        public array $filterNames,
        public Collection $books,
    ) {}
}
