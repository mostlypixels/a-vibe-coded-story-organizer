<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NoteCategory extends Model
{
    use HasFactory;

    public const MAX_DEPTH = 3;

    protected $fillable = [
        'project_id',
        'parent_id',
        'name',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<NoteCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<NoteCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Note, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /**
     * Levels from the root down to this category: a root category is 1.
     * Stops after MAX_DEPTH + 1 steps, so bad data cannot loop it.
     */
    public function depth(): int
    {
        $depth = 1;
        $category = $this;

        while ($category->parent_id !== null && $depth <= self::MAX_DEPTH) {
            $category = $category->parent;
            $depth++;
        }

        return $depth;
    }
}
