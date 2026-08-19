<?php

namespace App\Models;

use App\Enums\FieldKind;
use App\Enums\SceneStatus;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSiblingPosition;
use App\Models\Concerns\SanitizesRichHtml;
use App\Services\WordCountSnapshotRecorder;
use App\Support\AuthorMarkdown;
use App\Support\WordCounter;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Scene extends Model
{
    use HasFactory;
    use HasRevisions;
    use HasSiblingPosition;
    use SanitizesRichHtml;

    protected $fillable = [
        'name',
        'description',
        'contents',
        'notes',
        'status',
        'position',
        'event_id',
    ];

    protected $casts = [
        'status' => SceneStatus::class,
        'share_expires_at' => 'datetime',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * The project that owns this scene's revisions (see HasRevisions).
     */
    public function revisionProject(): Project
    {
        return $this->chapter->act->book->project;
    }

    /**
     * The single event this scene happens during (optional).
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Events this scene mentions (many-to-many, optional).
     */
    public function mentionedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class);
    }

    /**
     * Codex entries whose name or an alias appears as a whole word in this scene's
     * contents. A derived cache maintained by SceneReferenceMatcher — never edited by
     * hand. The pivot has no columns of its own (plain belongsToMany, no pivot model).
     */
    public function codexReferences(): BelongsToMany
    {
        return $this->belongsToMany(CodexEntry::class, 'scene_codex_entry');
    }

    /**
     * Sanitize the `notes` rich-HTML field on write. `contents` deliberately has no
     * mutator: it stays Markdown-only (ValidMarkdown + AuthorMarkdown rendering).
     */
    protected function notes(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $this->cleanRichHtml($value),
        );
    }

    /**
     * The scene's Markdown `contents` rendered to HTML. This is the single home for
     * the null-guard and the renderer choice (previously `Str::markdown($contents ?? '')`
     * repeated across the Story overview, the public share view, and the book export).
     * Rendering `contents` (unlike the rich-HTML fields) is safe by our convention:
     * it is Markdown gated by ValidMarkdown, echoed with {!! !!} only here and in those
     * views. Returns an empty string when there are no contents.
     */
    protected function renderedContents(): Attribute
    {
        return Attribute::make(
            get: fn (): string => AuthorMarkdown::render($this->contents),
        );
    }

    /**
     * Scenes are ordered within their chapter (see HasSiblingPosition).
     */
    protected function siblingScopeColumn(): string
    {
        return 'chapter_id';
    }

    /**
     * Whether this scene currently has a live public share link.
     *
     * True only when a token is set AND its expiry is set and still in the
     * future. Expired links are inert server-side — never trust the presence
     * of a token alone.
     */
    public function isShared(): bool
    {
        return $this->share_token !== null
            && $this->share_expires_at !== null
            && $this->share_expires_at->isFuture();
    }

    /**
     * The public URL for this scene's share link, or null when no token exists.
     *
     * Builds the URL by route name (`shared.scenes.show`); returns null for an
     * unshared scene. Note this does not check expiry — use isShared() to gate
     * visibility.
     */
    public function shareUrl(): ?string
    {
        return $this->share_token
            ? route('shared.scenes.show', $this->share_token)
            : null;
    }

    protected static function booted(): void
    {
        static::creating(function (Scene $scene) {
            if (is_null($scene->position)) {
                $scene->position = static::where('chapter_id', $scene->chapter_id)->max('position') + 1;
            }
        });

        // Keeps word_count true to contents on every write path that goes through
        // $model->save() — autosave, manual save, revert/undo (RevisionReverter),
        // import, and seeders/factories. A controller-level implementation would
        // miss RevisionReverter, which never touches FieldAutosaveController, so
        // the count would go stale the moment someone uses Undo (word-count spec,
        // expanded/architecture.md "The write path").
        //
        // `saving`, not `saved`: it sets an attribute on the row already about to
        // be written, so there is no second UPDATE and no half-applied state.
        static::saving(function (Scene $scene): void {
            if ($scene->isDirty('contents')) {
                $scene->word_count = WordCounter::count($scene->contents, FieldKind::Markdown);
            }
        });

        // Records the project's new total on the writer's day. `saved`, not
        // `saving` — the opposite of the hook above, for the opposite reason:
        // the recorder sums the scenes table, so this row must already be
        // written.
        //
        // Eloquent syncs `changes` on an update only, so wasChanged() is always
        // false for a new row. A new scene therefore tests its own word_count:
        // a scene created with text moves the total, an empty one does not.
        static::saved(function (Scene $scene): void {
            $movedTheTotal = $scene->wasRecentlyCreated
                ? $scene->word_count > 0
                : $scene->wasChanged('word_count');

            if ($movedTheTotal) {
                app(WordCountSnapshotRecorder::class)->record($scene->chapter->act->book->project);
            }
        });

        // Deleting is writing: the total drops.
        static::deleted(function (Scene $scene): void {
            app(WordCountSnapshotRecorder::class)->record($scene->chapter->act->book->project);
        });
    }
}
