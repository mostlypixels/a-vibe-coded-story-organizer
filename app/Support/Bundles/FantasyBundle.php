<?php

namespace App\Support\Bundles;

use App\Enums\CodexEntryType;

/**
 * Thin fantasy-genre placeholder content. A later pass fills in real copy.
 */
final class FantasyBundle implements GenreBundle
{
    public function attributes(): array
    {
        return [
            new BundleAttribute('Magic System', [CodexEntryType::Character, CodexEntryType::Organization]),
        ];
    }

    public function tags(): array
    {
        return ['Magic'];
    }

    public function sampleEntries(): array
    {
        return [
            new BundleSampleEntry(
                type: CodexEntryType::Character,
                name: 'The Wanderer',
                description: 'A stranger who arrives carrying more than they say.',
                tags: ['Magic'],
                attributeValues: ['Magic System' => 'Unspecified'],
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
