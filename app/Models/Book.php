<?php

namespace App\Models;

use App\Enums\BookLanguage;
use App\Enums\StoryOverviewMode;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSiblingPosition;
use App\Models\Concerns\SanitizesRichHtml;
use App\Services\CoverImageService;
use App\Services\WordCountSnapshotRecorder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One volume of a project. A project holds at least one book, and the
 * manuscript, the publication metadata and the EPUB belong to the book. The
 * codex, the timeline and the word-count history stay on the project.
 *
 * > [!WARNING]
 * > `name` is nullable, and every display site must call {@see displayName()}
 * > rather than `->name` — an unnamed book renders an empty string otherwise.
 * > A null name means "this book has no name of its own", so it tracks the
 * > project name through every rename. {@see hasOwnName()} is the single
 * > predicate that decides how visible the book layer is in the UI.
 */
class Book extends Model
{
    use HasFactory;
    use HasRevisions;
    use HasSiblingPosition;
    use SanitizesRichHtml;

    protected $fillable = [
        'name',
        'description',
        'language',
        'author',
        'publisher',
        'rights',
        'isbn',
        'cover_image',
        'dedication',
        'acknowledgements',
        'preface',
        'postface',
        'position',
        'overview_render_mode',
    ];

    protected $casts = [
        'language' => BookLanguage::class,
        'overview_render_mode' => StoryOverviewMode::class,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The project that owns this book's revisions (see HasRevisions).
     */
    public function revisionProject(): Project
    {
        return $this->project;
    }

    /**
     * The name to print for this book, anywhere a reader sees one: its own name,
     * or the project's while it has none.
     */
    public function displayName(): string
    {
        return $this->name ?? $this->project->name;
    }

    /**
     * Whether this book carries a name of its own. The UI shows the book layer
     * (picker line, breadcrumb, page title) only for a book that does.
     */
    public function hasOwnName(): bool
    {
        return $this->name !== null;
    }

    /**
     * Books are ordered within their project (see HasSiblingPosition).
     */
    protected function siblingScopeColumn(): string
    {
        return 'project_id';
    }

    protected static function booted(): void
    {
        static::creating(function (Book $book) {
            if (is_null($book->position)) {
                $book->position = static::where('project_id', $book->project_id)->max('position') + 1;
            }
        });

        // An unnamed book exists only while it is the project's only book: it
        // borrows the project name. A second book ends that, so every unnamed
        // sibling takes the project's current name as its own here. Without it,
        // renaming the project to a series title later renames volume one too.
        static::created(function (Book $book) {
            static::query()
                ->where('project_id', $book->project_id)
                ->whereKeyNot($book->getKey())
                ->whereNull('name')
                ->update(['name' => $book->project->name]);
        });

        // The cover is a plain path column (not an FK-cascaded row), so deleting
        // a book never removes its file automatically. Delete it here before the
        // row is gone, otherwise a book deletion leaks an orphan cover on the
        // public disk (media-lifecycle.md pitfall).
        static::deleting(function (Book $book) {
            app(CoverImageService::class)->delete($book->cover_image);
        });

        // The book's manuscript cascades at the database level, several levels
        // down, which fires no Scene::deleted. The recorder re-sums, so the
        // project total is right whether the scenes went or moved.
        static::deleted(function (Book $book): void {
            app(WordCountSnapshotRecorder::class)->record($book->project);
        });
    }
}
