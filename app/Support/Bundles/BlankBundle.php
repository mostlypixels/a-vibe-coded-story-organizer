<?php

namespace App\Support\Bundles;

/**
 * Empty bundle for Genre::Blank. Skip reuses the same create-and-seed path
 * with this bundle, so it must return empty lists everywhere.
 */
final class BlankBundle implements GenreBundle
{
    public function attributes(): array
    {
        return [];
    }

    public function tags(): array
    {
        return [];
    }

    public function sampleEntries(): array
    {
        return [];
    }

    public function skeleton(): array
    {
        return [];
    }
}
