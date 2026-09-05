<?php

namespace App\Services\Import;

use App\Enums\BookLanguage;
use App\Enums\CodexEntryType;
use App\Enums\CodexMediaCollection;
use App\Enums\SceneStatus;
use App\Enums\StoryOverviewMode;
use App\Exceptions\ImportValidationException;
use App\Http\Requests\UpdatePublicationSettingRequest;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\User;
use App\Services\CodexMediaService;
use App\Services\CoverImageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Imports a validated archive in resumable project, timeline, story, and codex phases.
 *
 * Each phase has its own transaction and persisted ID maps. All references must
 * resolve to remapped IDs. The importer reuses auto-created anchors and the first
 * book. It preserves stored positions and sanitizes all text again.
 *
 * Media receives new storage paths. Metadata-only media keeps a null path.
 * Imported entities start with no revision history.
 */
class ProjectGraphImporter
{
    /** Keys for the persisted source-to-new ID maps. */
    public const MAP_PLOTLINES = 'plotlines';

    public const MAP_EVENTS = 'events';

    public const MAP_BOOKS = 'books';

    public const MAP_ACTS = 'acts';

    public const MAP_CHAPTERS = 'chapters';

    public const MAP_SCENES = 'scenes';

    public const MAP_TAGS = 'tags';

    public const MAP_ATTRIBUTES = 'attributes';

    public const MAP_ENTRIES = 'entries';

    public function __construct(
        private ContentSanitizer $contentSanitizer,
        private CodexMediaService $codexMediaService,
        private CoverImageService $coverImageService,
    ) {}

    /**
     * Creates the project and its project-owned fields.
     *
     * A name collision adds a timestamp. Model hooks create anchors and the first
     * book for later phases to reuse.
     */
    public function importProject(string $dataPath, User $user): Project
    {
        $dataPath = $this->normalizePath($dataPath);

        $descriptor = $this->readJson($dataPath, 'data/project/project.json');
        $description = $this->readHtmlField($dataPath, 'data/project', $descriptor);
        $snapshots = $this->readWordCountSnapshots($dataPath);
        $challenges = $this->readChallenges($dataPath);

        // A database rollback cannot remove a copied file.
        $copiedCovers = [];
        $cover = $this->importCover($dataPath, 'data/project', $descriptor, CoverImageService::PROJECT_COVER_DIRECTORY, $copiedCovers);

        try {
            return DB::transaction(function () use ($user, $descriptor, $description, $cover, $snapshots, $challenges): Project {
                $project = $user->projects()->create([
                    'name' => $this->collisionFreeName((string) $descriptor['name'], $user),
                    'description' => $description,
                    'cover_image' => $cover,
                    'daily_word_goal' => isset($descriptor['daily_word_goal']) ? (int) $descriptor['daily_word_goal'] : null,
                    'total_word_goal' => isset($descriptor['total_word_goal']) ? (int) $descriptor['total_word_goal'] : null,
                ]);

                $this->importWordCountSnapshots($project, $snapshots);
                $this->importChallenges($project, $challenges);

                return $project;
            });
        } catch (Throwable $exception) {
            foreach ($copiedCovers as $coverPath) {
                $this->coverImageService->delete($coverPath);
            }

            throw $exception;
        }
    }

    /** @return array<int, array{recorded_on: string, word_count: int}> */
    private function readWordCountSnapshots(string $dataPath): array
    {
        /** @var array<int, array{recorded_on: string, word_count: int}> */
        return $this->readJsonIfPresent($dataPath, 'data/word-count-snapshots.json');
    }

