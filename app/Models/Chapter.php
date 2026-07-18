<?php

namespace App\Models;

use App\Models\Concerns\HasSiblingPosition;
use App\Models\Concerns\SanitizesRichHtml;
use App\Services\CoverImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chapter extends Model
{
    use HasFactory;
    use HasSiblingPosition;
    use SanitizesRichHtml;

    protected $fillable = [
        'name',
        'description',
        'cover_image',
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

    /**
     * Chapters are ordered within their act (see HasSiblingPosition).
     */
    protected function siblingScopeColumn(): string
    {
        return 'act_id';
    }

    protected static function booted(): void
    {
        static::creating(function (Chapter $chapter) {
            if (is_null($chapter->position)) {
                $chapter->position = static::where('act_id', $chapter->act_id)->max('position') + 1;
            }
        });

        // The cover is a plain path column (not an FK-cascaded row), so deleting a
        // single chapter never removes its file automatically. Delete it here before
        // the row is gone, otherwise a chapter deletion leaks an orphan cover on the
        // public disk. The project/act cascade paths bypass THIS hook (they delete
        // chapter rows via the DB FK), so Project::deleting and Act::deleting purge
        // surviving chapters' covers themselves (media-lifecycle.md pitfall).
        static::deleting(function (Chapter $chapter) {
            app(CoverImageService::class)->delete($chapter->cover_image);
        });
    }
}
