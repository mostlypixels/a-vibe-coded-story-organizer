<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    use HasFactory;

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

    public function plotlines(): BelongsToMany
    {
        return $this->belongsToMany(Plotline::class);
    }
}
