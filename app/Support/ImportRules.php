<?php

namespace App\Support;

use App\Enums\CodexMediaCollection;
use App\Models\ImportSetting;

/** Defines fixed archive versions, paths, MIME types, and size tolerances. */
class ImportRules
{
    /** @var array<int, int> Manifest versions that match the current archive layout. */
    public const SUPPORTED_MANIFEST_VERSIONS = [4, 5];

    /** Default archive size in kilobytes. Runtime validation uses {@see ImportSetting}. */
    public const DEFAULT_MAX_ARCHIVE_KILOBYTES = 204800;

    /** Maximum entries in an archive. A long serial export stays far below this value. */
    public const MAX_ENTRY_COUNT = 50000;

    /**
     * An archive can expand to this multiple of the upload cap. Text compresses well
     * and media does not, so real exports stay below this multiple.
     */
    public const MAX_EXPANSION_FACTOR = 5;

    /** An export holds only text and uploaded files, so no entry is larger than the largest upload. */
    public const MAX_ENTRY_BYTES = CodexMediaRules::FILE_MAX_KILOBYTES * 1024;

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

    /** The cap follows the live upload cap, so an admin change moves both. */
    public static function maxUncompressedBytes(): int
    {
        return ImportSetting::current()->max_archive_kilobytes * 1024 * self::MAX_EXPANSION_FACTOR;
    }

    /** Imported media obey the same size limits as uploaded media. */
    public static function maxMediaBytes(CodexMediaCollection $collection): int
    {
        $kilobytes = $collection === CodexMediaCollection::ReferenceFile
            ? CodexMediaRules::FILE_MAX_KILOBYTES
            : CodexMediaRules::IMAGE_MAX_KILOBYTES;

        return $kilobytes * 1024;
    }

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
