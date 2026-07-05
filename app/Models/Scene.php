<?php

namespace App\Models;

use App\Enums\SceneStatus;
use App\Models\Concerns\SanitizesRichHtml;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Scene extends Model
{
    use HasFactory;
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

    protected static function booted(): void
    {
        static::creating(function (Scene $scene) {
            if (is_null($scene->position)) {
                $scene->position = static::where('chapter_id', $scene->chapter_id)->max('position') + 1;
            }
        });
    }
}
