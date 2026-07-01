<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scene extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'contents',
        'position',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Scene $scene) {
            if (is_null($scene->position)) {
                $scene->position = static::where('chapter_id', $scene->chapter_id)->max('position') + 1;
            }
        });
    }
}
