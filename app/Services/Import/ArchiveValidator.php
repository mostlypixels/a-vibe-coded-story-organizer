<?php

namespace App\Services\Import;

use App\Enums\CodexMediaCollection;
use App\Exceptions\ImportValidationException;
use App\Http\Requests\StoreActRequest;
use App\Http\Requests\StoreChallengeRequest;
use App\Http\Requests\StoreChapterRequest;
use App\Http\Requests\StoreCodexAttributeRequest;
use App\Http\Requests\StoreCodexEntryRequest;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\StorePlotlineRequest;
use App\Http\Requests\StoreSceneRequest;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Support\ImportRules;
use finfo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

/**
 * Validates an archive before extraction or database writes.
 *
 * It checks ZIP structure, safe paths, allowed locations, the manifest version,
 * descriptor shapes, media sizes, and content-sniffed MIME types. Declared JSON
 * paths receive the same traversal checks as ZIP entry names.
 *
 * Descriptor values must obey the rules of the form that writes them. A bad
 * value rejects the archive, so no phase fails halfway and no page breaks later.
 */
class ArchiveValidator
{
    /** @var array<string, array<int, string>> Required keys can contain null. */
    private const DESCRIPTOR_REQUIRED_KEYS = [
        'book.json' => ['id', 'name', 'position', 'project_id'],
        'act.json' => ['id', 'name', 'position', 'book_id'],
        'chapter.json' => ['id', 'name', 'position', 'act_id'],
        'scene.json' => ['id', 'name', 'position', 'status', 'chapter_id', 'event_id', 'mentioned_event_ids'],
        'plotline.json' => ['id', 'name', 'color', 'is_main', 'project_id'],
        'event.json' => ['id', 'title', 'event_datetime', 'is_fixed', 'project_id', 'plotline_ids'],
        'entry.json' => ['id', 'name', 'type', 'project_id', 'aliases', 'tag_ids', 'attribute_values', 'media'],
    ];

    /** @var array<int, string> */
    private const MANIFEST_REQUIRED_KEYS = ['version', 'project_id', 'exported_at', 'includes_media'];

    /** @var array<int, string> */
    private const PROJECT_REQUIRED_KEYS = ['id', 'name'];

    /** @var array<string, array<int, string>> */
    private const LIST_ITEM_REQUIRED_KEYS = [
        'data/codex/attributes.json' => ['id', 'name', 'applies_to', 'position'],
        'data/tags.json' => ['id', 'name'],
        'data/word-count-snapshots.json' => ['recorded_on', 'word_count'],
        'data/challenges.json' => ['name', 'recurrence', 'starts_on', 'ends_on', 'target_words'],
    ];

    /** @var array<int, string> */
    private const MEDIA_ITEM_REQUIRED_KEYS = ['id', 'collection', 'position', 'original_name', 'mime_type', 'size', 'file'];

    /** @var array<string, array<int, string>> The app writes snapshots itself, so no form holds these rules. */
    private const SNAPSHOT_RULES = [
        'recorded_on' => ['required', 'date'],
        'word_count' => ['required', 'integer', 'min:0'],
    ];

    /** @var array<int, string> Descriptors that can link a content-sniffed cover. */
    private const COVER_BEARING_DESCRIPTORS = ['project.json', 'book.json', 'chapter.json'];

    /**
     * Validate the archive at the given path, throwing on the first violation.
     *
     * @throws ImportValidationException
     */
    public function validate(string $archivePath): void
    {
        $zip = new ZipArchive;

        // Libzip, not the extension, determines whether the upload is a ZIP.
        if ($zip->open($archivePath) !== true) {
            throw ImportValidationException::notAZip();
        }

        try {
            $entries = $this->safeEntryNames($zip);          // checks 2 + 3 + size caps
            $this->verifyRecordedSizes($zip);                // size caps hold for the real bytes
            $manifest = $this->validateManifest($zip);       // check 4
            $entryDescriptors = $this->validateDescriptors($zip, $entries); // check 5
            $this->validateMedia($zip, $entryDescriptors, (bool) $manifest['includes_media']); // check 6
            $this->validateCovers($zip, $entries, (bool) $manifest['includes_media']); // check 6 (plain cover columns)
        } finally {
            $zip->close();
        }
    }

