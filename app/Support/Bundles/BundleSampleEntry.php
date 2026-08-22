<?php

namespace App\Support\Bundles;

use App\Enums\CodexEntryType;

/**
 * One codex entry a bundle seeds as a starting example, with its attribute
 * values set at the project's Start event.
 */
final readonly class BundleSampleEntry
{
    /**
     * @param  array<int, string>  $tags
     * @param  array<string, string>  $attributeValues  attribute name => Start value
     */
    public function __construct(
        public CodexEntryType $type,
        public string $name,
        public string $description,
        public array $tags = [],
        public array $attributeValues = [],
    ) {}
}
