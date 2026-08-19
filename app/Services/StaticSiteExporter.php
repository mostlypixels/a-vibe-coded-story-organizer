<?php

namespace App\Services;

use App\Enums\CodexMediaCollection;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use App\Models\Event;
use App\Models\Project;
use App\Models\PublicationSetting;
use App\Models\Scene;
use App\Models\Tag;
use App\Models\WordCountSnapshot;
use App\Support\RichText;
use App\Support\StoryNumbering;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Builds a project archive with a lossless data layer and a readable books layer.
 *
 * The data layer stores raw project, story, timeline, codex, and media data. The
 * books layer converts scene Markdown to HTML. Media reads use the public disk
 * and do not require a storage link.
 */
class StaticSiteExporter
{
    /** Bump this version only when the data layout changes incompatibly. */
    private const DATA_VERSION = 4;

    /** Builds a temporary ZIP and removes partial files after a failure. */
    public function export(Project $project, bool $includeMedia): string
    {
        $path = $this->freshTempZipPath();

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Unable to open zip archive at {$path}.");
        }

        try {
            $this->addReadme($zip, $project);
            $this->addManifest($zip, $project, $includeMedia);
            $this->addWordCountSnapshots($zip, $project);
            $this->addProject($zip, $project, $includeMedia);
            $this->addBooks($zip, $project, $includeMedia);
            $this->addTimeline($zip, $project);
            $this->addCodex($zip, $project, $includeMedia);
            $this->addBooksReadingLayer($zip, $project);
        } catch (\Throwable $e) {
            $zip->close();
            if (is_file($path)) {
                unlink($path);
            }

            throw $e;
        }

        $zip->close();

