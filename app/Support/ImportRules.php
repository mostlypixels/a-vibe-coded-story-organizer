<?php

namespace App\Support;

use App\Models\ImportSetting;

/** Defines fixed archive versions, paths, MIME types, and size tolerances. */
class ImportRules
{
    /** @var array<int, int> Manifest versions that match the current archive layout. */
    public const SUPPORTED_MANIFEST_VERSIONS = [4, 5];

    /** Default archive size in kilobytes. Runtime validation uses {@see ImportSetting}. */
    public const DEFAULT_MAX_ARCHIVE_KILOBYTES = 204800;

    /** @var array<int, string> Exact paths allowed in an archive. */
    public const ALLOWED_FILES = [
        'data/manifest.json',
        'data/tags.json',
        'data/word-count-snapshots.json',
        'data/challenges.json',
        'README.md',
    ];

    /** @var array<int, string> Allowed directory prefixes. */
    public const ALLOWED_DIRECTORIES = [
        'data/project/',
        'data/books/',
        'data/timeline/',
        'data/codex/',
        'books/',
    ];

    /** @var array<int, string> Content-sniffed image MIME types. */
    public const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /** @var array<int, string> Content-sniffed reference-file MIME types. */
    public const REFERENCE_FILE_MIME_TYPES = [
        'application/pdf',
        'text/plain',
        'text/markdown',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** Maximum difference between declared and actual media size. */
    public const MEDIA_SIZE_TOLERANCE_BYTES = 1024;

    /** Allows known paths and their explicit parent-directory entries. */
    public static function isAllowedPath(string $path): bool
    {
        // No supported archive contains a revisions path segment.
        if (preg_match('#(^|/)revisions/#', $path) === 1) {
            return false;
        }

        if (in_array($path, self::ALLOWED_FILES, true)) {
            return true;
        }

        foreach (self::ALLOWED_DIRECTORIES as $directory) {
            if (str_starts_with($path, $directory)) {
                return true;
            }

            // Some ZIP tools include parent directory entries.
            if (str_ends_with($path, '/') && str_starts_with($directory, $path)) {
                return true;
            }
        }

        return false;
    }
}
