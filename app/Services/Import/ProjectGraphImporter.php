<?php

namespace App\Services\Import;

use App\Enums\CodexEntryType;
use App\Enums\CodexMediaCollection;
use App\Enums\ImportPhase;
use App\Enums\SceneStatus;
use App\Exceptions\ImportValidationException;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\User;
use App\Services\CodexMediaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The import domain core: given an ALREADY-VALIDATED, already-extracted export
 * archive directory and the importing user, insert the full project graph.
 *
 * One method per graph {@see ImportPhase} (project → timeline →
 * story → codex), each independently callable and each wrapped in its OWN
 * DB::transaction() — never one transaction for the whole import. A crash
 * mid-phase rolls back only that phase while prior committed phases survive;
 * that is what makes an import resumable (task 05 persists the checkpoint).
 *
 * Contract with the caller (ProjectImporter, task 05):
 *   - `$dataPath` is the extraction root — the directory that CONTAINS `data/`
 *     (and possibly the ignored `book/` + `README.md`).
 *   - The archive has already passed {@see ArchiveValidator}; this class still
 *     runs every description.html / notes.html / contents.md it reads through
 *     {@see ContentSanitizer} inline (nothing is persisted unsanitized).
 *   - `$idMaps` accumulates one `array<sourceId, newId>` map per entity type;
 *     the caller persists it after each phase and hands it back on resume.
 *
 * Binding rules implemented here (see .specs → import → data-model.md):
 *   - Ids are ALWAYS remapped; an unresolvable reference throws, never skips.
 *   - The main plotline and Start/End bookends are reconciled onto the new
 *     project's auto-created rows (an update, never a duplicate insert).
 *   - `position` is replayed verbatim from the archive's JSON — never left
 *     null for the HasSiblingPosition creating() hook to re-derive.
 *   - Media bytes are copied to a freshly generated storage path; a declared
 *     media row whose bytes are absent (metadata-only export) still creates a
 *     row with a null path.
 */
class ProjectGraphImporter
{
    /**
     * The `$idMaps` keys, one per remapped entity type. Public so the
     * orchestrator (task 05) and tests never spell them as magic strings.
     */
    public const MAP_PLOTLINES = 'plotlines';

    public const MAP_EVENTS = 'events';

    public const MAP_ACTS = 'acts';

    public const MAP_CHAPTERS = 'chapters';

    public const MAP_SCENES = 'scenes';

    public const MAP_TAGS = 'tags';

    public const MAP_ATTRIBUTES = 'attributes';

    public const MAP_ENTRIES = 'entries';

    public function __construct(
        private ContentSanitizer $contentSanitizer,
        private CodexMediaService $codexMediaService,
    ) {}

    /**
     * Phase 1 — create the Project for $user from data/project/.
     *
     * Creating the row triggers Project::booted()'s created hook, which
     * auto-creates the main plotline and Start/End bookend events phase 2
     * reconciles onto. On a name collision (case-insensitive match against the
     * user's existing project names) the new name gets a timestamp suffix —
     * import never merges into or blocks on an existing project.
     *
     * The four front-/back-matter fields (task 02, epub-configuration) are
     * Markdown, read through the same sanitizer gate as a scene's contents.md.
     * An archive that pre-dates them (manifest version 1) simply omits their
     * `*_file` link keys, so readMarkdownField() returns null for each — no crash.
     */
    public function importProject(string $dataPath, User $user): Project
    {
        $dataPath = $this->normalizePath($dataPath);

        $descriptor = $this->readJson($dataPath, 'data/project/project.json');
        $description = $this->readHtmlField($dataPath, 'data/project', $descriptor);
        $dedication = $this->readMarkdownField($dataPath, 'data/project', $descriptor, 'dedication_file');
        $acknowledgements = $this->readMarkdownField($dataPath, 'data/project', $descriptor, 'acknowledgements_file');
        $preface = $this->readMarkdownField($dataPath, 'data/project', $descriptor, 'preface_file');
        $postface = $this->readMarkdownField($dataPath, 'data/project', $descriptor, 'postface_file');

        return DB::transaction(fn (): Project => $user->projects()->create([
            'name' => $this->collisionFreeName((string) $descriptor['name'], $user),
            'description' => $description,
            'dedication' => $dedication,
            'acknowledgements' => $acknowledgements,
            'preface' => $preface,
            'postface' => $postface,
        ]));
    }

