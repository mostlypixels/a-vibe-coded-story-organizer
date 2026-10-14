<?php

namespace App\Models;

use App\Enums\FieldKind;
use App\Enums\SceneStatus;
use App\Models\Concerns\HasEntityLinks;
use App\Models\Concerns\HasNotes;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSiblingPosition;
use App\Models\Concerns\SanitizesRichHtml;
use App\Models\Contracts\Revisionable;
use App\Services\EntityLinkResolver;
use App\Services\WordCountSnapshotRecorder;
use App\Support\AuthorMarkdown;
use App\Support\EntityLinks;
use App\Support\RichText;
use App\Support\WordCounter;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Scene extends Model implements Revisionable
{
    use HasEntityLinks;
    use HasFactory;
    use HasNotes;
    use HasRevisions;
    use HasSiblingPosition;
    use SanitizesRichHtml;

    protected $fillable = [
        'name',
        'description',
        'contents',
        'status',
        'position',
        'event_id',
        'viewpoint_character_id',
    ];

    protected $casts = [
        'status' => SceneStatus::class,
        'share_expires_at' => 'datetime',
    ];

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * The book this scene belongs to, through its chapter and act.
     *
     * > [!WARNING]
     * > A plain method, not a relation. Call `book()`; the `$scene->book` property throws.
     */
    public function book(): Book
    {
        return $this->chapter->act->book;
    }

    /** The project this scene belongs to. A plain method, like {@see book()}. */
    public function project(): Project
    {
        return $this->book()->project;
    }

    /**
     * The project that owns this scene's revisions (see HasRevisions).
     */
    public function revisionProject(): Project
    {
        return $this->project();
    }

    /**
     * The single event this scene happens during (optional).
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The character whose viewpoint (POV) this scene uses (optional).
     *
     * @return BelongsTo<CodexEntry, $this>
     */
    public function viewpointCharacter(): BelongsTo
    {
        return $this->belongsTo(CodexEntry::class, 'viewpoint_character_id');
    }

    /**
     * Events this scene mentions (many-to-many, optional).
     *
     * @return BelongsToMany<Event, $this>
     */
    public function mentionedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class);
    }

    /**
     * Codex entries whose name or an alias appears as a whole word in this scene's
     * contents. A derived cache maintained by SceneReferenceMatcher — never edited by
     * hand. The pivot has no columns of its own (plain belongsToMany, no pivot model).
     *
     * @return BelongsToMany<CodexEntry, $this>
     */
    public function codexReferences(): BelongsToMany
    {
        return $this->belongsToMany(CodexEntry::class, 'scene_codex_entry');
    }

    /**
     * The scene's Markdown `contents` rendered to HTML. This is the single home for
     * the null-guard and the renderer choice. The Story overview, the public share
     * view, and the book export all read it.
     * Rendering `contents` (unlike the rich-HTML fields) is safe by our convention:
     * it is Markdown gated by ValidMarkdown, echoed with {!! !!} only here and in those
     * views. Returns an empty string when there are no contents.
     * The `entity:` links become app URLs. The project loads only when the text has a link.
     *
     * @return Attribute<string, never>
     */
    protected function renderedContents(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $html = AuthorMarkdown::render($this->contents);

                return EntityLinks::find($html) === []
                    ? $html
                    : app(EntityLinkResolver::class)->render($html, $this->project());
            },
        );
    }

    /** The editor can store markup with no text, such as `<p></p>`. */
    public function hasDescription(): bool
    {
        return RichText::toPlainText($this->description) !== '';
    }

    /** The first words of the contents as plain text, on one line. It keeps the link text and runs no link lookup. */
    public function contentsExcerpt(int $words = 100): string
    {
        return Str::of(RichText::toPlainText(AuthorMarkdown::render($this->contents)))->squish()->words($words)->toString();
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
            if ($scene->getAttribute('position') === null) {
                $scene->position = static::where('chapter_id', $scene->chapter_id)->max('position') + 1;
            }
        });

        // Keeps word_count true to contents on every write path that goes through
        // $model->save() — autosave, manual save, revert/undo (RevisionReverter),
        // import, and seeders/factories. A controller-level implementation would
        // miss RevisionReverter, which never touches FieldAutosaver, so
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
                app(WordCountSnapshotRecorder::class)->record($scene->project());
            }
        });

        // Deleting is writing: the total drops.
        static::deleted(function (Scene $scene): void {
            app(WordCountSnapshotRecorder::class)->record($scene->project());
        });
    }
}
