<?php

namespace App\Models;

use App\Models\Concerns\SanitizesRichHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Act extends Model
{
    use HasFactory;
    use SanitizesRichHtml;

    protected $fillable = [
        'name',
        'description',
        'position',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Act $act) {
            if (is_null($act->position)) {
                $act->position = static::where('project_id', $act->project_id)->max('position') + 1;
            }
        });
    }
}
