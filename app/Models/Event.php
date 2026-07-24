<?php

namespace App\Models;

use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\SanitizesRichHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
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

    public function plotlines(): BelongsToMany
    {
        return $this->belongsToMany(Plotline::class);
    }

    /**
     * Scenes that happen during this event.
     */
    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class);
    }

    /**
     * Scenes that mention this event (many-to-many).
     */
    public function mentioningScenes(): BelongsToMany
    {
        return $this->belongsToMany(Scene::class);
    }
}
