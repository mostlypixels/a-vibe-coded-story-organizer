<?php

namespace App\Support;

use App\Enums\CodexMediaCollection;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use Illuminate\Support\Collection;

/**
 * The data that the codex entry form shows, for the create and the edit page.
 *
 * The values come from `old()` first, so a failed validation keeps what the writer typed.
 */
final class CodexEntryForm
{
    /**
     * @param  list<string>  $aliasValues
     * @param  list<string>  $tagValues
     * @param  Collection<int, CodexMedia>  $referenceImages
     * @param  Collection<int, CodexMedia>  $referenceFiles
     * @param  Collection<int, CodexAttribute>  $attributes  Empty on the edit page, which has its own attribute sheets.
     * @param  list<array{id: string, name: string}>  $attributeOptions
     * @param  list<array{id: string, name: string, value: string}>  $pickedAttributes
     */
    private function __construct(
        public readonly array $aliasValues,
        public readonly array $tagValues,
        public readonly ?CodexMedia $cover,
        public readonly Collection $referenceImages,
        public readonly Collection $referenceFiles,
        public readonly Collection $attributes,
        public readonly array $attributeOptions,
        public readonly array $pickedAttributes,
    ) {}

    /** Eager-load `aliases`, `tags` and `media` on the entry first. */
    public static function forEdit(CodexEntry $entry): self
    {
        $media = $entry->media;

        return new self(
            aliasValues: old('aliases', $entry->aliases->pluck('alias')->values()->all()),
            tagValues: old('tags', $entry->tags->pluck('name')->values()->all()),
            cover: $media->firstWhere('collection', CodexMediaCollection::Cover),
            referenceImages: $media->where('collection', CodexMediaCollection::ReferenceImage)->sortBy('position')->values(),
            referenceFiles: $media->where('collection', CodexMediaCollection::ReferenceFile)->sortBy('position')->values(),
            attributes: collect(),
            attributeOptions: [],
            pickedAttributes: [],
        );
    }

    /** @param  Collection<int, CodexAttribute>  $attributes  The project attributes for this entry type. */
    public static function forCreate(Collection $attributes): self
    {
        return new self(
            aliasValues: old('aliases', []),
            tagValues: old('tags', []),
            cover: null,
            referenceImages: collect(),
            referenceFiles: collect(),
            attributes: $attributes,
            attributeOptions: $attributes
                ->map(fn (CodexAttribute $attribute) => ['id' => (string) $attribute->id, 'name' => $attribute->name])
                ->values()
                ->all(),
            pickedAttributes: self::pickedAttributes($attributes),
        );
    }

    /**
     * A pick survives a failed validation: `old()` keeps the IDs and the typed values.
     *
     * @param  Collection<int, CodexAttribute>  $attributes
     * @return list<array{id: string, name: string, value: string}>
     */
    private static function pickedAttributes(Collection $attributes): array
    {
        return collect(old('attribute_baselines', []))
            ->map(fn ($value) => (string) $value)
            ->filter(fn ($value, $id) => $attributes->contains('id', (int) $id))
            ->map(fn ($value, $id) => [
                'id' => (string) $id,
                'name' => $attributes->firstWhere('id', (int) $id)->name,
                'value' => $value,
            ])
            ->values()
            ->all();
    }
}
