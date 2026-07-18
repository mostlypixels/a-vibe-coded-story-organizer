<?php

namespace App\Services\Import;

use App\Enums\CodexMediaCollection;
use App\Exceptions\ImportValidationException;
use App\Support\ImportRules;
use finfo;
use ZipArchive;

/**
 * The import security gate: validates an uploaded export archive BEFORE anything
 * is extracted to the importer's working directory or written to the database
 * (invariant: nothing from an archive is persisted until it has passed here and
 * the ContentSanitizer, see .specs → import → architecture.md).
 *
 * Six checks, in order — the first failure throws {@see ImportValidationException}
 * with a user-safe message naming the offending file/rule:
 *
 *   1. The upload is a real zip (ZipArchive::open()'s return code, not the extension).
 *   2. No zip-slip: every entry name is inspected for "..", absolute, or drive-letter
 *      paths before anything is read. Nothing is EVER extracted to disk here —
 *      entries are only ever read into memory — so a hostile name can never place
 *      a file anywhere.
 *   3. Every entry sits inside the allow-listed arborescence ({@see ImportRules});
 *      book/ and README.md are allowed (real exports have them) but never read.
 *   4. data/manifest.json exists, parses, and its version is supported.
 *   5. Every JSON descriptor decodes and carries its required keys (the shapes in
 *      documentation/export-format.md).
 *   6. Every media file an entry.json declares exists (when the manifest says
 *      bytes are included), matches its declared size, and — crucially — its
 *      ACTUAL content (finfo/getimagesize on the bytes) matches its declared
 *      mime/collection. This is the check that stops a renamed executable.
 */
class ArchiveValidator
{
    /**
     * Required keys per descriptor basename, for descriptors that live at a
     * per-entity path (check 5). Shapes come from documentation/export-format.md;
     * keys may hold null (e.g. a scene's event_id) but must be PRESENT.
     *
     * @var array<string, array<int, string>>
     */
    private const DESCRIPTOR_REQUIRED_KEYS = [
        'act.json' => ['id', 'name', 'position', 'project_id'],
        'chapter.json' => ['id', 'name', 'position', 'act_id'],
        'scene.json' => ['id', 'name', 'position', 'status', 'chapter_id', 'event_id', 'mentioned_event_ids'],
        'plotline.json' => ['id', 'name', 'color', 'is_main', 'project_id'],
        'event.json' => ['id', 'title', 'event_datetime', 'is_fixed', 'project_id', 'plotline_ids'],
        'entry.json' => ['id', 'name', 'type', 'project_id', 'aliases', 'tag_ids', 'attribute_values', 'media'],
    ];

    /**
     * Required keys of data/manifest.json (check 4).
     *
     * @var array<int, string>
     */
    private const MANIFEST_REQUIRED_KEYS = ['version', 'project_id', 'exported_at', 'includes_media'];

    /**
     * Required keys of data/project/project.json — the one per-entity descriptor
     * whose PRESENCE is also required (an export without its root entity cannot
     * be imported at all).
     *
     * @var array<int, string>
     */
    private const PROJECT_REQUIRED_KEYS = ['id', 'name'];

    /**
     * Required keys of each item in the flat list descriptors (check 5).
     *
     * @var array<string, array<int, string>>
     */
    private const LIST_ITEM_REQUIRED_KEYS = [
        'data/codex/attributes.json' => ['id', 'name', 'applies_to', 'position'],
        'data/tags.json' => ['id', 'name'],
    ];

    /**
     * Required keys of each media[] item inside an entry.json (check 6).
     *
     * @var array<int, string>
     */
    private const MEDIA_ITEM_REQUIRED_KEYS = ['id', 'collection', 'position', 'original_name', 'mime_type', 'size', 'file'];

    /**
     * Validate the archive at the given path, throwing on the first violation.
     *
     * @throws ImportValidationException
     */
    public function validate(string $archivePath): void
    {
        $zip = new ZipArchive;

        // Check 1 — a real zip, judged by libzip, not by the file extension.
        if ($zip->open($archivePath) !== true) {
            throw ImportValidationException::notAZip();
        }

        try {
            $entries = $this->safeEntryNames($zip);          // checks 2 + 3
            $manifest = $this->validateManifest($zip);       // check 4
            $entryDescriptors = $this->validateDescriptors($zip, $entries); // check 5
            $this->validateMedia($zip, $entryDescriptors, (bool) $manifest['includes_media']); // check 6
            $this->validateChapterCovers($zip, $entries, (bool) $manifest['includes_media']); // check 6 (chapter covers)
        } finally {
            $zip->close();
        }
    }

