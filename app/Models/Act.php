<?php

namespace App\Models;

use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSiblingPosition;
use App\Models\Concerns\SanitizesRichHtml;
use App\Services\CoverImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Act extends Model
{
    use HasFactory;
    use HasRevisions;
    use HasSiblingPosition;
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

    /**
     * The project that owns this act's revisions (see HasRevisions).
     */
    public function revisionProject(): Project
    {
        return $this->project;
    }

    /**
     * Acts are ordered within their project (see HasSiblingPosition).
     */
    protected function siblingScopeColumn(): string
    {
        return 'project_id';
    }

    protected static function booted(): void
    {
        static::creating(function (Act $act) {
            if (is_null($act->position)) {
                $act->position = static::where('project_id', $act->project_id)->max('position') + 1;
            }
        });

        // Deleting an act cascades to its chapters at the DB level, which bypasses
        // Chapter::deleting — so purge the surviving chapters' cover files here before
        // the FK cascade drops their rows, otherwise deleting an act leaks an orphan
        // cover per chapter on the public disk (media-lifecycle.md pitfall).
        static::deleting(function (Act $act) {
            $coverImageService = app(CoverImageService::class);

            foreach ($act->chapters()->whereNotNull('cover_image')->pluck('cover_image') as $coverPath) {
                $coverImageService->delete($coverPath);
            }
        });
    }
}