    /**
     * Phase 2 — plotlines then events from data/timeline/.
     *
     * The archive's is_main plotline and its two is_fixed bookend events are
     * UPDATED onto the project's auto-created rows (every recorded field, not
     * a partial list) and their source ids mapped onto those existing rows;
     * everything else is inserted normally. Events resolve `plotline_ids`
     * through the plotline map built moments earlier.
     */
    public function importTimeline(string $dataPath, Project $project, array &$idMaps): void
    {
        $dataPath = $this->normalizePath($dataPath);

        $plotlines = $this->readEntityDescriptors($dataPath, 'data/timeline/plotlines/*/plotline.json');
        $events = $this->readEntityDescriptors($dataPath, 'data/timeline/events/*/event.json');

        // The anchors must be reconcilable before anything is written: exactly
        // one main plotline and exactly two fixed bookends, like every real
        // export carries (Project::booted() seeds the same set).
        $mainPlotlines = array_values(array_filter(
            $plotlines,
            fn (array $item): bool => (bool) $item['data']['is_main'],
        ));
        if (count($mainPlotlines) !== 1) {
            throw ImportValidationException::unexpectedAnchorCount('main plotline', 1, count($mainPlotlines));
        }

        $fixedEvents = array_values(array_filter(
            $events,
            fn (array $item): bool => (bool) $item['data']['is_fixed'],
        ));
        if (count($fixedEvents) !== 2) {
            throw ImportValidationException::unexpectedAnchorCount('fixed bookend event', 2, count($fixedEvents));
        }

        // Which archive bookend is Start and which is End follows the same
        // canonical (event_datetime, id) ordering Project::startEvent() uses.
        usort($fixedEvents, fn (array $a, array $b): int => [
            Carbon::parse((string) $a['data']['event_datetime']), (int) $a['data']['id'],
        ] <=> [
            Carbon::parse((string) $b['data']['event_datetime']), (int) $b['data']['id'],
        ]);

        DB::transaction(function () use ($dataPath, $project, &$idMaps, $plotlines, $events, $mainPlotlines, $fixedEvents): void {
            // --- Plotlines: reconcile the main one, insert the rest. -------
            $mainRow = $project->plotlines()->where('is_main', true)->firstOrFail();
            $mainData = $mainPlotlines[0]['data'];
            $mainRow->update([
                'name' => $mainData['name'],
                'color' => $mainData['color'],
                'description' => $this->readHtmlField($dataPath, $mainPlotlines[0]['directory'], $mainData),
            ]);
            $idMaps[self::MAP_PLOTLINES][(int) $mainData['id']] = $mainRow->id;

            foreach ($plotlines as $item) {
                if ((bool) $item['data']['is_main']) {
                    continue; // reconciled above
                }

                $plotline = $project->plotlines()->create([
                    'name' => $item['data']['name'],
                    'color' => $item['data']['color'],
                    'is_main' => false,
                    'description' => $this->readHtmlField($dataPath, $item['directory'], $item['data']),
                ]);
                $idMaps[self::MAP_PLOTLINES][(int) $item['data']['id']] = $plotline->id;
            }

            // --- Events: reconcile the two bookends, insert the rest. ------
            // Resolve BOTH auto-created bookends before either update: updating
            // Start's datetime first could otherwise change which row endEvent()
            // finds.
            $anchorRows = [$project->startEvent(), $project->endEvent()];

            foreach ($fixedEvents as $index => $item) {
                $row = $anchorRows[$index];
                $row->update([
                    'title' => $item['data']['title'],
                    'event_datetime' => $item['data']['event_datetime'],
                    'description' => $this->readHtmlField($dataPath, $item['directory'], $item['data']),
                ]);
                $idMaps[self::MAP_EVENTS][(int) $item['data']['id']] = $row->id;

                // sync (not attach): the auto-created bookends already carry a
                // main-plotline attachment; the archive's list replaces it.
                $row->plotlines()->sync(
                    $this->resolveIds($idMaps, self::MAP_PLOTLINES, $item['data']['plotline_ids'], $item['path'], 'plotline_ids'),
                );
            }

            foreach ($events as $item) {
                if ((bool) $item['data']['is_fixed']) {
                    continue; // reconciled above
                }

                $event = $project->events()->create([
                    'title' => $item['data']['title'],
                    'event_datetime' => $item['data']['event_datetime'],
                    'is_fixed' => false,
                    'description' => $this->readHtmlField($dataPath, $item['directory'], $item['data']),
                ]);
                $idMaps[self::MAP_EVENTS][(int) $item['data']['id']] = $event->id;

                $event->plotlines()->attach(
                    $this->resolveIds($idMaps, self::MAP_PLOTLINES, $item['data']['plotline_ids'], $item['path'], 'plotline_ids'),
                );
            }
        });
    }

