<?php

namespace App\Support;

use App\Services\Import\ProjectGraphImporter;

/**
 * Suggests a free name for a duplicated entity, e.g. "Arrival" -> "Arrival (2)".
 * The suggestion is advisory: the caller still validates and saves the name the
 * user submits, collision or not. See documentation/architecture/README.md -> Duplicating
 * entities.
 *
 * > [!NOTE]
 * > A source name that already ends in ` (<n>)` is re-suffixed from its base, not
 * > incremented in place. Duplicating a free "Arrival (2)" proposes "Arrival (2)"
 * > again only if that exact string reads back as taken; otherwise it still starts
 * > the search at "(2)" from the stripped base "Arrival". This keeps one rule
 * > instead of two ("strip" and "already free, keep as-is").
 */
class DuplicateName
{
    /**
     * Find the lowest-numbered `"<base> (n)"` not present in $taken, starting at 2.
     * Comparison is case-insensitive, mirroring
     * {@see ProjectGraphImporter::collisionFreeName()}.
     *
     * @param  iterable<string>  $taken
     */
    public static function suggest(string $name, iterable $taken): string
    {
        $base = self::stripSuffix($name);

        $takenFolded = [];
        foreach ($taken as $existing) {
            $takenFolded[mb_strtolower($existing)] = true;
        }

        $number = 2;
        do {
            $candidate = sprintf('%s (%d)', $base, $number);
            $number++;
        } while (isset($takenFolded[mb_strtolower($candidate)]));

        return $candidate;
    }

    /**
     * Strip a trailing ` (<n>)` so "Arrival (2)" and "Arrival" share one base.
     */
    private static function stripSuffix(string $name): string
    {
        return preg_replace('/ \(\d+\)$/', '', $name) ?? $name;
    }
}
