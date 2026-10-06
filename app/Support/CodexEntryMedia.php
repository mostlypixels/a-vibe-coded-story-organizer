<?php

namespace App\Support;

use App\Enums\CodexMediaCollection;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use Illuminate\Support\Collection;

/**
 * The media of a codex entry, split by collection and in display order.
 */
final class CodexEntryMedia
{
    /**
     * @param  Collection<int, CodexMedia>  $referenceImages
     * @param  Collection<int, CodexMedia>  $referenceFiles
     */
    private function __construct(
        public readonly ?CodexMedia $cover,
        public readonly Collection $referenceImages,
        public readonly Collection $referenceFiles,
        private readonly string $entryName,
    ) {}

    /** Eager-load `media` on the entry first. */
    public static function of(CodexEntry $entry): self
    {
        $media = $entry->media;

        return new self(
            cover: $media->firstWhere('collection', CodexMediaCollection::Cover),
            referenceImages: $media->where('collection', CodexMediaCollection::ReferenceImage)->sortBy('position')->values(),
            referenceFiles: $media->where('collection', CodexMediaCollection::ReferenceFile)->sortBy('position')->values(),
            entryName: $entry->name,
        );
    }

    /**
     * The cover first, then the reference images.
     *
     * @return list<array{url: string, alt: string}>
     */
    public function gallery(): array
    {
        return collect([$this->cover])
            ->filter()
            ->concat($this->referenceImages)
            ->map(fn (CodexMedia $media) => [
                'url' => $media->url(),
                'alt' => $media->original_name ?? $this->entryName,
            ])
            ->values()
            ->all();
    }
}
