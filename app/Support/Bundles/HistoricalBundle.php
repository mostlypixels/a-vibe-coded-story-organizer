<?php

namespace App\Support\Bundles;

use App\Enums\CodexEntryType;

/**
 * Thin historical-genre placeholder content. A later pass fills in real copy.
 */
final class HistoricalBundle implements GenreBundle
{
    public function attributes(): array
    {
        return [
            new BundleAttribute('Era', [CodexEntryType::Location]),
        ];
    }

    public function tags(): array
    {
        return ['Period Detail'];
    }

    public function sampleEntries(): array
    {
        return [
            new BundleSampleEntry(
                type: CodexEntryType::Location,
                name: 'The Old Quarter',
                description: 'A neighborhood that carries the weight of the period.',
                tags: ['Period Detail'],
                attributeValues: ['Era' => 'Unspecified'],
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