    /**
     * Restores snapshots without model events that could add another row.
     *
     * @param  array<int, array{recorded_on: string, word_count: int}>  $snapshots
     */
    private function importWordCountSnapshots(Project $project, array $snapshots): void
    {
        if ($snapshots === []) {
            return;
        }

        $now = now();

        DB::table('word_count_snapshots')->insert(array_map(
            fn (array $snapshot): array => [
                'project_id' => $project->id,
                'recorded_on' => Carbon::parse((string) $snapshot['recorded_on'])->toDateString(),
                'word_count' => (int) $snapshot['word_count'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $snapshots,
        ));
    }

    /** @return array<int, array{name: string, recurrence: string, starts_on: string, ends_on: ?string, target_words: int}> */
    private function readChallenges(string $dataPath): array
    {
        /** @var array<int, array{name: string, recurrence: string, starts_on: string, ends_on: ?string, target_words: int}> */
        return $this->readJsonIfPresent($dataPath, 'data/challenges.json');
    }

    /**
     * Restores challenges exactly, without model events or re-derived progress.
     *
     * @param  array<int, array{name: string, recurrence: string, starts_on: string, ends_on: ?string, target_words: int}>  $challenges
     */
    private function importChallenges(Project $project, array $challenges): void
    {
        if ($challenges === []) {
            return;
        }

        $now = now();

        DB::table('challenges')->insert(array_map(
            fn (array $challenge): array => [
                'project_id' => $project->id,
                'name' => (string) $challenge['name'],
                'recurrence' => (string) $challenge['recurrence'],
                'starts_on' => Carbon::parse((string) $challenge['starts_on'])->toDateString(),
                'ends_on' => $challenge['ends_on'] === null ? null : Carbon::parse((string) $challenge['ends_on'])->toDateString(),
                'target_words' => (int) $challenge['target_words'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $challenges,
        ));
    }

    /** @return array<string, mixed>|null Valid settings or null for defaults. */
    private function readPublicationSetting(string $dataPath, string $bookDirectory): ?array
    {
        $absolute = "{$dataPath}/{$bookDirectory}/publication-setting.json";

        if (! is_file($absolute)) {
            return null; // no config in the archive → lazy default
        }

        $raw = @file_get_contents($absolute);
        $decoded = $raw === false ? null : json_decode($raw, true);

        // Invalid optional settings fall back to defaults.
        if (! is_array($decoded) || $decoded === []) {
            Log::warning('Import: publication-setting.json is missing or not a JSON object; importing with default publication settings.');

            return null;
        }

        return $this->validatePublicationSetting($decoded);
    }

    /**
     * Uses the form's rules and removes appendix types unknown to this build.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    private function validatePublicationSetting(array $config): ?array
    {
        if (isset($config['appendix_entry_types']) && is_array($config['appendix_entry_types'])) {
            $config['appendix_entry_types'] = array_values(array_filter(
                $config['appendix_entry_types'],
                fn (mixed $type): bool => is_string($type) && CodexEntryType::tryFrom($type) !== null,
            ));
        }

        $validator = Validator::make($config, UpdatePublicationSettingRequest::configRules());

        if ($validator->fails()) {
            Log::warning('Import: publication-setting.json failed validation; importing with default publication settings.', [
                'errors' => $validator->errors()->keys(),
            ]);

            return null;
        }

        // validated() removes IDs, timestamps, and other unrecognized keys.
        return $validator->validated();
    }

    /** Reuses the main plotline and bookend events, then imports the timeline graph. */
    public function importTimeline(string $dataPath, Project $project, array &$idMaps): void
    {
        $dataPath = $this->normalizePath($dataPath);

        $plotlines = $this->readEntityDescriptors($dataPath, 'data/timeline/plotlines/*/plotline.json');
        $events = $this->readEntityDescriptors($dataPath, 'data/timeline/events/*/event.json');

        // Validate all required anchors before this phase writes data.
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

        // Use the model's date and ID ordering to identify Start and End.
        usort($fixedEvents, fn (array $a, array $b): int => [
            Carbon::parse((string) $a['data']['event_datetime']), (int) $a['data']['id'],
        ] <=> [
            Carbon::parse((string) $b['data']['event_datetime']), (int) $b['data']['id'],
        ]);

        DB::transaction(function () use ($dataPath, $project, &$idMaps, $plotlines, $events, $mainPlotlines, $fixedEvents): void {
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

            // Resolve both bookends before an update can change their ordering.
            $anchorRows = [$project->startEvent(), $project->endEvent()];

            foreach ($fixedEvents as $index => $item) {
                $row = $anchorRows[$index];
                $row->update([
                    'title' => $item['data']['title'],
                    'event_datetime' => $item['data']['event_datetime'],
                    'description' => $this->readHtmlField($dataPath, $item['directory'], $item['data']),
                ]);
                $idMaps[self::MAP_EVENTS][(int) $item['data']['id']] = $row->id;

                // Replace the plotline link that the model hook created.
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
     * Imports the nested book, act, chapter, and scene graph with stored positions.
     *
     * A failure removes copied covers because the transaction cannot remove files.
     *
     * > [!WARNING]
     * > Update the auto-created first book. Insert only the remaining books.
     */
    public function importStory(string $dataPath, Project $project, array &$idMaps): void
    {
        $dataPath = $this->normalizePath($dataPath);

        $books = $this->readEntityDescriptors($dataPath, 'data/books/*/book.json');

        // The lowest position and ID identify the book that reuses the default row.
        usort($books, fn (array $a, array $b): int => [
            (int) $a['data']['position'], (int) $a['data']['id'],
        ] <=> [
            (int) $b['data']['position'], (int) $b['data']['id'],
        ]);

        // Validate optional settings before the transaction starts.
        $publicationSettings = [];
        foreach ($books as $bookItem) {
            $publicationSettings[$bookItem['directory']] = $this->readPublicationSetting($dataPath, $bookItem['directory']);
        }

        $copiedCovers = [];

        try {
            DB::transaction(function () use ($dataPath, $project, &$idMaps, &$copiedCovers, $books, $publicationSettings): void {
                $seededBook = $project->books()->first();

                foreach ($books as $index => $bookItem) {
                    $attributes = $this->bookAttributes($dataPath, $bookItem, $copiedCovers);

                    if ($index === 0) {
                        $seededBook->update($attributes);
                        $book = $seededBook;
                    } else {
                        $book = $project->books()->create($attributes);
                    }
                    $idMaps[self::MAP_BOOKS][(int) $bookItem['data']['id']] = $book->id;

                    $setting = $publicationSettings[$bookItem['directory']] ?? null;
                    if ($setting !== null) {
                        $book->publicationSetting()->create($setting);
                    }

                    $this->importActs($dataPath, $book, $bookItem['directory'], $idMaps, $copiedCovers);
                }
            });
        } catch (Throwable $exception) {
            foreach ($copiedCovers as $coverPath) {
                $this->coverImageService->delete($coverPath);
            }

            throw $exception;
        }
    }

    /**
     * Preserves a null book name so it continues to follow the project name.
     *
     * @param  array{path: string, directory: string, data: array<string, mixed>}  $bookItem
     * @param  array<int, string>  $copiedCovers
     * @return array<string, mixed>
     */
    private function bookAttributes(string $dataPath, array $bookItem, array &$copiedCovers): array
    {
        $data = $bookItem['data'];
        $directory = $bookItem['directory'];

        $attributes = [
            'name' => $data['name'],
            'position' => (int) $data['position'],
            'description' => $this->readHtmlField($dataPath, $directory, $data),
            'language' => $this->parseBookLanguage($data['language'] ?? null, $bookItem['path']),
            'author' => $data['author'] ?? null,
            'publisher' => $data['publisher'] ?? null,
            'isbn' => $data['isbn'] ?? null,
            'overview_render_mode' => $this->parseOverviewRenderMode($data['overview_render_mode'] ?? null, $bookItem['path']),
            // Rights is plain text, not rich HTML.
            'rights' => $this->readFieldFile($dataPath, $directory, $data, 'rights_file'),
            'dedication' => $this->readMarkdownField($dataPath, $directory, $data, 'dedication_file'),
            'acknowledgements' => $this->readMarkdownField($dataPath, $directory, $data, 'acknowledgements_file'),
            'preface' => $this->readMarkdownField($dataPath, $directory, $data, 'preface_file'),
            'postface' => $this->readMarkdownField($dataPath, $directory, $data, 'postface_file'),
            'cover_image' => $this->importCover($dataPath, $directory, $data, CoverImageService::BOOK_COVER_DIRECTORY, $copiedCovers),
        ];

        // Omit null values so database defaults apply.
        foreach (['language', 'overview_render_mode'] as $key) {
            if ($attributes[$key] === null) {
                unset($attributes[$key]);
            }
        }

        return $attributes;
    }

    /** Returns null for the database default and rejects unknown languages. */
    private function parseBookLanguage(mixed $language, string $descriptorPath): ?BookLanguage
    {
        if ($language === null) {
            return null;
        }

        return BookLanguage::tryFrom((string) $language)
            ?? throw ImportValidationException::invalidDescriptorValue($descriptorPath, 'language');
    }

    /** Returns null for the database default and rejects unknown overview modes. */
    private function parseOverviewRenderMode(mixed $mode, string $descriptorPath): ?StoryOverviewMode
    {
        if ($mode === null) {
            return null;
        }

        return StoryOverviewMode::tryFrom((string) $mode)
            ?? throw ImportValidationException::invalidDescriptorValue($descriptorPath, 'overview_render_mode');
    }

    /** @param array<int, string> $copiedCovers Paths to remove after a rollback. */
    private function importActs(string $dataPath, Book $book, string $bookDirectory, array &$idMaps, array &$copiedCovers): void
    {
        foreach ($this->readEntityDescriptors($dataPath, "{$bookDirectory}/acts/*/act.json") as $actItem) {
            $act = $book->acts()->create([
                'name' => $actItem['data']['name'],
                'position' => (int) $actItem['data']['position'],
                'description' => $this->readHtmlField($dataPath, $actItem['directory'], $actItem['data']),
            ]);
            $idMaps[self::MAP_ACTS][(int) $actItem['data']['id']] = $act->id;

            $this->importChapters($dataPath, $act, $actItem['directory'], $idMaps, $copiedCovers);
        }
    }

    /**
     * Imports codex definitions, tags, entries, values, links, and media.
     * A failure removes files that the database transaction cannot roll back.
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

    /** @param array<int, string> $copiedCovers Paths to remove after a rollback. */
    private function importChapters(string $dataPath, Act $act, string $actDirectory, array &$idMaps, array &$copiedCovers): void
    {
        foreach ($this->readEntityDescriptors($dataPath, "{$actDirectory}/chapters/*/chapter.json") as $chapterItem) {
            $chapter = $act->chapters()->create([
                'name' => $chapterItem['data']['name'],
                'position' => (int) $chapterItem['data']['position'],
                'description' => $this->readHtmlField($dataPath, $chapterItem['directory'], $chapterItem['data']),
                'cover_image' => $this->importCover($dataPath, $chapterItem['directory'], $chapterItem['data'], CoverImageService::CHAPTER_COVER_DIRECTORY, $copiedCovers),
            ]);
            $idMaps[self::MAP_CHAPTERS][(int) $chapterItem['data']['id']] = $chapter->id;

            foreach ($this->readEntityDescriptors($dataPath, "{$chapterItem['directory']}/scenes/*/scene.json") as $sceneItem) {
                $this->importScene($dataPath, $chapter, $sceneItem, $idMaps);
            }
        }
    }

    /**
     * Copies a validated cover to a new path or returns null when bytes are absent.
     *
     * @param  array<string, mixed>  $descriptor
     * @param  string  $storageDirectory  The {@see CoverImageService} destination.
     * @param  array<int, string>  $copiedCovers
     */
    private function importCover(string $dataPath, string $directory, array $descriptor, string $storageDirectory, array &$copiedCovers): ?string
    {
        $coverFile = $descriptor['cover_file'] ?? null;

        if (! is_string($coverFile) || $coverFile === '') {
            return null;
        }

        $absoluteFile = "{$dataPath}/{$directory}/{$coverFile}";

        if (! is_file($absoluteFile)) {
            return null; // metadata-only export: link declared, bytes absent
        }

        $path = $this->coverImageService->storeImportedFile($absoluteFile, $storageDirectory);
        $copiedCovers[] = $path;

        return $path;
    }

    /** @param array{path: string, directory: string, data: array<string, mixed>} $item */
    private function importScene(string $dataPath, Chapter $chapter, array $item, array &$idMaps): void
    {
        $data = $item['data'];

        $scene = $chapter->scenes()->create(array_filter([
            'name' => $data['name'],
            'position' => (int) $data['position'],
            // Omit a null status so the database default applies.
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
     * Copies media to a new path or stores a null path when bytes are absent.
     *
     * @param  array{path: string, directory: string, data: array<string, mixed>}  $item
     * @param  array<string, mixed>  $media
     * @param  array<int, string>  $copiedPaths
     */
    private function importCodexMedia(string $dataPath, CodexEntry $entry, array $item, array $media, array &$copiedPaths): void
    {
        $collection = CodexMediaCollection::tryFrom((string) $media['collection'])
            ?? throw ImportValidationException::invalidDescriptorValue($item['path'], 'media.collection');

        $absoluteFile = "{$dataPath}/{$item['directory']}/{$media['file']}";

        $path = null;
        if (is_file($absoluteFile)) {
            $path = $this->codexMediaService->storeImportedFile($absoluteFile);
            $copiedPaths[] = $path;
        }

        $entry->media()->create([
            'collection' => $collection,
            'path' => $path,
            // Derive the filename again instead of trusting archive metadata.
            'original_name' => basename((string) $media['original_name']),
            'mime_type' => (string) $media['mime_type'],
            'size' => (int) $media['size'],
            'position' => (int) $media['position'],
        ]);
    }

    /** Adds a timestamp when the user already has the project name. */
    private function collisionFreeName(string $name, User $user): string
    {
        $taken = $user->projects()->pluck('name')
            ->map(fn (string $existing): string => mb_strtolower($existing))
            ->contains(mb_strtolower($name));

        return $taken
            ? sprintf('%s (imported %s)', $name, now()->format('Y-m-d H:i'))
            : $name;
    }

    /** Returns null for the database default and rejects unknown scene statuses. */
    private function parseSceneStatus(mixed $status, string $descriptorPath): ?SceneStatus
    {
        if ($status === null) {
            return null;
        }

        return SceneStatus::tryFrom((string) $status)
            ?? throw ImportValidationException::invalidDescriptorValue($descriptorPath, 'status');
    }

    /** @return array<int, CodexEntryType> */
    private function parseEntryTypes(mixed $types, string $descriptorPath): array
    {
        return array_map(
            fn (mixed $type): CodexEntryType => CodexEntryType::tryFrom((string) $type)
                ?? throw ImportValidationException::invalidDescriptorValue($descriptorPath, 'applies_to'),
            is_array($types) ? $types : [],
        );
    }

    /** Resolves one source ID and rejects a missing relationship. */
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

    /** @return array<int, int> */
    private function resolveIds(array $idMaps, string $mapKey, mixed $sourceIds, string $descriptorPath, string $jsonKey): array
    {
        return array_map(
            fn (mixed $sourceId): int => $this->resolveId($idMaps, $mapKey, $sourceId, $descriptorPath, $jsonKey),
            is_array($sourceIds) ? $sourceIds : [],
        );
    }

    /** @return array<int, array{path: string, directory: string, data: array<string, mixed>}> */
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

    /** Sanitizes linked rich HTML and returns it with canonical punctuation. A missing key is empty; a missing file is invalid. */
    private function readHtmlField(string $dataPath, string $directory, array $descriptor, string $key = 'description_file'): ?string
    {
        $html = $this->readFieldFile($dataPath, $directory, $descriptor, $key);

        if ($html !== null) {
            return $this->contentSanitizer->assertHtmlAllowed($html);
        }

        return null;
    }

    /** Validates linked Markdown and its rendered HTML, and returns it with canonical punctuation. */
    private function readMarkdownField(string $dataPath, string $directory, array $descriptor, string $key = 'contents_file'): ?string
    {
        $markdown = $this->readFieldFile($dataPath, $directory, $descriptor, $key);

        if ($markdown !== null) {
            return $this->contentSanitizer->assertMarkdownAllowed($markdown);
        }

        return null;
    }

    /** Returns linked raw content, or null when the descriptor omits the key. */
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

    /** @return array<mixed> */
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

    /** @return array<mixed> An absent optional file returns an empty list. */
    private function readJsonIfPresent(string $dataPath, string $relativePath): array
    {
        return is_file("{$dataPath}/{$relativePath}")
            ? $this->readJson($dataPath, $relativePath)
            : [];
    }

    /** Normalizes the extraction root for cross-platform path joins. */
    private function normalizePath(string $dataPath): string
    {
        return rtrim(str_replace('\\', '/', $dataPath), '/');
    }
}