    /**
     * Checks 2 + 3: walk every entry name in the central directory and reject
     * anything unsafe (zip-slip) or outside the allow-listed arborescence.
     * Nothing is extracted — names are inspected as strings only.
     *
     * @return array<int, string> every non-directory entry name
     */
    private function safeEntryNames(ZipArchive $zip): array
    {
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if ($name === false || $this->isUnsafePath($name)) {
                throw ImportValidationException::unsafeEntryPath((string) $name);
            }

            if (! ImportRules::isAllowedPath($name)) {
                throw ImportValidationException::disallowedEntryPath($name);
            }

            // Pure directory entries carry no content; keep only real files.
            if (! str_ends_with($name, '/')) {
                $entries[] = $name;
            }
        }

        return $entries;
    }

    /**
     * The zip-slip test, applied to archive entry names AND to the relative
     * media paths an entry.json declares: reject "..", ".", empty segments,
     * backslashes (our exporter only ever writes "/"), absolute paths, drive
     * letters, and NUL bytes. String inspection only — never resolved on disk.
     */
    private function isUnsafePath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            return true;
        }

        // Absolute ("/etc/passwd") or drive-letter ("C:...") paths.
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return true;
        }

        // A trailing slash marks a directory entry; trim it before splitting so
        // it doesn't read as an empty segment.
        foreach (explode('/', rtrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check 4: data/manifest.json exists, parses, has its required keys, and its
     * `version` is one this importer can read.
     *
     * @return array<string, mixed> the decoded manifest
     */
    private function validateManifest(ZipArchive $zip): array
    {
        if ($zip->locateName('data/manifest.json') === false) {
            throw ImportValidationException::missingManifest();
        }

        $manifest = $this->decodeJson($zip, 'data/manifest.json');
        $this->requireKeys('data/manifest.json', $manifest, self::MANIFEST_REQUIRED_KEYS);

        // Strict comparison: the exporter writes an integer version; a string
        // "1" is not a value any real export produces.
        if (! in_array($manifest['version'], ImportRules::SUPPORTED_MANIFEST_VERSIONS, true)) {
            throw ImportValidationException::unsupportedManifestVersion($manifest['version']);
        }

        return $manifest;
    }

    /**
     * Check 5: every JSON descriptor in the archive decodes and has its required
     * keys. The decoded entry.json descriptors are returned (keyed by their
     * archive path) so check 6 doesn't decode them a second time.
     *
     * @param  array<int, string>  $entries
     * @return array<string, array<string, mixed>> decoded entry.json data by path
     */
    private function validateDescriptors(ZipArchive $zip, array $entries): array
    {
        // The root entity descriptor must exist — without it there is no project
        // to import (its absence would otherwise surface as a confusing failure
        // deep inside the graph importer).
        if ($zip->locateName('data/project/project.json') === false) {
            throw ImportValidationException::missingDescriptor('data/project/project.json');
        }

        $entryDescriptors = [];

        foreach ($entries as $path) {
            if ($path === 'data/project/project.json') {
                $descriptor = $this->decodeJson($zip, $path);
                $this->requireKeys($path, $descriptor, self::PROJECT_REQUIRED_KEYS);

                continue;
            }

            // The two flat list descriptors: a JSON array whose every item has
            // the required keys.
            if (isset(self::LIST_ITEM_REQUIRED_KEYS[$path])) {
                $list = $this->decodeJson($zip, $path);
                if (! array_is_list($list)) {
                    throw ImportValidationException::malformedDescriptor($path);
                }
                foreach ($list as $item) {
                    $this->requireKeys($path, is_array($item) ? $item : [], self::LIST_ITEM_REQUIRED_KEYS[$path]);
                }

                continue;
            }

            // Per-entity descriptors, matched by basename inside the (already
            // allow-listed) data/ branches.
            $basename = basename($path);
            if (str_starts_with($path, 'data/') && isset(self::DESCRIPTOR_REQUIRED_KEYS[$basename])) {
                $descriptor = $this->decodeJson($zip, $path);
                $this->requireKeys($path, $descriptor, self::DESCRIPTOR_REQUIRED_KEYS[$basename]);

                if ($basename === 'entry.json') {
                    $entryDescriptors[$path] = $descriptor;
                }
            }
        }

        return $entryDescriptors;
    }

    /**
     * Check 6: every media[] item an entry.json declares is well-formed, its file
     * exists in the zip (required only when the manifest says bytes are included —
     * a metadata-only export legitimately ships none), its actual size matches the
     * declared size, and its ACTUAL content matches its declared mime/collection.
     *
     * @param  array<string, array<string, mixed>>  $entryDescriptors
     */
    private function validateMedia(ZipArchive $zip, array $entryDescriptors, bool $includesMedia): void
    {
        foreach ($entryDescriptors as $descriptorPath => $descriptor) {
            if (! is_array($descriptor['media']) || ! array_is_list($descriptor['media'])) {
                throw ImportValidationException::invalidDescriptorValue($descriptorPath, 'media');
            }

            // The entry's directory inside the archive; media `file` paths are
            // relative to it (export-format.md → Media).
            $entryDirectory = dirname($descriptorPath);

            foreach ($descriptor['media'] as $media) {
                $this->requireKeys($descriptorPath, is_array($media) ? $media : [], self::MEDIA_ITEM_REQUIRED_KEYS);

                $collection = is_string($media['collection']) ? CodexMediaCollection::tryFrom($media['collection']) : null;
                if ($collection === null) {
                    throw ImportValidationException::invalidDescriptorValue($descriptorPath, 'media.collection');
                }

                // The declared relative path is attacker-controlled JSON — give it
                // the same zip-slip treatment as a real entry name before joining.
                $file = $media['file'];
                if (! is_string($file) || $this->isUnsafePath($file) || str_ends_with($file, '/')) {
                    throw ImportValidationException::unsafeEntryPath(is_string($file) ? $file : '');
                }

                $this->validateMediaFile($zip, $descriptorPath, "{$entryDirectory}/{$file}", $media, $collection, $includesMedia);
            }
        }
    }

    /**
     * Size + content-sniff validation for one declared media file.
     *
     * @param  array<string, mixed>  $media  the media[] item from entry.json
     */
    private function validateMediaFile(
        ZipArchive $zip,
        string $descriptorPath,
        string $archivePath,
        array $media,
        CodexMediaCollection $collection,
        bool $includesMedia,
    ): void {
        $stat = $zip->statName($archivePath);

        if ($stat === false) {
            // Metadata-only exports (the "Include images & files" toggle off)
            // declare every media row but ship no bytes — that is valid. An
            // archive that CLAIMS to include media but is missing a file is not.
            if ($includesMedia) {
                throw ImportValidationException::missingMediaFile($descriptorPath, $archivePath);
            }

            return;
        }

        // Declared size vs. the file's actual (uncompressed) size in the zip.
        if (abs($stat['size'] - (int) $media['size']) > ImportRules::MEDIA_SIZE_TOLERANCE_BYTES) {
            throw ImportValidationException::mediaSizeMismatch($archivePath);
        }

        // Read the bytes into memory (never extracted to disk) and re-derive the
        // type from CONTENT — the declared mime_type/extension is not trusted.
        $bytes = $zip->getFromName($archivePath);
        if ($bytes === false) {
            throw ImportValidationException::missingMediaFile($descriptorPath, $archivePath);
        }

        $declaredMime = is_string($media['mime_type']) ? $media['mime_type'] : '';
        $sniffedMime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';

        match ($collection) {
            CodexMediaCollection::Cover,
            CodexMediaCollection::ReferenceImage => $this->validateImageContent($descriptorPath, $archivePath, $bytes, $declaredMime, $sniffedMime),
            CodexMediaCollection::ReferenceFile => $this->validateReferenceFileContent($descriptorPath, $archivePath, $declaredMime, $sniffedMime),
        };
    }

    /**
     * Check 6 (chapter covers): every chapter.json that links a `cover_file`
     * (task 07, epub-configuration) points at a genuine image inside its own
     * directory. The cover is a plain path field — unlike codex media it carries
     * no declared mime — so it is validated on CONTENT alone: the bytes must sniff
     * (finfo AND getimagesize, agreeing) as one of the allowed image types. A
     * forged image (e.g. a renamed .php) is rejected.
     *
     * The file's presence is required only when the manifest says bytes are
     * included, mirroring codex media: a metadata-only export legitimately ships
     * the `cover_file` link but no bytes.
     *
     * @param  array<int, string>  $entries
     */
    private function validateChapterCovers(ZipArchive $zip, array $entries, bool $includesMedia): void
    {
        foreach ($entries as $path) {
            if (basename($path) !== 'chapter.json' || ! str_starts_with($path, 'data/')) {
                continue;
            }

            $descriptor = $this->decodeJson($zip, $path);

            if (! array_key_exists('cover_file', $descriptor)) {
                continue; // a chapter without a cover
            }

            // The declared relative path is attacker-controlled JSON — give it the
            // same zip-slip treatment as a real entry name before joining.
            $coverFile = $descriptor['cover_file'];
            if (! is_string($coverFile) || $this->isUnsafePath($coverFile) || str_ends_with($coverFile, '/')) {
                throw ImportValidationException::unsafeEntryPath(is_string($coverFile) ? $coverFile : '');
            }

            $archivePath = dirname($path).'/'.$coverFile;
            $bytes = $zip->getFromName($archivePath);

            if ($bytes === false) {
                // No bytes: valid only for a metadata-only export.
                if ($includesMedia) {
                    throw ImportValidationException::missingMediaFile($path, $archivePath);
                }

                continue;
            }

            $this->validateChapterCoverContent($path, $archivePath, $bytes);
        }
    }

    /**
     * Content-sniff one chapter cover's bytes: finfo and getimagesize must agree on
     * an allowed image mime. A renamed non-image fails both sniffers and is rejected.
     */
    private function validateChapterCoverContent(string $descriptorPath, string $archivePath, string $bytes): void
    {
        $sniffedMime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        $imageInfo = getimagesizefromstring($bytes);

        if (! in_array($sniffedMime, ImportRules::IMAGE_MIME_TYPES, true)
            || $imageInfo === false
            || ($imageInfo['mime'] ?? null) !== $sniffedMime) {
            throw ImportValidationException::mediaContentMismatch($archivePath);
        }
    }

    /**
     * A cover / reference image must declare an allowed image mime AND actually
     * BE that image: finfo and getimagesize (a second, image-specific sniffer)
     * must both agree with the declaration. A renamed .php fails here — its
     * bytes sniff as text/x-php and never parse as an image.
     */
    private function validateImageContent(string $descriptorPath, string $archivePath, string $bytes, string $declaredMime, string $sniffedMime): void
    {
        if (! in_array($declaredMime, ImportRules::IMAGE_MIME_TYPES, true)) {
            throw ImportValidationException::invalidDescriptorValue($descriptorPath, 'media.mime_type');
        }

        $imageInfo = getimagesizefromstring($bytes);

        if ($sniffedMime !== $declaredMime || $imageInfo === false || ($imageInfo['mime'] ?? null) !== $declaredMime) {
            throw ImportValidationException::mediaContentMismatch($archivePath);
        }
    }

    /**
     * A reference file must declare an allowed document mime and its actual
     * content must sniff as one of the allowed document types. Set membership —
     * not strict declared === sniffed equality — because libmagic's spelling for
     * office documents varies across versions, while the security property only
     * needs "the bytes are genuinely one of the allowed document types".
     */
    private function validateReferenceFileContent(string $descriptorPath, string $archivePath, string $declaredMime, string $sniffedMime): void
    {
        if (! in_array($declaredMime, ImportRules::REFERENCE_FILE_MIME_TYPES, true)) {
            throw ImportValidationException::invalidDescriptorValue($descriptorPath, 'media.mime_type');
        }

        if (! in_array($sniffedMime, ImportRules::REFERENCE_FILE_MIME_TYPES, true)) {
            throw ImportValidationException::mediaContentMismatch($archivePath);
        }
    }

    /**
     * Decode a JSON entry, rejecting unreadable or non-array/object content.
     *
     * @return array<mixed>
     */
    private function decodeJson(ZipArchive $zip, string $path): array
    {
        $raw = $zip->getFromName($path);
        $decoded = $raw === false ? null : json_decode($raw, true);

        if (! is_array($decoded)) {
            throw ImportValidationException::malformedDescriptor($path);
        }

        return $decoded;
    }

    /**
     * Assert every required key is PRESENT (values may be null — e.g. a scene's
     * nullable event_id), naming the descriptor and the first missing key.
     *
     * @param  array<mixed>  $data
     * @param  array<int, string>  $keys
     */
    private function requireKeys(string $path, array $data, array $keys): void
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                throw ImportValidationException::missingDescriptorKey($path, $key);
            }
        }
    }
}
