<?php

namespace App\Support\Bundles;

use App\Enums\Genre;

/**
 * Resolves a Genre to its bundle. The genre seed action is the only caller —
 * it applies whatever the bundle returns onto a freshly created project.
 */
final class Bundles
{
    public static function for(Genre $genre): GenreBundle
    {
        return match ($genre) {
            Genre::Contemporary => new ContemporaryBundle,
            Genre::Historical => new HistoricalBundle,
            Genre::Fantasy => new FantasyBundle,
            Genre::ScienceFiction => new ScienceFictionBundle,
            Genre::Blank => new BlankBundle,
        };
    }
}
