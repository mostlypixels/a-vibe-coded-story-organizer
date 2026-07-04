<?php

namespace App\Models;

use App\Services\CodexMediaService;
use App\Support\PlotlineColors;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /**
     * The project's Start bookend event (year 0000): the earliest is_fixed event,
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

    protected static function booted(): void
    {
        static::created(function (Project $project) {
            $mainPlotline = $project->plotlines()->create([
                'name' => 'Main plotline',
                'is_main' => true,
                'color' => PlotlineColors::PRESETS[0],
            ]);

            foreach ([
                ['title' => 'Start', 'event_datetime' => '0000-01-01 00:00:00'],
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
        });
    }
}
