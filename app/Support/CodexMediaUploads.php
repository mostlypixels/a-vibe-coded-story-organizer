<?php

namespace App\Support;

use App\Services\CodexEntrySaver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The media half of a codex entry form, read off the request once.
 *
 * {@see CodexEntrySaver} needs to know which files arrived and
 * which stored media the writer ticked for removal, but it must not read the
 * request itself — a service that reaches for `request()` cannot be called from
 * a command or a test without a fake HTTP layer. The controller builds this
 * object from the already-validated request and hands it over.
 */
class CodexMediaUploads
{
    /**
     * @param  array<int, int|string>  $removeIds  Stored media rows to drop.
     * @param  array<int, UploadedFile>  $referenceImages
     * @param  array<int, UploadedFile>  $referenceFiles
     */
    public function __construct(
        public readonly ?UploadedFile $cover = null,
        public readonly array $removeIds = [],
        public readonly array $referenceImages = [],
        public readonly array $referenceFiles = [],
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            cover: $request->hasFile('cover') ? $request->file('cover') : null,
            removeIds: (array) $request->validated('remove_media', []),
            referenceImages: $request->hasFile('reference_images') ? $request->file('reference_images') : [],
            referenceFiles: $request->hasFile('reference_files') ? $request->file('reference_files') : [],
        );
    }

    /** A new cover replaces the old one, so the old row must go in the same save. */
    public function replacesCover(): bool
    {
        return $this->cover !== null;
    }
}