    /**
     * Phase 3 — the act → chapter → scene tree from data/acts/.
     *
     * Parentage follows the archive's directory nesting (the export contract:
     * "nesting mirrors ownership"); every `position` is replayed verbatim from
     * the JSON, and each scene's event references resolve through the event
     * map phase 2 fully populated.
     */
    public function importStory(string $dataPath, Project $project, array &$idMaps): void
    {
        $dataPath = $this->normalizePath($dataPath);

        DB::transaction(function () use ($dataPath, $project, &$idMaps): void {
            foreach ($this->readEntityDescriptors($dataPath, 'data/acts/*/act.json') as $actItem) {
                $act = $project->acts()->create([
                    'name' => $actItem['data']['name'],
                    'position' => (int) $actItem['data']['position'],
                    'description' => $this->readHtmlField($dataPath, $actItem['directory'], $actItem['data']),
                ]);
                $idMaps[self::MAP_ACTS][(int) $actItem['data']['id']] = $act->id;

                $this->importChapters($dataPath, $act, $actItem['directory'], $idMaps);
            }
        });
    }

    /**
     * Phase 4 — tags, attribute definitions, then every Codex entry (aliases,
     * tag links, event-anchored attribute values, media) from data/codex/ and
     * data/tags.json.
     *
     * Media bytes present in the archive are copied to a freshly generated
     * storage path; a declared media row without bytes (metadata-only export)
     * still creates its row with a null path. Disk copies are not covered by
     * the DB transaction, so on ANY failure the files copied so far are
     * removed before rethrowing — a rolled-back phase never leaks orphans.
     */
    public function importCodex(string $dataPath, Project $project, array &$idMaps): void
    {
        $dataPath = $this->normalizePath($dataPath);

        $copiedPaths = [];

        try {
            DB::transaction(function () use ($dataPath, $project, &$idMaps, &$copiedPaths): void {
                foreach ($this->readJsonIfPresent($dataPath, 'data/tags.json') as $tagData) {
                    $tag = $project->tags()->create(['name' => $tagData['name']]);
                    $idMaps[self::MAP_TAGS][(int) $tagData['id']] = $tag->id;
                }

                foreach ($this->readJsonIfPresent($dataPath, 'data/codex/attributes.json') as $attributeData) {
                    $attribute = $project->codexAttributes()->create([
                        'name' => $attributeData['name'],
                        'applies_to' => $this->parseEntryTypes($attributeData['applies_to'], 'data/codex/attributes.json'),
                        'position' => (int) $attributeData['position'],
                    ]);
                    $idMaps[self::MAP_ATTRIBUTES][(int) $attributeData['id']] = $attribute->id;
                }

                foreach ($this->readEntityDescriptors($dataPath, 'data/codex/*/*/entry.json') as $item) {
                    $this->importCodexEntry($dataPath, $project, $item, $idMaps, $copiedPaths);
                }
            });
        } catch (Throwable $exception) {
            $this->codexMediaService->deleteFiles($copiedPaths);

            throw $exception;
        }
    }

