<?php

namespace App\Services;

use App\Enums\CodexEntryType;
use App\Enums\CodexMediaCollection;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\User;
use App\Services\Concerns\CreatesInlineEvents;
use App\Support\AutosavableFields;
use App\Support\CodexMediaUploads;
use Illuminate\Support\Facades\DB;

/**
 * Creates and updates a codex entry with everything that hangs off it: aliases,
 * project tags, attribute baselines, lifespan events, scene references, media,
 * and the manual revision checkpoint.
 *
 * A codex entry is not one row. Saving it touches seven tables and has an order
 * the writer never sees but always feels:
 *
 *  - **Rows commit together, files do not.** Media rows are dropped inside the
 *    transaction and their files deleted after it, and uploads are written after
 *    it as well. A rollback then leaves no row pointing at a missing file, which
 *    is the failure a reader would notice. {@see CodexMediaService::queueRemovals()}
 *  - **References are rebuilt from the saved name and aliases.** So the rescan
 *    runs inside the transaction, never against a half-saved entry.
 *  - **A rescan is project-wide and slow**, so an update runs it only when the
 *    matching terms actually changed. A create always adds a new term, so it
 *    always rescans.
 *
 * Nothing here reads the request. The controller resolves and authorizes, then
 * hands over validated data and a {@see CodexMediaUploads}.
 */
class CodexEntrySaver
{
    use CreatesInlineEvents;

    public function __construct(
        private readonly CodexMediaService $media,
        private readonly SceneReferenceMatcher $matcher,
        private readonly RevisionRecorder $recorder,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(Project $project, CodexEntryType $type, array $validated, CodexMediaUploads $uploads): CodexEntry
    {
        [$entry, $pathsToDelete] = DB::transaction(function () use ($project, $type, $validated, $uploads) {
            $entry = $project->codexEntries()->create([
                'type' => $type,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncAliases($entry, $validated['aliases'] ?? []);
            $entry->tags()->sync($this->resolveTags($project, $validated['tags'] ?? []));
            $this->seedAttributeBaselines($entry, $type, $validated['attribute_baselines'] ?? []);

            // A new name and aliases always add matching terms.
            $this->matcher->syncProject($project);

            return [$entry, $this->queueMediaRemovals($entry, $uploads)];
        });

        $this->writeFiles($entry, $uploads, $pathsToDelete);

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  User  $user  Credited on the manual revision checkpoint.
     */
    public function update(CodexEntry $entry, array $validated, CodexMediaUploads $uploads, User $user): void
    {
        $project = $entry->project;
        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            // Plain saves, not autosaved fields — no revision snapshot for these.
            'inception_event_id' => $this->resolveInlineEvent(
                $project,
                $validated['new_inception_event_title'] ?? null,
                $validated['new_inception_event_datetime'] ?? null,
                $validated['inception_event_id'] ?? null,
            ),
            'termination_event_id' => $this->resolveInlineEvent(
                $project,
                $validated['new_termination_event_title'] ?? null,
                $validated['new_termination_event_datetime'] ?? null,
                $validated['termination_event_id'] ?? null,
            ),
        ];

        $pathsToDelete = DB::transaction(function () use ($project, $entry, $validated, $data, $uploads, $user) {
            $termsBefore = $this->referenceTerms($entry->name, $entry->aliases()->pluck('alias')->all());

            // A first-ever save seeds its baseline with the timestamp the entry
            // carried here, and the save below overwrites it.
            $heldSince = $entry->updated_at;
            $before = AutosavableFields::snapshotFieldsBeforeUpdate($entry, $data);

            $entry->update($data);

            $this->syncAliases($entry, $validated['aliases'] ?? []);
            $entry->tags()->sync($this->resolveTags($project, $validated['tags'] ?? []));

            $termsAfter = $this->referenceTerms($entry->name, $entry->aliases()->pluck('alias')->all());

            if ($termsBefore !== $termsAfter) {
                $this->matcher->syncProject($project);
            }

            $this->recorder->recordManualChanges($entry, $before, $user, heldSince: $heldSince);

            return $this->queueMediaRemovals($entry, $uploads);
        });

        $this->writeFiles($entry, $uploads, $pathsToDelete);
    }

    /** @param array<int, string|null> $aliases */
    private function syncAliases(CodexEntry $entry, array $aliases): void
    {
        $entry->aliases()->delete();

        $rows = collect($aliases)
            ->map(fn ($alias) => trim((string) $alias))
            ->filter()
            ->unique()
            ->map(fn ($alias) => ['alias' => $alias])
            ->values()
            ->all();

        if ($rows !== []) {
            $entry->aliases()->createMany($rows);
        }
    }

    /**
     * Builds a sorted, case-sensitive set from the name and aliases.
     * A case change can change reference matches and must trigger a rescan.
     *
     * @param  array<int, string>  $aliases
     * @return array<int, string>
     */
    private function referenceTerms(string $name, array $aliases): array
    {
        return collect($aliases)
            ->push($name)
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string|null>  $names
     * @return array<int, int> Project-scoped tag IDs.
     */
    private function resolveTags(Project $project, array $names): array
    {
        return collect($names)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->map(fn ($name) => $project->tags()->firstOrCreate(['name' => $name])->id)
            ->values()
            ->all();
    }

    /** @param array<int|string, string|null> $baselines */
    private function seedAttributeBaselines(CodexEntry $entry, CodexEntryType $type, array $baselines): void
    {
        foreach ($entry->project->codexAttributesFor($type) as $attribute) {
            /** @var CodexAttribute $attribute */
            $value = (string) ($baselines[$attribute->id] ?? '');

            (new AttributeTimeline($entry, $attribute))->ensureBaseline($value);
        }
    }

    /** @return array<int, string> Paths to delete once the transaction commits. */
    private function queueMediaRemovals(CodexEntry $entry, CodexMediaUploads $uploads): array
    {
        return $this->media->queueRemovals($entry, $uploads->removeIds, $uploads->replacesCover());
    }

    /**
     * Does the disk work, after the commit.
     * A failed row insert removes its file, so partial failure leaves no orphan.
     *
     * @param  array<int, string>  $pathsToDelete
     */
    private function writeFiles(CodexEntry $entry, CodexMediaUploads $uploads, array $pathsToDelete): void
    {
        $this->media->deleteFiles($pathsToDelete);

        if ($uploads->cover !== null) {
            $this->media->storeCover($entry, $uploads->cover);
        }

        if ($uploads->referenceImages !== []) {
            $this->media->storeMany($entry, CodexMediaCollection::ReferenceImage, $uploads->referenceImages);
        }

        if ($uploads->referenceFiles !== []) {
            $this->media->storeMany($entry, CodexMediaCollection::ReferenceFile, $uploads->referenceFiles);
        }
    }
}
