<?php

namespace App\Models;

use App\Models\Concerns\SanitizesRichHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chapter extends Model
{
    use HasFactory;
    use SanitizesRichHtml;

    protected $fillable = [
        'name',
        'description',
        'position',
    ];

    public function act(): BelongsTo
    {
        return $this->belongsTo(Act::class);
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Chapter $chapter) {
            if (is_null($chapter->position)) {
                $chapter->position = static::where('act_id', $chapter->act_id)->max('position') + 1;
            }
        });
    }
}
