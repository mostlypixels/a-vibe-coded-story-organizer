<?php

namespace App\Models;

use App\Enums\SceneStatus;
use App\Models\Concerns\HasSiblingPosition;
use App\Models\Concerns\SanitizesRichHtml;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Scene extends Model
{
    use HasFactory;
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
     * Sanitize the `notes` rich-HTML field on write. `contents` deliberately has no
     * mutator: it stays Markdown-only (ValidMarkdown + Str::markdown() rendering).
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
            get: fn (): string => Str::markdown($this->contents ?? ''),
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
     * Builds the URL by route name (`shared.scenes.show`, registered in the
     * public-display task); returns null for an unshared scene. Note this does
     * not check expiry — use isShared() to gate visibility.
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
    }
}
