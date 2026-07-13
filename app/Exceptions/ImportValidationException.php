<?php

namespace App\Exceptions;

use App\Services\Import\ArchiveValidator;
use App\Services\Import\ContentSanitizer;
use RuntimeException;

/**
 * Thrown by {@see ArchiveValidator} and {@see ContentSanitizer}
 * when an uploaded archive fails validation.
 *
 * Like {@see EpubExportException}, this is a **user input problem**, not a bug:
 * the archive the user uploaded is not a valid export. The message is therefore
 * user-safe by construction — it names the offending archive entry / rule (both
 * of which came from the user's own upload), never an internal server path or a
 * stack trace. Task 06's controller turns it into a redirect-back-with-error.
 *
 * Each named constructor is one failure mode, so tests and callers can assert on
 * a specific, actionable message rather than a generic "invalid archive".
 */
class ImportValidationException extends RuntimeException
{
    /**
     * The upload could not be opened as a zip at all (wrong format or corrupt).
     */
    public static function notAZip(): self
    {
        return new self('The uploaded file is not a valid zip archive.');
    }

    /**
     * An entry name tries to escape the archive root (zip-slip: "..", an
     * absolute path, or a drive-letter path).
     */
    public static function unsafeEntryPath(string $entry): self
    {
        return new self("The archive entry \"{$entry}\" uses an unsafe path and was rejected.");
    }

    /**
     * An entry sits outside the allow-listed export arborescence.
     */
    public static function disallowedEntryPath(string $entry): self
    {
        return new self("The archive contains an unexpected file \"{$entry}\" that is not part of a project export.");
    }

    /**
     * data/manifest.json is missing from the archive.
     */
    public static function missingManifest(): self
    {
        return new self('The archive is missing data/manifest.json and does not look like a project export.');
    }

    /**
     * The manifest's data-format version is not one this importer can read.
     */
    public static function unsupportedManifestVersion(mixed $version): self
    {
        $label = is_scalar($version) ? (string) $version : 'unknown';

        return new self("The archive uses export format version \"{$label}\", which this application cannot import.");
    }

    /**
     * A required descriptor file (e.g. data/project/project.json) is absent.
     */
    public static function missingDescriptor(string $path): self
    {
        return new self("The archive is missing the required descriptor \"{$path}\".");
    }

    /**
     * A JSON descriptor does not parse, or is not the object/array it must be.
     */
    public static function malformedDescriptor(string $path): self
    {
        return new self("The descriptor \"{$path}\" is not valid JSON and could not be read.");
    }

    /**
     * A JSON descriptor parses but is missing a required key.
     */
    public static function missingDescriptorKey(string $path, string $key): self
    {
        return new self("The descriptor \"{$path}\" is missing the required \"{$key}\" field.");
    }

    /**
     * A JSON descriptor parses but carries a value that cannot be valid (e.g.
     * an unknown media collection or a mime type no export ever writes).
     */
    public static function invalidDescriptorValue(string $path, string $key): self
    {
        return new self("The descriptor \"{$path}\" has an invalid value for \"{$key}\".");
    }

    /**
     * A media file an entry.json declares is absent from an archive whose
     * manifest says media bytes are included.
     */
    public static function missingMediaFile(string $descriptorPath, string $file): self
    {
        return new self("The media file \"{$file}\" declared in \"{$descriptorPath}\" is missing from the archive.");
    }

    /**
     * A media file's actual byte size disagrees with what its entry.json declared.
     */
    public static function mediaSizeMismatch(string $file): self
    {
        return new self("The media file \"{$file}\" does not match the size declared in the archive.");
    }

    /**
     * A media file's ACTUAL content (finfo-sniffed) is not what it claims to be
     * — the check that stops a renamed executable.
     */
    public static function mediaContentMismatch(string $file): self
    {
        return new self("The media file \"{$file}\" is not the type of file it claims to be and was rejected.");
    }

    /**
     * A descriptor references another record by source id, but no record with
     * that id exists in the archive (e.g. a scene's event_id pointing at an
     * event that was never exported). Import remaps every id through the map
     * built as rows are inserted; a miss is a corrupted archive, never a
     * silently dropped relationship.
     */
    public static function unresolvedReference(string $path, string $key): self
    {
        return new self("The descriptor \"{$path}\" references a \"{$key}\" record that does not exist in the archive.");
    }

    /**
     * The archive does not carry exactly the anchor rows every project must
     * have (one is_main plotline, two is_fixed bookend events) — without them
     * the importer cannot reconcile onto the new project's auto-created rows.
     */
    public static function unexpectedAnchorCount(string $entity, int $expected, int $actual): self
    {
        return new self("The archive must contain exactly {$expected} {$entity} record(s), but contains {$actual}.");
    }

    /**
     * An HTML fragment (description.html / notes.html, or rendered Markdown)
     * contains a tag, attribute, or URL scheme outside the app's rich-text
     * allow-list. Import rejects rather than strips — see ContentSanitizer.
     */
    public static function disallowedHtmlContent(): self
    {
        return new self('The archive contains HTML content that is not allowed and was rejected.');
    }

    /**
     * A contents.md file could not be parsed as Markdown at all.
     */
    public static function invalidMarkdown(): self
    {
        return new self('The archive contains a Markdown file that could not be read as valid Markdown.');
    }
}
