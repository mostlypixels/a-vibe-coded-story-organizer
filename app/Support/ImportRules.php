<?php

namespace App\Support;

use App\Models\ImportSetting;
use App\Services\Import\ArchiveValidator;

/**
 * Single source of truth for the fixed (non-admin-configurable) import rules.
 *
 * Mirrors {@see CodexMediaRules}'s role: the manifest version gate, the archive
 * arborescence allow-list, and the content-sniffing mime allow-lists live here so
 * they are never magic strings scattered across {@see ArchiveValidator}
 * and its tests. The one admin-tunable import limit — the archive size cap — is
 * deliberately NOT a constant here; it lives on the {@see ImportSetting}
 * singleton, and this class only holds its migration/seed DEFAULT.
 */
class ImportRules
{
    /**
     * The data/manifest.json `version` values this importer knows how to read.
     *
     * Version 4 moved the manuscript under `data/books/<id>/acts/` and gave each
     * book its own `publication-setting.json`. A relocated path is a breaking
     * layout change, so every older version is rejected rather than imported
     * against the wrong shape. Extending this list is the one-line opt-in for a
     * future breaking change (see documentation/export-format.md → "The version
     * contract").
     *
     * @var array<int, int>
     */
    public const SUPPORTED_MANIFEST_VERSIONS = [4];

    /**
     * Default archive size cap in kilobytes (200 MB).
     *
     * Used only as the seed/default for the admin-configurable
     * ImportSetting.max_archive_kilobytes (config/import.php reads it for the
     * lazy-create path). Validation never reads this constant — it reads
     * ImportSetting::current() so an admin change takes effect immediately.
     */
    public const DEFAULT_MAX_ARCHIVE_KILOBYTES = 204800;

    /**
     * Exact file paths allowed at fixed locations in the archive.
     *
     * README.md is part of a real export but is never read by the importer
     * (data/ is the only source of truth).
     *
     * @var array<int, string>
     */
    public const ALLOWED_FILES = [
        'data/manifest.json',
        'data/tags.json',
        // The project's writing history — a flat list like data/tags.json.
        // Absent in every archive exported before this feature; the importer
        // treats a missing file as "no history".
        'data/word-count-snapshots.json',
        'README.md',
    ];

    /**
     * Directory prefixes under which any entry is allowed.
     *
     * The manuscript lives under data/books/, one directory per book, and each
     * book carries its own publication-setting.json — so no flat descriptor for
     * it sits at the data/ root.
     *
     * books/ is part of a real export (the human reading layer) but, like
     * README.md, is allowed-but-ignored — the importer never reads it.
     *
     * @var array<int, string>
     */
    public const ALLOWED_DIRECTORIES = [
        'data/project/',
        'data/books/',
        'data/timeline/',
        'data/codex/',
        'books/',
    ];

    /**
     * Mime types a cover / reference-image file may ACTUALLY be, by content.
     *
     * The content-sniffed counterpart of {@see CodexMediaRules::IMAGE_EXTENSIONS}
     * (uploads are gated by extension; imported archive bytes are gated by what
     * finfo says they really are). Keep the two lists in step.
     *
     * @var array<int, string>
     */
    public const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Mime types a reference file may ACTUALLY be, by content.
     *
     * The content-sniffed counterpart of {@see CodexMediaRules::FILE_EXTENSIONS}
     * (pdf, txt, md, doc, docx). Markdown sniffs as text/plain; both spellings
     * are listed because the declared mime_type recorded at upload can be either.
     *
     * @var array<int, string>
     */
    public const REFERENCE_FILE_MIME_TYPES = [
        'application/pdf',
        'text/plain',
        'text/markdown',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * How far a media file's actual byte size may differ from the size its
     * entry.json declared before the archive is rejected (defense against a
     * descriptor that lies about what it ships).
     */
    public const MEDIA_SIZE_TOLERANCE_BYTES = 1024;

    /**
     * Whether a (already slip-checked) archive entry path is inside the
     * allow-listed arborescence.
     *
     * A trailing-slash directory entry is allowed both under an allowed prefix
     * (e.g. "data/books/1-book-one/") and as an ancestor of one (e.g. "data/" —
     * some zip tools emit parent directory entries explicitly).
     */
    public static function isAllowedPath(string $path): bool
    {
        // No supported export can produce a revisions/ sidecar, so a zip
        // carrying one is malformed by definition — reject it before the
        // allow-list below would otherwise let it through under data/books/,
        // data/timeline/, etc.
        // A path *segment* match, not a substring one: an entry legitimately
        // slugged "...-revisions" must still be allowed.
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

            // "data/" is not itself an allowed prefix, but it is the parent of
            // several — accept pure directory entries on the way down to one.
            if (str_ends_with($path, '/') && str_starts_with($directory, $path)) {
                return true;
            }
        }

        return false;
    }
}
