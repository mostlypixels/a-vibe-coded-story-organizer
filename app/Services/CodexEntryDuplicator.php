<?php

namespace App\Services;

use App\Models\CodexAlias;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Duplicates a codex entry into a new entry of the same type in the same project.
 *
 * Copies the rows the entry owns: aliases, media (each with its own copied file),
 * attribute values, and the tag pivots. Tags are re-attached, never re-created —
 * `Tag::count()` does not change. The derived `scene_codex_entry` pivot is never
 * copied; SceneReferenceMatcher rebuilds it.
 *
 * > [!WARNING]
 * > The file copies happen BEFORE the transaction, which inverts the "disk after
 * > commit" order the rest of the codex flow uses. The new media rows must carry
 * > the new paths, so the files must exist first. A failed transaction deletes the
 * > copies again. A crash between the two leaks an orphan file, which no user sees;
 * > the other order leaves a row that points at a missing file, which every user sees.
 */
class CodexEntryDuplicator
{
    public function __construct(
        private readonly CodexMediaService $media,
        private readonly SceneReferenceMatcher $matcher,
    ) {}

    public function duplicate(CodexEntry $entry, string $name): CodexEntry
    {
        $entry->load('aliases', 'media', 'attributeValues', 'tags');

        $mediaRows = $this->copyMediaFiles($entry);
        $copiedPaths = array_values(array_filter(array_column($mediaRows, 'path')));

        try {
            $copy = DB::transaction(function () use ($entry, $name, $mediaRows) {
                $copy = $entry->project->codexEntries()->create([
                    'type' => $entry->type,
                    'name' => $name,
                    'description' => $entry->description,
                ]);

                $copy->aliases()->createMany(
                    $entry->aliases->map(fn (CodexAlias $alias) => ['alias' => $alias->alias])->all()
                );

                // `position` is replayed instead of left to the CodexMedia creating()
                // hook, so the copy keeps the order the writer arranged.
                $copy->media()->createMany($mediaRows);

                // The unique index on codex_attribute_values is per entry, so the same
                // (attribute, start event) pairs cannot collide on a different entry.
                $copy->attributeValues()->createMany(
                    $entry->attributeValues->map(fn (CodexAttributeValue $value) => [
                        'codex_attribute_id' => $value->codex_attribute_id,
                        'start_event_id' => $value->start_event_id,
                        'value' => $value->value,
                    ])->all()
                );

                $copy->tags()->sync($entry->tags->modelKeys());

                return $copy;
            });
        } catch (Throwable $e) {
            $this->media->deleteFiles($copiedPaths);

            throw $e;
        }

        // The copy adds a name and a full set of copied aliases to the project, so
        // every scene must be rescanned — the same condition a create rescans for.
        $this->matcher->syncProject($entry->project);

        return $copy;
    }

    /**
     * The attributes of each new media row, with its file already copied to a fresh path.
     *
     * A metadata-only imported row has `path === null` and no file to copy; it
     * duplicates as another metadata-only row.
     *
     * @return array<int, array<string, mixed>>
     */
    private function copyMediaFiles(CodexEntry $entry): array
    {
        return $entry->media->map(fn (CodexMedia $media) => [
            'collection' => $media->collection,
            'path' => $media->path === null ? null : $this->media->copyFile($media->path),
            'original_name' => $media->original_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'position' => $media->position,
        ])->all();
    }
}
