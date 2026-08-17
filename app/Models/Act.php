<?php

namespace App\Models;

use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSiblingPosition;
use App\Models\Concerns\SanitizesRichHtml;
use App\Services\CoverImageService;
use App\Services\WordCountSnapshotRecorder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }

    /**
     * Every scene under this act, through its chapters — the grandchildren a
     * plain cascade delete also destroys, which is what the edit page's
     * "move or delete" dialog counts before offering the choice.
     *
     * A real `hasManyThrough` (one intermediate, `chapters`) rather than a
     * `whereHas` walk, so it can be counted, eager-loaded and constrained like
     * any other relation. Callers that order or select on it must qualify the
     * columns (`scenes.position`), since the join brings `chapters`' own
     * `name`/`position` into scope.
     */
    public function scenes(): HasManyThrough
    {
        return $this->hasManyThrough(Scene::class, Chapter::class);
    }

    /**
     * The project that owns this act's revisions (see HasRevisions).
     */
    public function revisionProject(): Project
    {
        return $this->book->project;
    }

    /**
     * Acts are ordered within their book (see HasSiblingPosition). Two books in
     * one project each start at position 1.
     */
    protected function siblingScopeColumn(): string
    {
        return 'book_id';
    }

    protected static function booted(): void
    {
        static::creating(function (Act $act) {
            if (is_null($act->position)) {
                $act->position = static::where('book_id', $act->book_id)->max('position') + 1;
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

        // The act's chapters and their scenes cascade at the database level, two
        // levels down, which fires neither Chapter::deleted nor Scene::deleted.
        // The recorder re-sums, so an act deleted with its chapters reassigned
        // correctly leaves the total unchanged.
        static::deleted(function (Act $act): void {
            app(WordCountSnapshotRecorder::class)->record($act->book->project);
        });
    }
}