    /**
     * Checks the recorded sizes before any read, so a small upload cannot fill the disk or memory.
     *
     * @return array<int, string> Safe, allowed, non-directory entry names.
     */
    private function safeEntryNames(ZipArchive $zip): array
    {
        if ($zip->numFiles > ImportRules::MAX_ENTRY_COUNT) {
            throw ImportValidationException::tooManyEntries(ImportRules::MAX_ENTRY_COUNT);
        }

        $maxTotalBytes = ImportRules::maxUncompressedBytes();
        $totalBytes = 0;
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = $stat === false ? false : $stat['name'];

            if ($name === false || $this->isUnsafePath($name)) {
                throw ImportValidationException::unsafeEntryPath((string) $name);
            }

            if (! ImportRules::isAllowedPath($name)) {
                throw ImportValidationException::disallowedEntryPath($name);
            }

            if ($stat['size'] > ImportRules::MAX_ENTRY_BYTES) {
                throw ImportValidationException::entryTooLarge($name, ImportRules::MAX_ENTRY_BYTES);
            }

            $totalBytes += $stat['size'];
            if ($totalBytes > $maxTotalBytes) {
                throw ImportValidationException::archiveTooLarge($maxTotalBytes);
            }

            if (! str_ends_with($name, '/')) {
                $entries[] = $name;
            }
        }