    /**
     * The chapters (and their scenes) nested under one act directory.
     */
    private function importChapters(string $dataPath, Act $act, string $actDirectory, array &$idMaps): void
    {
        foreach ($this->readEntityDescriptors($dataPath, "{$actDirectory}/chapters/*/chapter.json") as $chapterItem) {
            $chapter = $act->chapters()->create([
                'name' => $chapterItem['data']['name'],
                'position' => (int) $chapterItem['data']['position'],
                'description' => $this->readHtmlField($dataPath, $chapterItem['directory'], $chapterItem['data']),
            ]);
            $idMaps[self::MAP_CHAPTERS][(int) $chapterItem['data']['id']] = $chapter->id;

            foreach ($this->readEntityDescriptors($dataPath, "{$chapterItem['directory']}/scenes/*/scene.json") as $sceneItem) {
                $this->importScene($dataPath, $chapter, $sceneItem, $idMaps);
            }
        }
    }

    /**
     * One scene: scalars + position from JSON, prose/notes through the
     * sanitizer, and both event references resolved to NEW ids.
     *
     * @param  array{path: string, directory: string, data: array<string, mixed>}  $item
     */
    private function importScene(string $dataPath, Chapter $chapter, array $item, array &$idMaps): void
    {
        $data = $item['data'];

        $scene = $chapter->scenes()->create(array_filter([
            'name' => $data['name'],
            'position' => (int) $data['position'],
            // A null status is possible in the JSON; omitting the key lets the
            // column default apply (array_filter drops the null below).
            'status' => $this->parseSceneStatus($data['status'], $item['path']),
            'event_id' => $data['event_id'] === null
                ? null
                : $this->resolveId($idMaps, self::MAP_EVENTS, $data['event_id'], $item['path'], 'event_id'),
            'contents' => $this->readMarkdownField($dataPath, $item['directory'], $data),
            'description' => $this->readHtmlField($dataPath, $item['directory'], $data),
            'notes' => $this->readHtmlField($dataPath, $item['directory'], $data, 'notes_file'),
        ], fn (mixed $value): bool => $value !== null));
        $idMaps[self::MAP_SCENES][(int) $data['id']] = $scene->id;

        $mentionedEventIds = $this->resolveIds($idMaps, self::MAP_EVENTS, $data['mentioned_event_ids'], $item['path'], 'mentioned_event_ids');
        if ($mentionedEventIds !== []) {
            $scene->mentionedEvents()->attach($mentionedEventIds);
        }
    }

    /**
     * One Codex entry with everything it owns: aliases, tag links, its
     * event-anchored attribute values, and its media rows.
     *
     * @param  array{path: string, directory: string, data: array<string, mixed>}  $item
     * @param  array<int, string>  $copiedPaths
     */
    private function importCodexEntry(string $dataPath, Project $project, array $item, array &$idMaps, array &$copiedPaths): void
    {
        $data = $item['data'];

        $type = CodexEntryType::tryFrom((string) $data['type'])
            ?? throw ImportValidationException::invalidDescriptorValue($item['path'], 'type');

        $entry = $project->codexEntries()->create([
            'type' => $type,
            'name' => $data['name'],
            'description' => $this->readHtmlField($dataPath, $item['directory'], $data),
        ]);
        $idMaps[self::MAP_ENTRIES][(int) $data['id']] = $entry->id;

        foreach ((array) $data['aliases'] as $alias) {
            $entry->aliases()->create(['alias' => (string) $alias]);
        }

        $tagIds = $this->resolveIds($idMaps, self::MAP_TAGS, $data['tag_ids'], $item['path'], 'tag_ids');
        if ($tagIds !== []) {
            $entry->tags()->attach($tagIds);
        }

        foreach ((array) $data['attribute_values'] as $value) {
            $entry->attributeValues()->create([
                'codex_attribute_id' => $this->resolveId($idMaps, self::MAP_ATTRIBUTES, $value['attribute_id'] ?? null, $item['path'], 'attribute_values.attribute_id'),
                'start_event_id' => $this->resolveId($idMaps, self::MAP_EVENTS, $value['start_event_id'] ?? null, $item['path'], 'attribute_values.start_event_id'),
                'value' => (string) ($value['value'] ?? ''),
            ]);
        }

        foreach ((array) $data['media'] as $media) {
            $this->importCodexMedia($dataPath, $entry, $item, $media, $copiedPaths);
        }
    }

