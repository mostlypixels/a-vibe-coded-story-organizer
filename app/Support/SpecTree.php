<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Locates feature folders in the `.specs/` tree (see .specs/README.md).
 *
 * A feature name must resolve to exactly one folder anywhere in the tree, and
 * two statuses hold their features flat while three bucket them by month. Every
 * caller that looks a feature up would otherwise repeat both globs and get one
 * of them wrong; tests/Unit/SpecsStatusConsistencyTest only catches the damage
 * afterwards.
 */
final class SpecTree
{
    /** Statuses whose features sit directly under the status folder, with no month bucket. */
    public const FLAT_STATUSES = ['draft', 'shelved'];

    public static function root(): string
    {
        return config('specs.path');
    }

    /**
     * Every folder in the tree that carries this feature name. More than one is
     * the collision SpecsStatusConsistencyTest fails on.
     *
     * @return list<string>
     */
    public static function locate(string $name): array
    {
        $root = self::root();

        $flat = array_values(array_filter(
            array_map(fn (string $status) => "$root/$status/$name", self::FLAT_STATUSES),
            File::isDirectory(...),
        ));

        return array_merge($flat, File::glob("$root/*/*/$name", GLOB_ONLYDIR) ?: []);
    }

    /** Whether the name is free for a new feature folder. */
    public static function isFree(string $name): bool
    {
        return self::locate($name) === [];
    }

    /** Kebab-case keeps folder names glob- and URL-safe, and matches every existing feature. */
    public static function isValidName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name);
    }
}
