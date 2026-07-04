<?php

namespace App\Models;

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
    }
}
