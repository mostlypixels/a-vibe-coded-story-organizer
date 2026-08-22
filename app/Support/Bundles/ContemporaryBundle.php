<?php

namespace App\Support\Bundles;

use App\Enums\CodexEntryType;

/**
 * Thin contemporary-genre placeholder content. A later pass fills in real copy.
 */
final class ContemporaryBundle implements GenreBundle
{
    public function attributes(): array
    {
        return [
            new BundleAttribute('Occupation', [CodexEntryType::Character]),
        ];
    }

    public function tags(): array
    {
        return ['Protagonist'];
    }

    public function sampleEntries(): array
    {
        return [
            new BundleSampleEntry(
                type: CodexEntryType::Character,
                name: 'Jordan Lee',
                description: 'The protagonist, introduced in the opening chapter.',
                tags: ['Protagonist'],
                attributeValues: ['Occupation' => 'Journalist'],
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
