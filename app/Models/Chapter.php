<?php

namespace App\Models;

use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSiblingPosition;
use App\Models\Concerns\SanitizesRichHtml;
use App\Models\Concerns\SumsSceneWords;
use App\Services\CoverImageService;
use App\Services\WordCountSnapshotRecorder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chapter extends Model
{
    use HasFactory;
    use HasRevisions;
    use HasSiblingPosition;
    use SanitizesRichHtml;
    use SumsSceneWords;

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

    /**
     * The book this chapter belongs to, through its act.
     *
     * > [!WARNING]
     * > A plain method, not a relation. Call `book()`; the `$chapter->book` property throws.
     */
    public function book(): Book
    {
        return $this->act->book;
    }

    /** The project this chapter belongs to. A plain method, like {@see book()}. */
    public function project(): Project
    {
        return $this->book()->project;
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class);
    }

    /**
     * The project that owns this chapter's revisions (see HasRevisions).
     */
    public function revisionProject(): Project
    {
        return $this->project();
    }

    /** The cover is on a private disk. The route checks project ownership before it sends the file. */
    public function coverUrl(): ?string
    {
        return $this->cover_image !== null ? route('chapters.cover', $this) : null;
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
        // media disk. The project/act cascade paths bypass THIS hook (they delete
        // chapter rows via the DB FK), so Project::deleting and Act::deleting purge
        // surviving chapters' covers themselves (media-lifecycle.md pitfall).
        static::deleting(function (Chapter $chapter) {
            app(CoverImageService::class)->delete($chapter->cover_image);

            // The cascade to scenes skips their HasRevisions hook.
            Revision::deleteFor($chapter->scenes());
        });

        // The chapter's scenes cascade at the database level, which fires no
        // Scene::deleted — so record the project's new total here. The
        // controller deletes inside a transaction, so this upsert joins it.
        static::deleted(function (Chapter $chapter): void {
            app(WordCountSnapshotRecorder::class)->record($chapter->project());
        });
    }
}
