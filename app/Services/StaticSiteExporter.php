<?php

namespace App\Services;

use App\Enums\CodexMediaCollection;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use App\Models\Event;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Tag;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Builds a downloadable .zip export of one project.
 *
 * HTTP-agnostic and async-ready (invariant 5): it takes a Project plus options
 * and returns a finished zip path on disk — no Request/Response dependency — so a
 * future queued Job can reuse it unchanged. Media bytes are read off the `public`
 * disk via Storage, never the /storage URL, so it never depends on
 * `php artisan storage:link` or any CLI step.
 *
 * The archive has two top-level folders (see plan/00-overview.md):
 *   - data/  — a lossless machine layer (source of truth for a future reimport)
 *   - book/  — a human reading version
 *
 * It emits data/manifest.json, the data/ Story branch (project + acts → chapters →
 * scenes), the data/ Timeline branch (plotlines + events), the data/ Codex branch
 * (entries + attribute definitions + tags + media), and the book/ human reading
 * layer (TOC + compiled chapter pages). The book/ layer is the ONLY place Markdown
 * is rendered to HTML; data/ stays raw and lossless (invariant 3).
 */
class StaticSiteExporter
{
    /**
     * The data-format version written into data/manifest.json. A future import
     * reads this to decide how to interpret the archive; bump it only on a
     * breaking change to the data/ layout. Documented in
     * documentation/export-format.md.
     *
     * Bumped to 2 by the epub-configuration feature (task 02): this single bump
     * covers every new field the whole feature adds across its tasks (the four
     * project front-/back-matter Markdown columns here, plus chapter covers and
     * the serialized PublicationSetting in later tasks) rather than bumping once
     * per task. `ImportRules::SUPPORTED_MANIFEST_VERSIONS` still accepts the
     * pre-bump version 1 — those archives simply import with the new fields null.
     */
    private const DATA_VERSION = 2;

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
            $this->addStory($zip, $project);
            $this->addTimeline($zip, $project);
            $this->addCodex($zip, $project, $includeMedia);
            $this->addBook($zip, $project);
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
     * it (task 04), but the manifest records the choice regardless.
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
     * README.md — the archive's front door for whoever opens the zip. It carries the
     * project name, the export date, the project description as plain text (the stored
     * HTML stripped to prose), and a short note pointing humans at book/ and machines
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
            .'story, open **`book/`** — start at `book/index.html` for a table of contents and '
            .'clickable chapters. If you are a **program** restoring this backup, read '
            .'**`data/`**: it is a complete, lossless copy of the project — every field, id, and '
            .'relationship — that can be rebuilt exactly. `data/manifest.json` describes the '
            .'archive. The `book/` folder is for reading only and is never the source of truth.';

