<?php

namespace App\Models;

use App\Enums\BookLanguage;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\SanitizesRichHtml;
use App\Services\CodexMediaService;
use App\Services\CoverImageService;
use App\Support\PlotlineColors;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Project extends Model
{
    use HasFactory;
    use HasRevisions;
    use SanitizesRichHtml;

    protected $fillable = [
        'name',
        'description',
        'language',
        'author',
        'publisher',
        'rights',
        'isbn',
        'cover_image',
        'dedication',
        'acknowledgements',
        'preface',
        'postface',
    ];

    protected $casts = [
        'language' => BookLanguage::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A project owns its own revisions (see HasRevisions).
     */
    public function revisionProject(): Project
    {
        return $this;
    }

    public function plotlines(): HasMany
    {
        return $this->hasMany(Plotline::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function acts(): HasMany
    {
        return $this->hasMany(Act::class);
    }

    public function codexEntries(): HasMany
    {
        return $this->hasMany(CodexEntry::class);
    }

    public function codexAttributes(): HasMany
    {
        return $this->hasMany(CodexAttribute::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function publicationSetting(): HasOne
    {
        return $this->hasOne(PublicationSetting::class);
    }

    /**
     * Return the project's publication setting, or an unsaved default instance
     * when no row exists. Never returns null, enabling code to access settings
     * for projects that never visited the config form.
     *
     * The unsaved instance has all default attributes set to match what the
     * database schema defaults would apply on insertion.
     */
    public function publicationSettingOrDefault(): PublicationSetting
    {
        if ($this->publicationSetting) {
            return $this->publicationSetting;
        }

        return $this->publicationSetting()->make([
            'include_project_cover' => true,
            'include_chapter_covers' => false,
            'include_author' => true,
            'include_publisher' => true,
            'include_rights' => true,
            'include_isbn' => true,
            'include_scene_titles' => false,
            'include_act_descriptions' => false,
            'include_chapter_descriptions' => false,
            'include_scene_descriptions' => false,
            'include_dedication' => false,
            'include_acknowledgements' => false,
            'include_preface' => false,
            'include_postface' => false,
            'chapter_title_format' => 'chapter_number_title',
            'table_of_contents_depth' => 'chapters',
            'divider_type' => 'horizontal_rule',
            'section_order' => PublicationSetting::SECTION_KEYS,
            'include_codex_appendix' => false,
            'appendix_entry_types' => [],
            'appendix_include_images' => false,
        ]);
    }

    /**
     * The project's Start bookend event (year 0001): the earliest is_fixed event,
     * created undeletable by booted() and the anchor for every attribute-timeline
     * baseline. Single source of truth for "the project's Start".
     *
     * Ordered by (event_datetime, id): the id tie-break is part of the contract —
     * two fixed events could share a datetime, and "never datetime alone" keeps the
     * bookend resolution deterministic (see CLAUDE.md, refactor_codex finding 8).
     */
    public function startEvent(): Event
    {
        return $this->events()
            ->where('is_fixed', true)
            ->orderBy('event_datetime')
            ->orderBy('id')
            ->firstOrFail();
    }

    /**
     * The project's End bookend event (year 3000): the latest is_fixed event. The
     * orderByDesc twin of startEvent(); the (event_datetime, id) tie-break is equally
     * part of the contract.
     */
    public function endEvent(): Event
    {
        return $this->events()
            ->where('is_fixed', true)
            ->orderByDesc('event_datetime')
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * The earliest non-bookend event, or null when the project holds only its
     * Start/End bookends. Bounds how far the Start bookend may move (Start must
     * stay first); ordered canonically by (event_datetime, id) like startEvent().
     */
    public function earliestRegularEvent(): ?Event
    {
        return $this->events()
            ->where('is_fixed', false)
            ->orderBy('event_datetime')
            ->orderBy('id')
            ->first();
    }

    /**
     * The latest non-bookend event, or null when only the bookends exist. The
     * orderByDesc twin of earliestRegularEvent(); bounds how far End may move.
     */
    public function latestRegularEvent(): ?Event
    {
        return $this->events()
            ->where('is_fixed', false)
            ->orderByDesc('event_datetime')
            ->orderByDesc('id')
            ->first();
    }

    protected static function booted(): void
    {
        static::created(function (Project $project) {
            $mainPlotline = $project->plotlines()->create([
                'name' => 'Main plotline',
                'is_main' => true,
                'color' => PlotlineColors::PRESETS[0],
            ]);

            foreach ([
                ['title' => 'Start', 'event_datetime' => '0001-01-01 00:00:00'],
                ['title' => 'End', 'event_datetime' => '3000-01-01 00:00:00'],
            ] as $data) {
                $event = $project->events()->create($data + ['is_fixed' => true]);
                $event->plotlines()->attach($mainPlotline);
            }
        });

        // Delete the project's codex media files off disk before the FK cascade drops
        // their rows. project → codex_entries → codex_media cascades at the DB level,
        // which bypasses CodexEntry's own `deleting` hook, so without this every
        // project (or account) deletion would leak orphan files (media-lifecycle.md).
        static::deleting(function (Project $project) {
            app(CodexMediaService::class)->purgeProject($project);

            // The cover is a plain path column (not a tracked codex_media row), so the
            // FK cascade never touches its file. Delete it here before the row is gone,
            // otherwise project deletion leaks an orphan cover on the public disk.
            if ($project->cover_image !== null) {
                Storage::disk('public')->delete($project->cover_image);
            }

            // project → acts → chapters cascades at the DB level, bypassing both
            // Act::deleting and Chapter::deleting — so purge every surviving chapter's
            // cover file here (one query over the project's chapters, joined through
            // their acts) before the cascade drops the rows, otherwise a project
            // deletion leaks an orphan cover per chapter (media-lifecycle.md pitfall).
            $coverImageService = app(CoverImageService::class);

            $chapterCovers = Chapter::query()
                ->whereHas('act', fn ($query) => $query->where('project_id', $project->id))
                ->whereNotNull('cover_image')
                ->pluck('cover_image');

            foreach ($chapterCovers as $coverPath) {
                $coverImageService->delete($coverPath);
            }
        });
    }
}
