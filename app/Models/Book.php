<?php

namespace App\Models;

use App\Enums\BookLanguage;
use App\Enums\StoryOverviewMode;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSiblingPosition;
use App\Models\Concerns\SanitizesRichHtml;
use App\Services\CoverImageService;
use App\Services\WordCountSnapshotRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function acts(): HasMany
    {
        return $this->hasMany(Act::class);
    }

    public function publicationSetting(): HasOne
    {
        return $this->hasOne(PublicationSetting::class);
    }

    /**
     * Return this book's publication setting, or an unsaved default instance
     * when no row exists. Never returns null, enabling code to read settings
     * for a book that never visited the config form.
     *
     * The unsaved instance has all default attributes set to match what the
     * database schema defaults would apply on insertion.
     */
    public function publicationSettingOrDefault(): PublicationSetting
    {
        if ($this->publicationSetting) {
            return $this->publicationSetting;
        }

        return $this->publicationSetting()->make([
            'include_book_cover' => true,
            'include_chapter_covers' => false,
            'include_author' => true,
            'include_publisher' => true,
            'include_rights' => true,
            'include_isbn' => true,
            'include_scene_titles' => false,
            'include_act_descriptions' => false,
            'include_chapter_descriptions' => false,
            'include_scene_descriptions' => false,
            'include_dedication' => false,
            'include_acknowledgements' => false,
            'include_preface' => false,
            'include_postface' => false,
            'chapter_title_format' => 'chapter_number_title',
            'table_of_contents_depth' => 'chapters',
            'divider_type' => 'horizontal_rule',
            'section_order' => PublicationSetting::SECTION_KEYS,
            'include_codex_appendix' => false,
            'appendix_entry_types' => [],
            'appendix_include_images' => false,
        ]);
    }

    /**
     * Every chapter in this book, as a query to build on — the book-scoped twin
     * of {@see Project::chapterQuery()}, and a Builder for the same reason: a
     * relation would join `acts`, whose own `name`/`position` columns make every
     * caller's `orderBy()` ambiguous.
     */
    public function chapterQuery(): Builder
    {
        return Chapter::query()->whereHas('act', fn (Builder $query) => $query->where('book_id', $this->id));
    }

    /**
     * Every scene in this book, as a query to build on. The two-level twin of
     * {@see self::chapterQuery()} — scenes reach the book through
     * chapter → act.
     */
    public function sceneQuery(): Builder
    {
        return Scene::query()->whereHas('chapter.act', fn (Builder $query) => $query->where('book_id', $this->id));
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
        //
        // book -> acts -> chapters cascades at the database level, which fires
        // neither Act::deleting nor Chapter::deleting — so purge every chapter
        // cover below this book here too, one query through the acts.
        static::deleting(function (Book $book) {
            $coverImageService = app(CoverImageService::class);

            $coverImageService->delete($book->cover_image);

            $chapterCovers = $book->chapterQuery()
                ->whereNotNull('cover_image')
                ->pluck('cover_image');

            foreach ($chapterCovers as $coverPath) {
                $coverImageService->delete($coverPath);
            }
        });

        // The book's manuscript cascades at the database level, several levels
        // down, which fires no Scene::deleted. The recorder re-sums, so the
        // project total is right whether the scenes went or moved.
        static::deleted(function (Book $book): void {
            app(WordCountSnapshotRecorder::class)->record($book->project);
        });
    }
}
