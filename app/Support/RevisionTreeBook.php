<?php

namespace App\Support;

use App\Services\ProjectRevisionsBrowser;
use Illuminate\Support\Collection;

/**
 * The entities of one book in the {@see ProjectRevisionsBrowser} sidebar.
 *
 * Project-wide entities sit in one bucket with a null id and name.
 */
final readonly class RevisionTreeBook
{
    /**
     * @param  list<string>  $filterNames
     * @param  Collection<int, RevisionTreeEntity>  $entities
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public array $filterNames,
        public Collection $entities,
    ) {}
}