    /**
     * One media row. Bytes present in the extracted archive are copied to a
     * freshly generated storage path (never the archive's own path); absent
     * bytes (metadata-only export) still create the row, with a null path.
     *
     * @param  array{path: string, directory: string, data: array<string, mixed>}  $item
     * @param  array<string, mixed>  $media
     * @param  array<int, string>  $copiedPaths
     */
    private function importCodexMedia(string $dataPath, CodexEntry $entry, array $item, array $media, array &$copiedPaths): void
    {
        $collection = CodexMediaCollection::tryFrom((string) $media['collection'])
            ?? throw ImportValidationException::invalidDescriptorValue($item['path'], 'media.collection');

        // The declared file path already passed ArchiveValidator's traversal
        // check (it treats entry.json's `file` like a real zip entry name).
        $absoluteFile = "{$dataPath}/{$item['directory']}/{$media['file']}";

        $path = null;
        if (is_file($absoluteFile)) {
            $path = $this->codexMediaService->storeImportedFile($absoluteFile);
            $copiedPaths[] = $path;
        }

        $entry->media()->create([
            'collection' => $collection,
            'path' => $path,
            // Re-derived via basename() regardless of what the JSON claims —
            // never trusted as pre-sanitized (export-format.md security note).
            'original_name' => basename((string) $media['original_name']),
            'mime_type' => (string) $media['mime_type'],
            'size' => (int) $media['size'],
            'position' => (int) $media['position'],
        ]);
    }

    /**
     * The archive's project name, suffixed with a timestamp when the importing
     * user already has a project with that name (case-insensitive). A
     * collision only ever renames the NEW project — it never blocks creation.
     */
    private function collisionFreeName(string $name, User $user): string
    {
        $taken = $user->projects()->pluck('name')
            ->map(fn (string $existing): string => mb_strtolower($existing))
            ->contains(mb_strtolower($name));

        return $taken
            ? sprintf('%s (imported %s)', $name, now()->format('Y-m-d H:i'))
            : $name;
    }

    /**
     * Parse a scene.json `status` into the enum (null stays null so the
     * column default applies); an unknown value is a corrupted archive.
     */
    private function parseSceneStatus(mixed $status, string $descriptorPath): ?SceneStatus
    {
        if ($status === null) {
            return null;
        }

        return SceneStatus::tryFrom((string) $status)
            ?? throw ImportValidationException::invalidDescriptorValue($descriptorPath, 'status');
    }

    /**
     * Parse an attribute's applies_to list into CodexEntryType enums,
     * rejecting any value that is not a real entry type.
     *
     * @return array<int, CodexEntryType>
     */
    private function parseEntryTypes(mixed $types, string $descriptorPath): array
    {
        return array_map(
            fn (mixed $type): CodexEntryType => CodexEntryType::tryFrom((string) $type)
                ?? throw ImportValidationException::invalidDescriptorValue($descriptorPath, 'applies_to'),
            is_array($types) ? $types : [],
        );
    }

    /**
     * Resolve one source id through an id map. A miss means the archive
     * references a record it never carried — a validation failure, never a
     * silently dropped relationship (data-model.md, binding default).
     */
    private function resolveId(array $idMaps, string $mapKey, mixed $sourceId, string $descriptorPath, string $jsonKey): int
    {
        $resolved = is_numeric($sourceId)
            ? ($idMaps[$mapKey][(int) $sourceId] ?? null)
            : null;

        if ($resolved === null) {
            throw ImportValidationException::unresolvedReference($descriptorPath, $jsonKey);
        }

        return (int) $resolved;
    }