        return $path;
    }

    /** Writes the archive version and media inclusion flag. */
    private function addManifest(ZipArchive $zip, Project $project, bool $includeMedia): void
    {
        $manifest = [
            'version' => self::DATA_VERSION,
            'project_id' => $project->id,
            'exported_at' => now()->toIso8601String(),
            'includes_media' => $includeMedia,
        ];

        $this->addJson($zip, 'data/manifest.json', $manifest);
    }

    /** Writes all word-count snapshots in date order, including an empty list. */
    private function addWordCountSnapshots(ZipArchive $zip, Project $project): void
    {
        $snapshots = $project->wordCountSnapshots()
            ->orderBy('recorded_on')
            ->get(['recorded_on', 'word_count'])
            ->map(fn (WordCountSnapshot $snapshot): array => [
                'recorded_on' => $snapshot->recorded_on->toDateString(),
                'word_count' => $snapshot->word_count,
            ])
            ->all();

        $this->addJson($zip, 'data/word-count-snapshots.json', $snapshots);
    }

    /** Writes a human introduction. The README is not an import source. */
    private function addReadme(ZipArchive $zip, Project $project): void
    {
        $lines = [
            '# '.$project->name,
            '',
            '| Date of export | '.now()->format('Y-m-d').' |',
            '| --- | --- |',
        ];

        // The README uses plain text instead of stored rich HTML.
        $description = RichText::toPlainText($project->description);
        if ($description !== '') {
            $lines[] = '';
            $lines[] = $description;
        }

        $lines[] = '';
        $lines[] = '## What is in this archive';
        $lines[] = '';
        $lines[] = 'This export has two folders. If you are a person who wants to **read** the '
            .'story, open **`books/`** — start at `books/index.html` to pick a book, then its own '
            .'table of contents for clickable chapters. If you are a **program** restoring this '
            .'backup, read **`data/`**: it is a complete, lossless copy of the project — every '
            .'field, id, and relationship — that can be rebuilt exactly. `data/manifest.json` '
            .'describes the archive. The `books/` folder is for reading only and is never the '
            .'source of truth.';

        $this->addFromString($zip, 'README.md', implode("\n", $lines)."\n");
    }

    /** Writes project-owned fields and its optional description and cover. */
    private function addProject(ZipArchive $zip, Project $project, bool $includeMedia): void
    {
        $dir = 'data/project';

        $json = [
            'id' => $project->id,
            'name' => $project->name,
            'daily_word_goal' => $project->daily_word_goal,
            'total_word_goal' => $project->total_word_goal,
        ];
        $json += $this->addFieldFile($zip, $dir, 'description_file', 'description.html', $project->description);
        $json += $this->addCoverFile($zip, $dir, $project->cover_image, $includeMedia);

        $this->addJson($zip, "{$dir}/project.json", $json);
    }

    /** Writes each ordered book and its nested story tree. */
    private function addBooks(ZipArchive $zip, Project $project, bool $includeMedia): void
    {
        // Load the complete ordered tree and references without per-item queries.
        $project->load([
            'books' => fn ($query) => $query->orderBy('position')->chaperone('project'),
            'books.acts' => fn ($query) => $query->orderBy('position'),
            'books.acts.chapters' => fn ($query) => $query->orderBy('position'),
            'books.acts.chapters.scenes' => fn ($query) => $query->orderBy('position'),
            'books.acts.chapters.scenes.mentionedEvents',
            'books.publicationSetting',
        ]);

        foreach ($project->books as $book) {
            $this->addBookData($zip, $book, $includeMedia);
        }
    }

    /**
     * Writes one book's metadata, fields, cover, settings, and story tree.
     *
     * Preserve a null name so it continues to follow the project name. Rights
     * remain plain text; descriptions remain rich HTML.
     */
    private function addBookData(ZipArchive $zip, Book $book, bool $includeMedia): void
    {
        $dir = 'data/books/'.$this->slugDir($book->id, $book->displayName());

        $json = [
            'id' => $book->id,
            'name' => $book->name,
            'position' => $book->position,
            'project_id' => $book->project_id,
            'language' => $book->language?->value,
            'author' => $book->author,
            'publisher' => $book->publisher,
            'isbn' => $book->isbn,
            'overview_render_mode' => $book->overview_render_mode?->value,
        ];
        $json += $this->addFieldFile($zip, $dir, 'description_file', 'description.html', $book->description);
        $json += $this->addFieldFile($zip, $dir, 'rights_file', 'rights.txt', $book->rights);
        $json += $this->addFieldFile($zip, $dir, 'dedication_file', 'dedication.md', $book->dedication);
        $json += $this->addFieldFile($zip, $dir, 'acknowledgements_file', 'acknowledgements.md', $book->acknowledgements);
        $json += $this->addFieldFile($zip, $dir, 'preface_file', 'preface.md', $book->preface);
        $json += $this->addFieldFile($zip, $dir, 'postface_file', 'postface.md', $book->postface);
        $json += $this->addCoverFile($zip, $dir, $book->cover_image, $includeMedia);

        $this->addJson($zip, "{$dir}/book.json", $json);

        $this->addBookPublicationSetting($zip, $dir, $book);

        foreach ($book->acts as $act) {
            $actDir = "{$dir}/acts/".$this->entityDir($act);

            $actJson = [
                'id' => $act->id,
                'name' => $act->name,
                'position' => $act->position,
                'book_id' => $act->book_id,
            ];
            $actJson += $this->addFieldFile($zip, $actDir, 'description_file', 'description.html', $act->description);
            $this->addJson($zip, "{$actDir}/act.json", $actJson);

            foreach ($act->chapters as $chapter) {
                $chapterDir = "{$actDir}/chapters/".$this->entityDir($chapter);

                $chapterJson = [
                    'id' => $chapter->id,
                    'name' => $chapter->name,
                    'position' => $chapter->position,
                    'act_id' => $chapter->act_id,
                ];
                $chapterJson += $this->addFieldFile($zip, $chapterDir, 'description_file', 'description.html', $chapter->description);
                $chapterJson += $this->addCoverFile($zip, $chapterDir, $chapter->cover_image, $includeMedia);
                $this->addJson($zip, "{$chapterDir}/chapter.json", $chapterJson);

                foreach ($chapter->scenes as $scene) {
                    $this->addScene($zip, $chapterDir, $scene);
                }
            }
        }
    }

    /** Writes raw saved publication settings and omits unsaved defaults. */
    private function addBookPublicationSetting(ZipArchive $zip, string $bookDir, Book $book): void
    {
        $setting = $book->publicationSetting;

        if ($setting === null) {
            return;
        }

        $this->addJson($zip, "{$bookDir}/publication-setting.json", [
            'include_book_cover' => $setting->include_book_cover,
            'include_chapter_covers' => $setting->include_chapter_covers,
            'include_scene_titles' => $setting->include_scene_titles,
            'include_act_descriptions' => $setting->include_act_descriptions,
            'include_chapter_descriptions' => $setting->include_chapter_descriptions,
            'include_scene_descriptions' => $setting->include_scene_descriptions,
            'include_dedication' => $setting->include_dedication,
            'include_acknowledgements' => $setting->include_acknowledgements,
            'include_preface' => $setting->include_preface,
            'include_postface' => $setting->include_postface,
            'include_author' => $setting->include_author,
            'include_publisher' => $setting->include_publisher,
            'include_rights' => $setting->include_rights,
            'include_isbn' => $setting->include_isbn,
            'chapter_title_format' => $setting->chapter_title_format->value,
            'table_of_contents_depth' => $setting->table_of_contents_depth->value,
            'divider_type' => $setting->divider_type->value,
            'section_order' => $setting->section_order,
            'include_codex_appendix' => $setting->include_codex_appendix,
            'appendix_entry_types' => $setting->appendix_entry_types,
            'appendix_include_images' => $setting->appendix_include_images,
        ]);
    }

    /** Writes one scene without its private sharing credentials. */
    private function addScene(ZipArchive $zip, string $chapterDir, Scene $scene): void
    {
        $dir = "{$chapterDir}/scenes/".$this->entityDir($scene);

        $json = [
            'id' => $scene->id,
            'name' => $scene->name,
            'position' => $scene->position,
            'status' => $scene->status?->value,
            'chapter_id' => $scene->chapter_id,
            'event_id' => $scene->event_id,
            'mentioned_event_ids' => $scene->mentionedEvents->pluck('id')->all(),
        ];
        $json += $this->addFieldFile($zip, $dir, 'contents_file', 'contents.md', $scene->contents);
        $json += $this->addFieldFile($zip, $dir, 'description_file', 'description.html', $scene->description);
        $json += $this->addFieldFile($zip, $dir, 'notes_file', 'notes.html', $scene->notes);

        $this->addJson($zip, "{$dir}/scene.json", $json);
    }

    /**
     * Writes a safe relative cover link and optionally copies its bytes.
     *
     * @return array<string, string>
     */
    private function addCoverFile(ZipArchive $zip, string $dir, ?string $coverImage, bool $includeMedia): array
    {
        if (blank($coverImage)) {
            return [];
        }

        $relativePath = 'cover/'.basename($coverImage);

        if ($includeMedia) {
            // Keep the link but do not fail when the source file is missing.
            $bytes = Storage::disk('public')->get($coverImage);
            if ($bytes !== null) {
                $this->addFromString($zip, "{$dir}/{$relativePath}", $bytes);
            }
        }

        return ['cover_file' => $relativePath];
    }

    /** Writes all timeline records, including the main plotline and bookend events. */
    private function addTimeline(ZipArchive $zip, Project $project): void
    {
        // Stable ordering makes archive output repeatable. Eager loading prevents extra queries.
        $project->load([
            'plotlines' => fn ($query) => $query->orderBy('name'),
            'events' => fn ($query) => $query->orderBy('event_datetime')->orderBy('id'),
            'events.plotlines',
        ]);

        foreach ($project->plotlines as $plotline) {
            $dir = 'data/timeline/plotlines/'.$this->entityDir($plotline);

            $json = [
                'id' => $plotline->id,
                'name' => $plotline->name,
                'color' => $plotline->color,
                'is_main' => $plotline->is_main,
                'project_id' => $plotline->project_id,
            ];
            $json += $this->addFieldFile($zip, $dir, 'description_file', 'description.html', $plotline->description);

            $this->addJson($zip, "{$dir}/plotline.json", $json);
        }

        foreach ($project->events as $event) {
            $this->addEvent($zip, $event);
        }
    }

    /** Writes one event, its plotline IDs, and its rich description. */
    private function addEvent(ZipArchive $zip, Event $event): void
    {
        $dir = 'data/timeline/events/'.$this->slugDir($event->id, $event->title);

        $json = [
            'id' => $event->id,
            'title' => $event->title,
            'event_datetime' => $event->event_datetime?->toIso8601String(),
            'is_fixed' => $event->is_fixed,
            'project_id' => $event->project_id,
            'plotline_ids' => $event->plotlines->pluck('id')->all(),
        ];
        $json += $this->addFieldFile($zip, $dir, 'description_file', 'description.html', $event->description);

        $this->addJson($zip, "{$dir}/event.json", $json);
    }

    /** Writes codex definitions, tags, entries, and optional media bytes. */
    private function addCodex(ZipArchive $zip, Project $project, bool $includeMedia): void
    {
        $this->addCodexAttributes($zip, $project);
        $this->addTags($zip, $project);

        // Load all entry relations and use stable output ordering.
        $project->load([
            'codexEntries' => fn ($query) => $query->orderBy('id'),
            'codexEntries.aliases' => fn ($query) => $query->orderBy('id'),
            'codexEntries.tags' => fn ($query) => $query->orderBy('id'),
            'codexEntries.attributeValues' => fn ($query) => $query
                ->orderBy('codex_attribute_id')->orderBy('start_event_id')->orderBy('id'),
            'codexEntries.media' => fn ($query) => $query->orderBy('collection')->orderBy('position'),
        ]);

        foreach ($project->codexEntries as $entry) {
            $this->addCodexEntry($zip, $entry, $includeMedia);
        }
    }

    /** Writes ordered attribute definitions with enum backing values. */
    private function addCodexAttributes(ZipArchive $zip, Project $project): void
    {
        $attributes = $project->codexAttributes()->orderBy('position')->get()
            ->map(fn (CodexAttribute $attribute) => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'applies_to' => $attribute->applies_to
                    ? $attribute->applies_to->map(fn ($type) => $type->value)->values()->all()
                    : [],
                'position' => $attribute->position,
            ])->all();

        $this->addJson($zip, 'data/codex/attributes.json', $attributes);
    }

    /** Writes the tags referenced by entry tag IDs. */
    private function addTags(ZipArchive $zip, Project $project): void
    {
        $tags = $project->tags()->orderBy('id')->get()
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])->all();

        $this->addJson($zip, 'data/tags.json', $tags);
    }

    /**
     * Writes one entry, including event-anchored values and a media manifest.
     * Media metadata remains present when media bytes are excluded.
     */
    private function addCodexEntry(ZipArchive $zip, CodexEntry $entry, bool $includeMedia): void
    {
        $dir = 'data/codex/'.$entry->type->value.'/'.$this->entityDir($entry);

        $json = [
            'id' => $entry->id,
            'name' => $entry->name,
            'type' => $entry->type->value,
            'project_id' => $entry->project_id,
            'aliases' => $entry->aliases->pluck('alias')->values()->all(),
            'tag_ids' => $entry->tags->pluck('id')->values()->all(),
            'attribute_values' => $entry->attributeValues->map(fn ($value) => [
                'id' => $value->id,
                'attribute_id' => $value->codex_attribute_id,
                'start_event_id' => $value->start_event_id,
                'value' => $value->value,
            ])->all(),
            'media' => $this->addCodexMedia($zip, $dir, $entry, $includeMedia),
        ];
        $json += $this->addFieldFile($zip, $dir, 'description_file', 'description.html', $entry->description);

        $this->addJson($zip, "{$dir}/entry.json", $json);
    }

    /**
     * Builds the media manifest and optionally copies bytes from the public disk.
     *
     * @return array<int, array<string, mixed>>
     */
    private function addCodexMedia(ZipArchive $zip, string $dir, CodexEntry $entry, bool $includeMedia): array
    {
        $manifest = [];

        foreach ($entry->media as $media) {
            $relativePath = $this->mediaFilePath($media);

            $manifest[] = [
                'id' => $media->id,
                'collection' => $media->collection->value,
                'position' => $media->position,
                'original_name' => $media->original_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'file' => $relativePath,
            ];

            if ($includeMedia) {
                // Keep the metadata but do not fail when the source file is missing.
                $bytes = Storage::disk('public')->get($media->path);
                if ($bytes !== null) {
                    $this->addFromString($zip, "{$dir}/{$relativePath}", $bytes);
                }
            }
        }

        return $manifest;
    }

    /** Builds a safe, collection-scoped media path without filename collisions. */
    private function mediaFilePath(CodexMedia $media): string
    {
        $name = basename($media->original_name);

        return match ($media->collection) {
            CodexMediaCollection::Cover => "cover/{$name}",
            CodexMediaCollection::ReferenceImage => sprintf('reference-images/%02d-%s', $media->position, $name),
            CodexMediaCollection::ReferenceFile => sprintf('reference-files/%02d-%s', $media->position, $name),
        };
    }

    /** Writes the human reading layer in book order. */
    private function addBooksReadingLayer(ZipArchive $zip, Project $project): void
    {
        $books = $project->books()->orderBy('position')->get();

        $this->addBooksReadingIndex($zip, $project, $books);

        foreach ($books as $book) {
            $this->addBookReadingLayer($zip, $book);
        }
    }

    /** @param Collection<int, Book> $books */
    private function addBooksReadingIndex(ZipArchive $zip, Project $project, Collection $books): void
    {
        $entries = $books->map(fn (Book $book) => [
            'title' => $book->displayName(),
            'href' => $this->bookFolder($book).'/index.html',
        ])->all();

        $html = view('exports.books.books-index', [
            'projectName' => $project->name,
            'books' => $entries,
        ])->render();

        $this->addFromString($zip, 'books/index.html', $html);
    }

    /**
     * Writes one book's contents page and compiled chapter pages.
     *
     * One numbering map and title format serve both contents and headings.
     * Previous and next links never leave the book.
     *
     * > [!WARNING]
     * > File paths use stored positions, not display numbers.
     */
    private function addBookReadingLayer(ZipArchive $zip, Book $book): void
    {
        $acts = $this->loadActTree($book);
        $settings = $book->publicationSettingOrDefault();
        $numbering = StoryNumbering::fromActs($acts);
        $folder = $this->bookFolder($book);

        // Share one ordered chapter list with the contents and reading links.
        $sequence = [];
        foreach ($acts as $act) {
            foreach ($act->chapters as $chapter) {
                $sequence[] = ['act' => $act, 'chapter' => $chapter];
            }
        }

        $this->addBookReadingIndex($zip, $book, $folder, $acts, $settings, $numbering);

        $lastIndex = count($sequence) - 1;
        foreach ($sequence as $index => $item) {
            $act = $item['act'];
            $chapter = $item['chapter'];

            $previous = $index > 0
                ? '../'.$this->chapterHref($sequence[$index - 1]['act'], $sequence[$index - 1]['chapter'])
                : '../index.html';
            $next = $index < $lastIndex
                ? '../'.$this->chapterHref($sequence[$index + 1]['act'], $sequence[$index + 1]['chapter'])
                : '../index.html';

            $html = view('exports.books.chapter', [
                // Use the same formatted heading as the contents entry.
                'chapterTitle' => $settings->chapter_title_format->format($numbering->chapter($chapter), $chapter->name),
                // Use the shared scene renderer so the app and archive match.
                'renderedScenes' => $chapter->scenes->map(
                    fn (Scene $scene): string => $scene->renderedContents
                )->all(),
                'prevHref' => $previous,
                'nextHref' => $next,
            ])->render();

            $this->addFromString($zip, "books/{$folder}/".$this->chapterHref($act, $chapter), $html);
        }
    }

    /** @param Collection<int, Act> $acts */
    private function addBookReadingIndex(ZipArchive $zip, Book $book, string $folder, Collection $acts, PublicationSetting $settings, StoryNumbering $numbering): void
    {
        $toc = [];
        foreach ($acts as $act) {
            $chapters = [];
            foreach ($act->chapters as $chapter) {
                $chapters[] = [
                    'title' => $this->chapterTocTitle($chapter, $settings, $numbering),
                    'href' => $this->chapterHref($act, $chapter),
                ];
            }
            $toc[] = ['title' => $this->actTocTitle($act, $numbering), 'chapters' => $chapters];
        }

        $html = view('exports.books.index', [
            'bookName' => $book->displayName(),
            'toc' => $toc,
        ])->render();

        $this->addFromString($zip, "books/{$folder}/index.html", $html);
    }

    /** Builds an act label with its gap-free story number and optional name. */
    private function actTocTitle(Act $act, StoryNumbering $numbering): string
    {
        $number = $numbering->act($act);

        return filled($act->name)
            ? "Act {$number}: {$act->name}"
            : "Act {$number}";
    }

    /** Uses the configured title format and prevents blank contents labels. */
    private function chapterTocTitle(Chapter $chapter, PublicationSetting $settings, StoryNumbering $numbering): string
    {
        $number = $numbering->chapter($chapter);
        $label = $settings->chapter_title_format->format($number, $chapter->name);

        return $label !== '' ? $label : "Chapter {$number}";
    }

    /** Builds the shared chapter path from stored act and chapter positions. */
    private function chapterHref(Act $act, Chapter $chapter): string
    {
        return sprintf('%02d/%02d.html', $act->position, $chapter->position);
    }

    /** Returns a book's zero-padded reading-layer folder. */
    private function bookFolder(Book $book): string
    {
        return sprintf('%02d', $book->position);
    }

    /** @return Collection<int, Act> The ordered tree needed by the reading layer. */
    private function loadActTree(Book $book): Collection
    {
        return $book->acts()
            ->with([
                'chapters' => fn ($query) => $query->orderBy('position'),
                'chapters.scenes' => fn ($query) => $query->orderBy('position'),
            ])
            ->orderBy('position')
            ->get();
    }

    /** Builds an entity directory from its stable ID and cosmetic slug. */
    private function entityDir(Model $model): string
    {
        return $this->slugDir($model->id, $model->name);
    }

    /** Builds a directory from an explicit ID and display name. */
    private function slugDir(int $id, string $name): string
    {
        return sprintf('%d-%s', $id, $this->slug($name));
    }

    /**
     * Writes a raw stored field and returns its link. Empty fields write neither.
     *
     * @return array<string, string>
     */
    private function addFieldFile(ZipArchive $zip, string $dir, string $linkKey, string $filename, ?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $this->addFromString($zip, "{$dir}/{$filename}", $value);

        return [$linkKey => $filename];
    }

    /** @param array<string, mixed> $data */
    private function addJson(ZipArchive $zip, string $path, array $data): void
    {
        $this->addFromString(
            $zip,
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /** Creates a unique path for concurrent exports. */
    private function freshTempZipPath(): string
    {
        $directory = config('exports.temp_path');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.DIRECTORY_SEPARATOR.Str::uuid().'.zip';
    }

    /** Returns a cosmetic slug or `untitled` when no slug remains. */
    private function slug(string $name): string
    {
        $slug = Str::slug($name);

        return $slug !== '' ? $slug : 'untitled';
    }

    /** Throws when the ZIP library cannot add an entry. */
    private function addFromString(ZipArchive $zip, string $path, string $contents): void
    {
        if ($zip->addFromString($path, $contents) !== true) {
            throw new RuntimeException("Unable to add {$path} to the export archive.");
        }
    }
}
