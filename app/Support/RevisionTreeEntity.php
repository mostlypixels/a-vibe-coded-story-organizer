<?php

namespace App\Support;

use App\Services\ProjectRevisionsBrowser;
use Illuminate\Support\Collection;

/** One entity with history in the {@see ProjectRevisionsBrowser} sidebar. */
final readonly class RevisionTreeEntity
{
    /**
     * @param  string  $filterName  The lowercase name that the sidebar filter compares.
     * @param  Collection<int, RevisionTreeField>  $fields
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $filterName,
        public string $url,
        public Collection $fields,
    ) {}
}
