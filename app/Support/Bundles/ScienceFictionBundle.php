<?php

namespace App\Support\Bundles;

use App\Enums\CodexEntryType;

/**
 * Thin science-fiction-genre placeholder content. A later pass fills in real copy.
 */
final class ScienceFictionBundle implements GenreBundle
{
    public function attributes(): array
    {
        return [
            new BundleAttribute('Technology Level', [CodexEntryType::Location, CodexEntryType::Organization]),
        ];
    }

    public function tags(): array
    {
        return ['Tech'];
    }

    public function sampleEntries(): array
    {
        return [
            new BundleSampleEntry(
                type: CodexEntryType::Organization,
                name: 'The Collective',
                description: 'An organization whose reach outpaces its stated purpose.',
                tags: ['Tech'],
                attributeValues: ['Technology Level' => 'Unspecified'],
            ),
        ];
    }

    public function skeleton(): array
    {
        return [
            new BundleAct('Act One', ['Opening']),
        ];
    }
}
