<?php

namespace App\Models;

use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\SanitizesRichHtml;
use App\Models\Contracts\Revisionable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model implements Revisionable
{
    use HasFactory;
    use HasRevisions;
    use SanitizesRichHtml;

    protected $fillable = [
        'title',
        'description',
        'event_datetime',
        'is_fixed',
    ];

    protected function casts(): array
    {
        return [
            'event_datetime' => 'datetime',
            'is_fixed' => 'boolean',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The project that owns this event's revisions (see HasRevisions).
     */
    public function revisionProject(): Project
    {
        return $this->project;
    }

    /**
     * Events are titled by `title`, not the `name` every other revisionable
     * uses — the single override of HasRevisions::revisionDisplayColumn().
     */
    public static function revisionDisplayColumn(): string
    {
        return 'title';
    }

    /** @return BelongsToMany<Plotline, $this> */
    public function plotlines(): BelongsToMany
    {
        return $this->belongsToMany(Plotline::class);
    }

    /**
     * Scenes that happen during this event.
     *
     * @return HasMany<Scene, $this>
     */
    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class);
    }

    /**
     * Codex attribute values that start at this event. They are deleted with it.
     *
     * @return HasMany<CodexAttributeValue, $this>
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(CodexAttributeValue::class, 'start_event_id');
    }

    /**
     * Scenes that mention this event (many-to-many).
     *
     * @return BelongsToMany<Scene, $this>
     */
    public function mentioningScenes(): BelongsToMany
    {
        return $this->belongsToMany(Scene::class);
    }
}