    /**
     * Resolve a list of source ids through an id map (see resolveId()).
     *
     * @return array<int, int>
     */
    private function resolveIds(array $idMaps, string $mapKey, mixed $sourceIds, string $descriptorPath, string $jsonKey): array
    {
        return array_map(
            fn (mixed $sourceId): int => $this->resolveId($idMaps, $mapKey, $sourceId, $descriptorPath, $jsonKey),
            is_array($sourceIds) ? $sourceIds : [],
        );
    }

    /**
     * Locate every descriptor matching a glob pattern (relative to the
     * extraction root) and decode it, keeping its relative path for error
     * messages and its directory for sibling field files.
     *
     * @return array<int, array{path: string, directory: string, data: array<string, mixed>}>
     */
    private function readEntityDescriptors(string $dataPath, string $pattern): array
    {
        $items = [];

        foreach (glob("{$dataPath}/{$pattern}") ?: [] as $absolute) {
            $relative = substr(str_replace('\\', '/', $absolute), strlen($dataPath) + 1);

            $items[] = [
                'path' => $relative,
                'directory' => dirname($relative),
                'data' => $this->readJson($dataPath, $relative),
            ];
        }

        return $items;
    }

    /**
     * Read + sanitize a rich-HTML field file linked from a descriptor. A
     * missing link key means "field is null/empty" (the export null-handling
     * rule) — never an error; a DANGLING link (key present, file absent) is a
     * corrupted archive.
     */
    private function readHtmlField(string $dataPath, string $directory, array $descriptor, string $key = 'description_file'): ?string
    {
        $html = $this->readFieldFile($dataPath, $directory, $descriptor, $key);

        if ($html !== null) {
            $this->contentSanitizer->assertHtmlAllowed($html);
        }

        return $html;
    }

    /**
     * Read + sanitize a Markdown field file (a scene's contents.md), checking
     * both well-formedness and the rendered output's allow-list.
     */
    private function readMarkdownField(string $dataPath, string $directory, array $descriptor, string $key = 'contents_file'): ?string
    {
        $markdown = $this->readFieldFile($dataPath, $directory, $descriptor, $key);

        if ($markdown !== null) {
            $this->contentSanitizer->assertMarkdownAllowed($markdown);
        }

        return $markdown;
    }

    /**
     * The raw contents of a `*_file`-linked sibling file, or null when the
     * descriptor omits the key (the field was empty at export time).
     */
    private function readFieldFile(string $dataPath, string $directory, array $descriptor, string $key): ?string
    {
        if (! isset($descriptor[$key])) {
            return null;
        }

        $relative = "{$directory}/{$descriptor[$key]}";
        $contents = @file_get_contents("{$dataPath}/{$relative}");

        if ($contents === false) {
            throw ImportValidationException::missingDescriptor($relative);
        }

        return $contents;
    }

    /**
     * Decode one JSON descriptor by its path relative to the extraction root.
     *
     * @return array<mixed>
     */
    private function readJson(string $dataPath, string $relativePath): array
    {
        $raw = @file_get_contents("{$dataPath}/{$relativePath}");

        if ($raw === false) {
            throw ImportValidationException::missingDescriptor($relativePath);
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw ImportValidationException::malformedDescriptor($relativePath);
        }

        return $decoded;
    }

    /**
     * Like readJson(), but an absent file reads as an empty list — the flat
     * tags/attributes descriptors are optional in a hand-built archive.
     *
     * @return array<mixed>
     */
    private function readJsonIfPresent(string $dataPath, string $relativePath): array
    {
        return is_file("{$dataPath}/{$relativePath}")
            ? $this->readJson($dataPath, $relativePath)
            : [];
    }

    /**
     * Normalize the extraction root to forward slashes with no trailing
     * separator, so every joined path (glob patterns, error messages,
     * substr offsets) is consistent across platforms.
     */
    private function normalizePath(string $dataPath): string
    {
        return rtrim(str_replace('\\', '/', $dataPath), '/');
    }
}
