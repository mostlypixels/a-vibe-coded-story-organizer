<?php

namespace App\Support\Bundles;

use App\Enums\CodexEntryType;

/**
 * One codex attribute a bundle wants on the project, e.g. "Occupation" on
 * character sheets.
 */
final readonly class BundleAttribute
{
    /**
     * @param  array<int, CodexEntryType>  $appliesTo
     */
    public function __construct(
        public string $name,
        public array $appliesTo,
    ) {}
}