        $this->addFromString($zip, 'README.md', implode("\n", $lines)."\n");
    }

    /**
     * The Story branch of data/: the project entity plus the act → chapter → scene
     * tree. Every entity is a `<db-id>-slug` directory holding a per-entity JSON
     * (scalars + stable ids + relationship id lists + links to its field files) and
     * raw field files (exact stored column values — never re-rendered/re-sanitized,
     * invariant 3). Nesting mirrors ownership (invariant, 00-overview.md).
     */
    private function addStory(ZipArchive $zip, Project $project): void
    {
        $this->addProject($zip, $project);

        // Eager-load the whole tree once, ordered by position at every level (the
        // app-wide invariant that also drives book/ numbering) — no N+1. The scene's
        // mentioned events are loaded so we can emit their ids without a per-scene query.
        $project->load([
            'acts' => fn ($query) => $query->orderBy('position'),
            'acts.chapters' => fn ($query) => $query->orderBy('position'),
            'acts.chapters.scenes' => fn ($query) => $query->orderBy('position'),
            'acts.chapters.scenes.mentionedEvents',
        ]);

        foreach ($project->acts as $act) {
            $actDir = 'data/acts/'.$this->entityDir($act);

            $actJson = [
                'id' => $act->id,
                'name' => $act->name,
                'position' => $act->position,
                'project_id' => $act->project_id,
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
                $this->addJson($zip, "{$chapterDir}/chapter.json", $chapterJson);

                foreach ($chapter->scenes as $scene) {
                    $this->addScene($zip, $chapterDir, $scene);
                }
            }
        }
    }

    /**
     * data/project/ — project.json (id, name, description_file?, plus the four
     * front-/back-matter Markdown field-file links) + description.html and any of
     * dedication.md / acknowledgements.md / preface.md / postface.md that are
     * non-empty. The four Markdown fields stay RAW — never rendered — like a
     * scene's contents.md (task 02, epub-configuration).
     */
    private function addProject(ZipArchive $zip, Project $project): void
    {
        $dir = 'data/project';

        $json = [
            'id' => $project->id,
            'name' => $project->name,
        ];
        $json += $this->addFieldFile($zip, $dir, 'description_file', 'description.html', $project->description);
        $json += $this->addFieldFile($zip, $dir, 'dedication_file', 'dedication.md', $project->dedication);
        $json += $this->addFieldFile($zip, $dir, 'acknowledgements_file', 'acknowledgements.md', $project->acknowledgements);
        $json += $this->addFieldFile($zip, $dir, 'preface_file', 'preface.md', $project->preface);
        $json += $this->addFieldFile($zip, $dir, 'postface_file', 'postface.md', $project->postface);

        $this->addJson($zip, "{$dir}/project.json", $json);
    }

    /**
     * A single scene directory: scene.json (scalars + mentioned-event ids + field-file
     * links) plus contents.md / description.html / notes.html. `status` is written as
     * the SceneStatus enum VALUE (machine form, e.g. "draft"). The share-link columns
     * (share_token, share_expires_at) are deliberately excluded (deployment secrets,
     * invariant / 00-overview.md).
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
     * The Timeline branch of data/: every plotline and event in the project, grouped
     * by type under data/timeline/. Each is a `<db-id>-slug` directory with a
     * per-entity JSON (scalars + stable id + relationship id lists + field-file links)
     * and a raw description.html fragment.
     *
     * The auto-created anchors are exported like any other row: the is_main main
     * plotline and the is_fixed Start/End bookend events. They are part of the graph
     * (Codex attribute values often anchor to the Start bookend, task 04). Matching —
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
     * each entry.json is written REGARDLESS (00-overview.md media-toggle default).
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
     * `public` disk (invariant 5) — never via the /storage URL — so the export never
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
     * The book/ human reading layer — the manuscript, readable. Unlike data/, this is
     * the ONE place the export renders Markdown to HTML (Str::markdown on scene
     * `contents`, in the Blade templates). It holds no descriptions, notes, images, or
     * data/ — only the compiled prose.
     *
     * Layout: book/index.html (a TOC of acts + chapter links) and book/NN/NN.html
     * (one compiled page per chapter, folder = zero-padded act position, file =
     * zero-padded per-act chapter position). Each chapter page carries prev/next
     * reading links at top and bottom that follow global reading order ACROSS act
     * boundaries; the first chapter's prev and the last chapter's next link back to
     * the TOC. HTML lives in the Blade templates under resources/views/exports/book
     * (guidelines: no string-built HTML in the service).
     */
    private function addBook(ZipArchive $zip, Project $project): void
    {
        $acts = $this->loadBookTree($project);

        // A flat, ordered chapter sequence (all chapters across all acts, in reading
        // order) built once so both the TOC and prev/next navigation share it.
        $sequence = [];
        foreach ($acts as $act) {
            foreach ($act->chapters as $chapter) {
                $sequence[] = ['act' => $act, 'chapter' => $chapter];
            }
        }

        $this->addBookIndex($zip, $project, $acts);

        $lastIndex = count($sequence) - 1;
        foreach ($sequence as $index => $item) {
            $act = $item['act'];
            $chapter = $item['chapter'];

            // Chapter pages live one level below index.html (book/NN/NN.html), so
            // prev/next reach a sibling chapter via ../NN/NN.html (crossing act
            // folders when needed) and the TOC via ../index.html at the ends.
            $previous = $index > 0
                ? '../'.$this->chapterHref($sequence[$index - 1]['act'], $sequence[$index - 1]['chapter'])
                : '../index.html';
            $next = $index < $lastIndex
                ? '../'.$this->chapterHref($sequence[$index + 1]['act'], $sequence[$index + 1]['chapter'])
                : '../index.html';

            $html = view('exports.book.chapter', [
                'chapterTitle' => $chapter->name,
                // Render Markdown → HTML through the same Scene::renderedContents
                // accessor the app's views use, so the reading layer and the app can
                // never drift apart on how scene contents are rendered.
                'renderedScenes' => $chapter->scenes->map(
                    fn (Scene $scene): string => $scene->renderedContents
                )->all(),
                'prevHref' => $previous,
                'nextHref' => $next,
            ])->render();

            $this->addFromString($zip, 'book/'.$this->chapterHref($act, $chapter), $html);
        }
    }

    /**
     * book/index.html — the TOC. Builds a plain data structure (act title + its
     * chapter titles and hrefs, in position order) so the Blade template stays
     * presentation-only; titles are HTML-escaped by Blade's {{ }}.
     *
     * @param  Collection<int, Act>  $acts
     */
    private function addBookIndex(ZipArchive $zip, Project $project, Collection $acts): void
    {
        $toc = [];
        foreach ($acts as $act) {
            $chapters = [];
            foreach ($act->chapters as $chapter) {
                $chapters[] = [
                    'title' => $chapter->name,
                    'href' => $this->chapterHref($act, $chapter),
                ];
            }
            $toc[] = ['title' => $act->name, 'chapters' => $chapters];
        }

        $html = view('exports.book.index', [
            'projectName' => $project->name,
            'toc' => $toc,
        ])->render();

        $this->addFromString($zip, 'book/index.html', $html);
    }

    /**
     * A chapter's path relative to book/: NN/NN.html — folder = zero-padded ACT
     * position, file = zero-padded PER-ACT chapter position. Single source of truth
     * for the TOC links, the prev/next links, and the written zip entry, so they can
     * never drift apart.
     */
    private function chapterHref(Act $act, Chapter $chapter): string
    {
        return sprintf('%02d/%02d.html', $act->position, $chapter->position);
    }

    /**
     * Load the act → chapter → scene tree for the book/ layer, ordered by position at
     * every level (the app-wide invariant that drives book/ numbering and reading
     * order). Scenes carry only `contents` here — the reading layer needs nothing else.
     * Reloaded independently of the data/ Story branch so the two layers never couple.
     *
     * @return Collection<int, Act>
     */
    private function loadBookTree(Project $project): Collection
    {
        return $project->acts()
            ->with([
                'chapters' => fn ($query) => $query->orderBy('position'),
                'chapters.scenes' => fn ($query) => $query->orderBy('position'),
            ])
            ->orderBy('position')
            ->get();
    }

    /**
     * The `<db-id>-slug` directory name for an entity. The id is the stable identity
     * (invariant 4); the slug is cosmetic. Reused by every data/ branch.
     */
    private function entityDir(Model $model): string
    {
        return $this->slugDir($model->id, $model->name);
    }

    /**
     * Build a `<id>-slug` directory segment from an explicit id + display name. Used
     * directly for entities whose display column is not `name` (e.g. an event's
     * `title`); entityDir() delegates here for name-based entities.
     */
    private function slugDir(int $id, string $name): string
    {
        return sprintf('%d-%s', $id, $this->slug($name));
    }

    /**
     * Write a raw field file (the EXACT stored column value — never re-rendered or
     * re-sanitized, invariant 3) and return the `*_file` link key to merge into the
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
     * A fresh, collision-free temp path under storage/app/exports (created on
     * demand). Each export gets its own uuid-named file so concurrent exports
     * never clobber one another.
     */
    private function freshTempZipPath(): string
    {
        $directory = storage_path('app/exports');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.DIRECTORY_SEPARATOR.Str::uuid().'.zip';
    }

    /**
     * Slug a name for a cosmetic directory/file segment, falling back to
     * 'untitled' when the name slugs to empty (e.g. a name of only punctuation).
     * Reused by later tasks for entity directory names.
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
