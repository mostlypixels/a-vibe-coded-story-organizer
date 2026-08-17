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
 * Builds a downloadable .zip export of one project.
 *
 * HTTP-agnostic and async-ready: it takes a Project plus options
 * and returns a finished zip path on disk — no Request/Response dependency — so a
 * future queued Job can reuse it unchanged. Media bytes are read off the `public`
 * disk via Storage, never the /storage URL, so it never depends on
 * `php artisan storage:link` or any CLI step.
 *
 * The archive has two top-level folders:
 *   - data/  — a lossless machine layer (source of truth for a future reimport)
 *   - books/ — a human reading version
 *
 * It emits data/manifest.json, the project's own data/project/ descriptor, the
 * data/books/ branch (one directory per book, each carrying its own publication
 * metadata and its act -> chapter -> scene tree), the data/ Timeline branch
 * (plotlines + events, project-wide), the data/ Codex branch (entries + attribute
 * definitions + tags + media, project-wide), and the books/ human reading layer
 * (a top-level index linking every book's own TOC + compiled chapter pages). The
 * books/ layer is the ONLY place Markdown is rendered to HTML; data/ stays raw
 * and lossless.
 */
class StaticSiteExporter
{
    /**
     * The data-format version written into data/manifest.json. A future import
     * reads this to decide how to interpret the archive; bump it only on a
     * breaking change to the data/ layout. Documented in
     * documentation/export-format.md.
     *
     * The multiple-books feature bumped it to 4: the manuscript moved from
     * data/acts/ (project-scoped) to data/books/<id>/acts/ (one directory per
     * book), publication-setting.json moved inside each book's own directory,
     * and the book/ reading layer gained a books/ level above it. A renamed or
     * relocated path is a breaking layout change, so
     * `ImportRules::SUPPORTED_MANIFEST_VERSIONS` accepts version 4 only —
     * older archives are rejected rather than imported against the wrong shape.
     */
    private const DATA_VERSION = 4;

    /**
     * Build the export and return the path to a ready temp zip. The caller (the
     * controller) streams it and deletes it after send. The temp file is removed
     * here too if the build throws, so no orphaned zips are left behind.
     */
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
            // Abandon the half-built archive and delete the temp file so a failed
            // export never leaks a partial zip onto disk.
            $zip->close();
            if (is_file($path)) {
                unlink($path);
            }

            throw $e;
        }

        $zip->close();

        return $path;
    }

    /**
     * data/manifest.json — the archive's root descriptor. `includes_media`
     * reflects the "Include images & files" toggle; media BYTES are governed by
     * it, but the manifest records the choice regardless.
     */
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

    /**
     * data/word-count-snapshots.json — the project's writing history, as a flat
     * list of `{ recorded_on, word_count }` (oldest first), so a restore carries
     * the record forward instead of leaving a gap. Written like `data/tags.json`
     * — always present, even as an empty array, never omitted for "no rows yet".
     * An archive exported before this feature has no such file; the importer
     * reads that as "no history", not an error.
     */
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

    /**
     * README.md — the archive's front door for whoever opens the zip. It carries the
     * project name, the export date, the project description as plain text (the stored
     * HTML stripped to prose), and a short note pointing humans at books/ and machines
     * at data/. It renders nothing from data/ and is never a source of truth.
     */
    private function addReadme(ZipArchive $zip, Project $project): void
    {
        $lines = [
            '# '.$project->name,
            '',
            '| Date of export | '.now()->format('Y-m-d').' |',
            '| --- | --- |',
        ];

        // The description is a stored sanitized HTML fragment; the README wants prose,
        // so strip it to plain text. Skip the block entirely when there is no description.
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

    /**
     * data/project/ — project.json (id, name, goals, description_file?, cover_file?)
     * plus description.html and cover/<name>. Slimmed to the project's OWN columns:
     * language, author, publisher, rights, isbn and the four front-/back-matter
     * fields now belong to each book (see {@see addBookData()}) and are never
     * written here.
     */
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

    /**
     * The data/books/ branch: one directory per book, in position order, each
     * carrying its own publication metadata (see {@see addBookData()}) and its
     * act -> chapter -> scene tree. Nesting mirrors ownership, the same rule the
     * act/chapter/scene tree below it already follows.
     */
    private function addBooks(ZipArchive $zip, Project $project, bool $includeMedia): void
    {
        // Eager-load the whole books -> acts -> chapters -> scenes tree once,
        // ordered by position at every level (the app-wide invariant that also
        // drives books/ numbering) — no N+1. The scene's mentioned events are
        // loaded so we can emit their ids without a per-scene query. chaperone()
        // wires each book's inverse project relation so displayName() on an
        // unnamed book (the common case) costs no extra query.
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
     * A single book's data/ directory: book.json (scalars + field-file links)
     * plus its own description.html, rights.txt, the four front-/back-matter
     * Markdown files, its cover, its publication-setting.json, and its
     * act -> chapter -> scene tree underneath.
     *
     * `name` is written LITERALLY, including null — an unnamed book tracks its
     * project's name ({@see Book::displayName()}), and coercing it to a string
     * here would materialize a value that should keep drifting with the project.
     * `rights` is a plain-text column (not rich HTML like `description`), so it
     * is written as `rights.txt`, never `.html` — the same field-file convention
     * as `contents.md`, just under a `.txt` name.
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

    /**
     * {bookDir}/publication-setting.json — this book's EPUB publication config,
     * so a customised setting survives an export -> import round-trip. Moved off
     * the data/ root now that {@see PublicationSetting} belongs to a Book, not
     * the Project.
     *
     * RAW persisted values only, never rendered: booleans as booleans, enum
     * columns as their backing string, the two ordered lists as arrays.
     *
     * The file is OMITTED entirely when the book has no saved row — the lazy
     * default ({@see Book::publicationSettingOrDefault()}) means "no row"
     * already equals "defaults", so writing a serialized default would add
     * nothing. An import that finds no file therefore leaves the imported book
     * on the same lazy default.
     */
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

    /**
     * A single scene directory: scene.json (scalars + mentioned-event ids + field-file
     * links) plus contents.md / description.html / notes.html. `status` is written as
     * the SceneStatus enum VALUE (machine form, e.g. "draft"). The share-link columns
     * (share_token, share_expires_at) are deliberately excluded: they are
     * deployment secrets.
     */
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
     * A plain-path cover image belonging to a project, a book, or a chapter.
     * Returns the `cover_file` link key to merge into the owning entity's JSON
     * (a path relative to that entity's own directory, `cover/<name>`) when a
     * cover is set, and — when $includeMedia is true — co-locates the file's
     * BYTES at that path, exactly like codex media.
     *
     * The bytes are read straight off the `public` disk, never via the
     * /storage URL, so the export never depends on `php artisan storage:link`.
     * The link is written regardless of the toggle; only the bytes are
     * conditional, and a metadata-only export imports back a null cover.
     * basename-guarded so a stray path component can never escape the entity's
     * own directory.
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
            // A missing file on disk is skipped rather than aborting the whole
            // export; the cover_file link still records that a cover was set.
            $bytes = Storage::disk('public')->get($coverImage);
            if ($bytes !== null) {
                $this->addFromString($zip, "{$dir}/{$relativePath}", $bytes);
            }
        }

        return ['cover_file' => $relativePath];
    }

    /**
     * The Timeline branch of data/: every plotline and event in the project, grouped
     * by type under data/timeline/. Each is a `<db-id>-slug` directory with a
     * per-entity JSON (scalars + stable id + relationship id lists + field-file links)
     * and a raw description.html fragment.
     *
     * The auto-created anchors are exported like any other row: the is_main main
     * plotline and the is_fixed Start/End bookend events. They are part of the graph
     * (Codex attribute values often anchor to the Start bookend). Matching —
     * not duplicating — them on a future import is an import-time concern, not handled
     * here (see documentation/export-format.md → Timeline).
     */
    private function addTimeline(ZipArchive $zip, Project $project): void
    {
        // Deterministic file iteration: plotlines by name (no position column exists),
        // events by (event_datetime, id) — the same canonical tie-break the bookend
        // resolution uses. Ordering only affects iteration, never identity (the DB id).
        // events.plotlines is eager-loaded so plotline_ids needs no per-event query.
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

    /**
     * A single event directory: event.json (scalars + plotline id list + field-file
     * link) plus description.html. Events have no `name` column — the directory slug
     * uses `title`. `event_datetime` is a datetime cast, serialized as a stable
     * ISO-8601 string; `is_fixed` marks the Start/End bookends. `plotline_ids` comes
     * from the event_plotline pivot.
     */
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

    /**
     * The Codex branch of data/ — the richest branch. Emits the project's flat
     * attribute-definition and tag lists, then one directory per Codex entry grouped
     * by type (data/codex/<type>/<id>-slug/). The "Include images & files" toggle
     * ($includeMedia) governs whether media BYTES are copied; the media[] metadata in
     * each entry.json is written REGARDLESS.
     */
    private function addCodex(ZipArchive $zip, Project $project, bool $includeMedia): void
    {
        $this->addCodexAttributes($zip, $project);
        $this->addTags($zip, $project);

        // Eager-load every relationship an entry renders so no per-entry query runs.
        // Entries are grouped by type in the directory path; iteration order is by id
        // (they have no position column) and only affects file write order, not identity.
        // Attribute values are ordered (attribute, start event, id) for a stable file.
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

    /**
     * data/codex/attributes.json — the project's attribute DEFINITIONS as a flat array
     * (no directory, no rich fields), ordered by position (the app-wide invariant).
     * `applies_to` is the list of CodexEntryType enum VALUES the attribute applies to.
     */
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

    /**
     * data/tags.json — the project's tags as a flat array of { id, name } (no rich
     * fields → no directories). Entry.json's `tag_ids` reference these by stable id.
     */
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
     * A single Codex entry directory: entry.json (scalars + aliases + tag ids +
     * attribute values + media manifest + field-file link) plus its raw description.html
     * and — when $includeMedia is true — its media bytes co-located in the entry dir.
     *
     * `attribute_values` is the crucial attribute-over-time link: each row anchors an
     * attribute's value to a START EVENT, so it is emitted as
     * { id, attribute_id, start_event_id, value } (referenced records live in the Codex
     * attributes list and the Timeline events branch).
     *
     * `media[]` IS the manifest (there is deliberately no separate images/manifest.json).
     * It is written whether or not bytes are copied; only the bytes are toggle-governed.
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
     * Build the entry's media[] manifest and, when $includeMedia is true, copy each
     * file's BYTES into the entry directory at its `file` path (grouped by collection:
     * cover/, reference-images/, reference-files/). Bytes are read straight off the
     * `public` disk — never via the /storage URL — so the export never
     * depends on `php artisan storage:link`. The returned manifest is written regardless
     * of the toggle; only the bytes are conditional.
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
                // A missing file on disk is skipped rather than aborting the whole
                // export; its metadata still records that the media existed.
                $bytes = Storage::disk('public')->get($media->path);
                if ($bytes !== null) {
                    $this->addFromString($zip, "{$dir}/{$relativePath}", $bytes);
                }
            }
        }

        return $manifest;
    }

    /**
     * The media file's relative path inside the entry directory, grouped by collection.
     * The single cover keeps its original name (`cover/portrait.jpg`); the multi-item
     * reference collections prefix a zero-padded position so two files sharing an
     * original name never collide (`reference-images/01-sketch.png`). original_name is
     * basename-guarded so a stray path component can never escape the entry directory.
     */
    private function mediaFilePath(CodexMedia $media): string
    {
        $name = basename($media->original_name);

        return match ($media->collection) {
            CodexMediaCollection::Cover => "cover/{$name}",
            CodexMediaCollection::ReferenceImage => sprintf('reference-images/%02d-%s', $media->position, $name),
            CodexMediaCollection::ReferenceFile => sprintf('reference-files/%02d-%s', $media->position, $name),
        };
    }

    /**
     * The books/ human reading layer — books/index.html links every book's own
     * table of contents (in position order), and each book gets its own
     * books/NN/ folder ({@see addBookReadingLayer()}), NN being the book's
     * zero-padded position.
     */
    private function addBooksReadingLayer(ZipArchive $zip, Project $project): void
    {
        $books = $project->books()->orderBy('position')->get();

        $this->addBooksReadingIndex($zip, $project, $books);

        foreach ($books as $book) {
            $this->addBookReadingLayer($zip, $book);
        }
    }

    /**
     * books/index.html — the top-level index linking every book's own table of
     * contents at books/NN/index.html. Titles use {@see Book::displayName()} so
     * an unnamed book still gets a real label (the project's name) rather than
     * a blank link.
     *
     * @param  Collection<int, Book>  $books
     */
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
     * One book's reading layer: its own table of contents (books/NN/index.html)
     * plus a compiled page per chapter (books/NN/NN/NN.html) — the same shape
     * the whole project's book/ layer used to have, now written once per book.
     * Unlike data/, this is the ONE place the export renders Markdown to HTML
     * (Str::markdown on scene `contents`, in the Blade templates).
     *
     * Act and chapter numbers are derived once from this book's own loaded tree
     * via {@see StoryNumbering}, then threaded through both the TOC and the
     * chapter pages, so the two can never disagree. The chapter heading is
     * formatted by the book's {@see PublicationSetting::$chapter_title_format}
     * — the same setting that drives the EPUB — so both exports agree on how a
     * chapter number reads.
     *
     * prev/next reading links stay INSIDE this book: the first chapter's prev
     * and the last chapter's next both point at `../index.html`, this book's
     * own TOC, never a sibling book.
     *
     * > [!WARNING]
     * > `chapterHref()` must not use the display numbers above. It is file
     * > identity (folder = act position, file = per-act chapter position),
     * > relative to this book's own TOC, and must never shift the URL of an
     * > already exported book.
     */
    private function addBookReadingLayer(ZipArchive $zip, Book $book): void
    {
        $acts = $this->loadActTree($book);
        $settings = $book->publicationSettingOrDefault();
        $numbering = StoryNumbering::fromActs($acts);
        $folder = $this->bookFolder($book);

        // A flat, ordered chapter sequence (all chapters across all acts of THIS
        // book, in reading order) built once so both the TOC and prev/next
        // navigation share it.
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

            // Chapter pages live one level below this book's index.html
            // (books/NN/NN/NN.html), so prev/next reach a sibling chapter via
            // ../NN/NN.html (crossing act folders when needed) and this book's
            // own TOC via ../index.html at the ends.
            $previous = $index > 0
                ? '../'.$this->chapterHref($sequence[$index - 1]['act'], $sequence[$index - 1]['chapter'])
                : '../index.html';
            $next = $index < $lastIndex
                ? '../'.$this->chapterHref($sequence[$index + 1]['act'], $sequence[$index + 1]['chapter'])
                : '../index.html';

            $html = view('exports.books.chapter', [
                // Same formatted heading as the TOC entry, built from this
                // book's chapter number, never $chapter->position.
                'chapterTitle' => $settings->chapter_title_format->format($numbering->chapter($chapter), $chapter->name),
                // Render Markdown → HTML through the same Scene::renderedContents
                // accessor the app's views use, so the reading layer and the app can
                // never drift apart on how scene contents are rendered.
                'renderedScenes' => $chapter->scenes->map(
                    fn (Scene $scene): string => $scene->renderedContents
                )->all(),
                'prevHref' => $previous,
                'nextHref' => $next,
            ])->render();

            $this->addFromString($zip, "books/{$folder}/".$this->chapterHref($act, $chapter), $html);
        }
    }

    /**
     * books/NN/index.html — one book's own table of contents. Builds a plain
     * data structure (act title + its chapter titles and hrefs, in position
     * order) so the Blade template stays presentation-only; titles are
     * HTML-escaped by Blade's {{ }}.
     *
     * @param  Collection<int, Act>  $acts
     */
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

    /**
     * The TOC label for an Act: "Act {number}: {name}", or just "Act {number}"
     * when the Act has no name. `number` is the book-wide, gap-free rank from
     * {@see StoryNumbering} — never `$act->position`, which is a per-book but
     * gappy sort key. Mirrors {@see EpubExporter}'s own act nav label so both
     * exports agree on how an act number reads.
     */
    private function actTocTitle(Act $act, StoryNumbering $numbering): string
    {
        $number = $numbering->act($act);

        return filled($act->name)
            ? "Act {$number}: {$act->name}"
            : "Act {$number}";
    }

    /**
     * The TOC label for a Chapter, formatted by the book's configured
     * {@see ChapterTitleFormat} — the same setting/value used for the chapter
     * page's own heading, so the two can never drift. Falls back to
     * "Chapter {number}" when the format is "Title" and the chapter has no name
     * (the "Title" format on its own would otherwise render a blank listing row;
     * see {@see EpubExporter::chapterNavTitle()} for the same reasoning).
     */
    private function chapterTocTitle(Chapter $chapter, PublicationSetting $settings, StoryNumbering $numbering): string
    {
        $number = $numbering->chapter($chapter);
        $label = $settings->chapter_title_format->format($number, $chapter->name);

        return $label !== '' ? $label : "Chapter {$number}";
    }

    /**
     * A chapter's path relative to its book's own folder: NN/NN.html — folder =
     * zero-padded ACT position, file = zero-padded PER-ACT chapter position.
     * Single source of truth for the TOC links, the prev/next links, and the
     * written zip entry, so they can never drift apart.
     */
    private function chapterHref(Act $act, Chapter $chapter): string
    {
        return sprintf('%02d/%02d.html', $act->position, $chapter->position);
    }

    /**
     * The zero-padded folder segment for one book's reading layer
     * (books/NN/...), derived from the book's own position among its
     * project's books.
     */
    private function bookFolder(Book $book): string
    {
        return sprintf('%02d', $book->position);
    }

    /**
     * Load one book's act → chapter → scene tree for the books/ layer, ordered
     * by position at every level (the app-wide invariant that drives books/
     * numbering and reading order). Scenes carry only `contents` here — the
     * reading layer needs nothing else. Reloaded independently of the data/
     * books branch so the two layers never couple.
     *
     * @return Collection<int, Act>
     */
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

    /**
     * The `<db-id>-slug` directory name for an entity. The id is the stable
     * identity; the slug is cosmetic. Reused by every data/ branch whose
     * display column is `name` and can never be null — a Book's slug is built
     * directly off {@see Book::displayName()} instead (see {@see addBookData()}).
     */
    private function entityDir(Model $model): string
    {
        return $this->slugDir($model->id, $model->name);
    }

    /**
     * Build a `<id>-slug` directory segment from an explicit id + display name. Used
     * directly for entities whose display column is not `name` (e.g. an event's
     * `title`), or whose name may be null (a Book); entityDir() delegates here for
     * the common name-based case.
     */
    private function slugDir(int $id, string $name): string
    {
        return sprintf('%d-%s', $id, $this->slug($name));
    }

    /**
     * Write a raw field file (the EXACT stored column value — never re-rendered or
     * re-sanitized) and return the `*_file` link key to merge into the
     * entity JSON. A null/empty value writes nothing and returns no key, keeping the
     * "omit both the file and its link" null-handling rule consistent across every
     * entity.
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

    /**
     * Encode an array as pretty JSON and add it as a zip entry. Slashes and unicode
     * stay unescaped so the raw ids/paths read cleanly in the archive.
     *
     * @param  array<string, mixed>  $data
     */
    private function addJson(ZipArchive $zip, string $path, array $data): void
    {
        $this->addFromString(
            $zip,
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * A fresh, collision-free temp path in the `exports.temp_path` directory
     * (created on demand). Each export gets its own uuid-named file so
     * concurrent exports never clobber one another.
     */
    private function freshTempZipPath(): string
    {
        $directory = config('exports.temp_path');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.DIRECTORY_SEPARATOR.Str::uuid().'.zip';
    }

    /**
     * Slug a name for a cosmetic directory/file segment, falling back to
     * 'untitled' when the name slugs to empty (e.g. a name of only punctuation).
     * {@see self::slugDir()} is the only caller: it builds every entity directory
     * name from this.
     */
    private function slug(string $name): string
    {
        $slug = Str::slug($name);

        return $slug !== '' ? $slug : 'untitled';
    }

    /**
     * Add a string as a zip entry, guarding against a silent write failure.
     * Reused by every branch that writes JSON or raw field files.
     */
    private function addFromString(ZipArchive $zip, string $path, string $contents): void
    {
        if ($zip->addFromString($path, $contents) !== true) {
            throw new RuntimeException("Unable to add {$path} to the export archive.");
        }
    }
}