        return $entries;
    }

    /**
     * Libzip extracts all the real bytes, also when the recorded size is smaller.
     * Count the real bytes, and stop one byte after the recorded size.
     */
    private function verifyRecordedSizes(ZipArchive $zip): void
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $stream = $zip->getStreamIndex($index);

            if ($stat === false || $stream === false) {
                throw ImportValidationException::notAZip();
            }

            $bytesRead = 0;
            try {
                while ($bytesRead <= $stat['size'] && ! feof($stream)) {
                    $chunk = fread($stream, 65536);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    $bytesRead += strlen($chunk);
                }
            } finally {
                fclose($stream);
            }

            if ($bytesRead !== $stat['size']) {
                throw ImportValidationException::entrySizeMismatch($stat['name']);
            }
        }
    }

    /** Rejects traversal, absolute paths, drive paths, backslashes, and null bytes. */
    private function isUnsafePath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            return true;
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return true;
        }

        foreach (explode('/', rtrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> A valid supported manifest. */
    private function validateManifest(ZipArchive $zip): array
    {
        if ($zip->locateName('data/manifest.json') === false) {
            throw ImportValidationException::missingManifest();
        }

        $manifest = $this->decodeJson($zip, 'data/manifest.json');
        $this->requireKeys('data/manifest.json', $manifest, self::MANIFEST_REQUIRED_KEYS);

        // Real exports store the version as an integer.
        if (! in_array($manifest['version'], ImportRules::SUPPORTED_MANIFEST_VERSIONS, true)) {
            throw ImportValidationException::unsupportedManifestVersion($manifest['version']);
        }

        return $manifest;
    }

    /**
     * @param  array<int, string>  $entries
     * @return array<string, array<string, mixed>> Entry descriptors by archive path.
     */
    private function validateDescriptors(ZipArchive $zip, array $entries): array
    {
        // An archive without its project descriptor has no root entity.
        if ($zip->locateName('data/project/project.json') === false) {
            throw ImportValidationException::missingDescriptor('data/project/project.json');
        }

        $entryDescriptors = [];
        $eventDescriptors = [];

        foreach ($entries as $path) {
            if ($path === 'data/project/project.json') {
                $descriptor = $this->decodeJson($zip, $path);
                $this->requireKeys($path, $descriptor, self::PROJECT_REQUIRED_KEYS);
                $this->validateFormRules($path, $descriptor);
                $this->validateLinkedFieldFiles($descriptor);

                continue;
            }

            if (isset(self::LIST_ITEM_REQUIRED_KEYS[$path])) {
                $list = $this->decodeJson($zip, $path);
                if (! array_is_list($list)) {
                    throw ImportValidationException::malformedDescriptor($path);
                }
                foreach ($list as $item) {
                    $this->requireKeys($path, is_array($item) ? $item : [], self::LIST_ITEM_REQUIRED_KEYS[$path]);
                    $this->validateFormRules($path, $item);
                }

                continue;
            }

            $basename = basename($path);
            if (str_starts_with($path, 'data/') && isset(self::DESCRIPTOR_REQUIRED_KEYS[$basename])) {
                $descriptor = $this->decodeJson($zip, $path);
                $this->requireKeys($path, $descriptor, self::DESCRIPTOR_REQUIRED_KEYS[$basename]);
                $this->validateFormRules($path, $descriptor);
                $this->validateLinkedFieldFiles($descriptor);

                if ($basename === 'entry.json') {
                    $entryDescriptors[$path] = $descriptor;
                }

                if ($basename === 'event.json') {
                    $eventDescriptors[$path] = $descriptor;
                }
            }
        }

        $this->validateEventWindow($eventDescriptors);

        return $entryDescriptors;
    }

    /**
     * Applies the form rules that need no route model.
     *
     * @param  array<mixed>  $data
     */
    private function validateFormRules(string $path, array $data): void
    {
        $validator = Validator::make($data, $this->formRules($path));

        if ($validator->fails()) {
            throw ImportValidationException::invalidDescriptorValue($path, (string) array_key_first($validator->errors()->messages()));
        }
    }

    /** @return array<string, mixed> */
    private function formRules(string $path): array
    {
        return match ($path) {
            'data/project/project.json' => UpdateProjectRequest::fieldRules(),
            'data/tags.json' => StoreTagRequest::fieldRules(),
            'data/codex/attributes.json' => StoreCodexAttributeRequest::fieldRules(),
            'data/challenges.json' => StoreChallengeRequest::fieldRules(),
            'data/word-count-snapshots.json' => self::SNAPSHOT_RULES,
            default => match (basename($path)) {
                'book.json' => UpdateBookRequest::fieldRules(),
                'act.json' => StoreActRequest::fieldRules(),
                'chapter.json' => StoreChapterRequest::fieldRules(),
                'scene.json' => StoreSceneRequest::fieldRules(),
                'plotline.json' => StorePlotlineRequest::fieldRules(),
                'event.json' => StoreEventRequest::fieldRules(),
                'entry.json' => StoreCodexEntryRequest::fieldRules(),
                default => [],
            },
        };
    }

    /**
     * Applies the WithinEventWindow limit to each regular event. That rule reads
     * the bookends from the database, so here they come from the archive.
     *
     * @param  array<string, array<string, mixed>>  $events  Event descriptors by archive path.
     */
    private function validateEventWindow(array $events): void
    {
        $bookends = array_map(
            fn (array $event): Carbon => Carbon::parse((string) $event['event_datetime']),
            array_filter($events, fn (array $event): bool => (bool) $event['is_fixed']),
        );

        // The importer reports a wrong bookend count.
        if (count($bookends) !== 2) {
            return;
        }

        $start = min($bookends);
        $end = max($bookends);

        foreach ($events as $path => $event) {
            if ((bool) $event['is_fixed']) {
                continue;
            }

            $moment = Carbon::parse((string) $event['event_datetime']);
            if ($moment->lt($start) || $moment->gt($end)) {
                throw ImportValidationException::invalidDescriptorValue($path, 'event_datetime');
            }
        }
    }

    /**
     * The importer reads each `*_file` key as a path next to the descriptor.
     * Treat these declared JSON paths as untrusted archive entry names.
     *
     * @param  array<mixed>  $descriptor
     */
    private function validateLinkedFieldFiles(array $descriptor): void
    {
        foreach ($descriptor as $key => $file) {
            if (! is_string($key) || ! str_ends_with($key, '_file') || $file === null) {
                continue;
            }

            if (! is_string($file) || $this->isUnsafePath($file) || str_ends_with($file, '/')) {
                throw ImportValidationException::unsafeEntryPath(is_string($file) ? $file : '');
            }
        }
    }

    /** @param array<string, array<string, mixed>> $entryDescriptors */
    private function validateMedia(ZipArchive $zip, array $entryDescriptors, bool $includesMedia): void
    {
        foreach ($entryDescriptors as $descriptorPath => $descriptor) {
            if (! is_array($descriptor['media']) || ! array_is_list($descriptor['media'])) {
                throw ImportValidationException::invalidDescriptorValue($descriptorPath, 'media');
            }

            $entryDirectory = dirname($descriptorPath);

            foreach ($descriptor['media'] as $media) {
                $this->requireKeys($descriptorPath, is_array($media) ? $media : [], self::MEDIA_ITEM_REQUIRED_KEYS);

                $collection = is_string($media['collection']) ? CodexMediaCollection::tryFrom($media['collection']) : null;
                if ($collection === null) {
                    throw ImportValidationException::invalidDescriptorValue($descriptorPath, 'media.collection');
                }

                // A null file is a row without bytes. The source file was missing at export.
                $file = $media['file'];
                if ($file === null) {
                    continue;
                }

                // Treat declared JSON paths as untrusted archive entry names.
                if (! is_string($file) || $this->isUnsafePath($file) || str_ends_with($file, '/')) {
                    throw ImportValidationException::unsafeEntryPath(is_string($file) ? $file : '');
                }

                $this->validateMediaFile($zip, $descriptorPath, "{$entryDirectory}/{$file}", $media, $collection, $includesMedia);
            }
        }
    }

    /** @param array<string, mixed> $media */
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
            // Metadata-only archives can declare media without bytes.
            if ($includesMedia) {
                throw ImportValidationException::missingMediaFile($descriptorPath, $archivePath);
            }

            return;
        }

        $maxBytes = ImportRules::maxMediaBytes($collection);
        if ($stat['size'] > $maxBytes) {
            throw ImportValidationException::entryTooLarge($archivePath, $maxBytes);
        }

        if (abs($stat['size'] - (int) $media['size']) > ImportRules::MEDIA_SIZE_TOLERANCE_BYTES) {
            throw ImportValidationException::mediaSizeMismatch($archivePath);
        }

        // Sniff bytes in memory. Do not trust the declared MIME type or extension.
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

    /** @param array<int, string> $entries */
    private function validateCovers(ZipArchive $zip, array $entries, bool $includesMedia): void
    {
        foreach ($entries as $path) {
            if (! in_array(basename($path), self::COVER_BEARING_DESCRIPTORS, true) || ! str_starts_with($path, 'data/')) {
                continue;
            }

            $descriptor = $this->decodeJson($zip, $path);

            if (! array_key_exists('cover_file', $descriptor)) {
                continue; // an entity without a cover
            }

            // Treat declared JSON paths as untrusted archive entry names.
            $coverFile = $descriptor['cover_file'];
            if (! is_string($coverFile) || $this->isUnsafePath($coverFile) || str_ends_with($coverFile, '/')) {
                throw ImportValidationException::unsafeEntryPath(is_string($coverFile) ? $coverFile : '');
            }

            $archivePath = dirname($path).'/'.$coverFile;
            $stat = $zip->statName($archivePath);

            $maxBytes = ImportRules::maxMediaBytes(CodexMediaCollection::Cover);
            if ($stat !== false && $stat['size'] > $maxBytes) {
                throw ImportValidationException::entryTooLarge($archivePath, $maxBytes);
            }

            $bytes = $stat === false ? false : $zip->getFromName($archivePath);

            if ($bytes === false) {
                if ($includesMedia) {
                    throw ImportValidationException::missingMediaFile($path, $archivePath);
                }

                continue;
            }

            $this->validateCoverContent($path, $archivePath, $bytes);
        }
    }

    /** Requires finfo and getimagesize to agree on an allowed image type. */
    private function validateCoverContent(string $descriptorPath, string $archivePath, string $bytes): void
    {
        $sniffedMime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        $imageInfo = getimagesizefromstring($bytes);

        if (! in_array($sniffedMime, ImportRules::IMAGE_MIME_TYPES, true)
            || $imageInfo === false
            || ($imageInfo['mime'] ?? null) !== $sniffedMime) {
            throw ImportValidationException::mediaContentMismatch($archivePath);
        }
    }

    /** Requires the declaration, finfo, and getimagesize to agree. */
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
     * Requires declared and sniffed document types to be allowed.
     * Libmagic can use different valid names for office formats.
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
