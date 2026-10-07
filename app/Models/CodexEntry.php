<?php

namespace App\Models;

use App\Enums\CodexEntryType;
use App\Enums\CodexMediaCollection;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\SanitizesRichHtml;
use App\Models\Contracts\Revisionable;
use App\Services\AttributeTimeline;
use App\Services\CodexMediaService;
use App\Support\Age;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CodexEntry extends Model implements Revisionable
{
    use HasFactory;
    use HasRevisions;
    use SanitizesRichHtml;

    protected $fillable = [
        'project_id',
        'type',
        'name',
        'description',
        'inception_event_id',
        'termination_event_id',
    ];

    protected $casts = [
        'type' => CodexEntryType::class,
    ];

    protected static function booted(): void
    {
        // Delete the entry's files off disk before the FK cascade drops their rows.
        // The cascadeOnDelete on codex_media removes the rows but never the files,
        // so without this every entry deletion would leak orphan files (data-model.md).
        static::deleting(function (CodexEntry $entry) {
            app(CodexMediaService::class)->purge($entry);
        });
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function inceptionEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function terminationEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The project that owns this codex entry's revisions (see HasRevisions).
     */
    public function revisionProject(): Project
    {
        return $this->project;
    }

    /** @return HasMany<CodexAlias, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(CodexAlias::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Scenes whose contents reference this entry by name or alias (whole-word match).
     * A derived cache maintained by SceneReferenceMatcher — never edited by hand.
     * The pivot has no columns of its own (plain belongsToMany, no pivot model).
     *
     * @return BelongsToMany<Scene, $this>
     */
    public function referencingScenes(): BelongsToMany
    {
        return $this->belongsToMany(Scene::class, 'scene_codex_entry');
    }

    /** @return HasMany<CodexMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(CodexMedia::class);
    }

    /** @return HasMany<CodexAttributeValue, $this> */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(CodexAttributeValue::class);
    }

    /**
     * The cover image: the single media row in the Cover collection.
     * This is the single source of truth — there is deliberately no
     * cover_media_id FK on codex_entries.
     *
     * @return HasOne<CodexMedia, $this>
     */
    public function cover(): HasOne
    {
        return $this->hasOne(CodexMedia::class)
            ->where('collection', CodexMediaCollection::Cover);
    }

    /**
     * The first letter of the first and the last word of the name, for a cover placeholder.
     * Only a character has initials: "RP" for a street tells the reader nothing.
     *
     * > [!WARNING]
     * > An article or a title counts as a word: "La Thénardier" gives "LT".
     */
    public function initials(): ?string
    {
        if ($this->type !== CodexEntryType::Character) {
            return null;
        }

        // The first letter, not the first character: "« Le Cabuc »" starts with a quote mark.
        $letters = collect(preg_split('/\s+/u', $this->name, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $word) => preg_match('/\p{L}/u', $word, $match) ? $match[0] : null)
            ->filter()
            ->values();

        if ($letters->isEmpty()) {
            return null;
        }

        $initials = $letters->count() === 1 ? $letters->first() : $letters->first().$letters->last();

        return mb_strtoupper($initials);
    }

    /**
     * Whether termination happens before inception — a time traveller.
     * A legal save (see the edit page warning); age and the existence
     * filter both stand down when this is true.
     */
    public function hasInvertedLifespan(): bool
    {
        if (! $this->inceptionEvent || ! $this->terminationEvent) {
            return false;
        }

        return $this->terminationEvent->event_datetime->lt($this->inceptionEvent->event_datetime);
    }

    /**
     * This entry's age at a moment, in whole years. Null when there is no
     * inception event, no moment, or the lifespan is inverted — a single
     * number cannot describe a nonsensical timeline.
     */
    public function ageAt(?Event $moment): ?Age
    {
        if (! $this->inceptionEvent || ! $moment || $this->hasInvertedLifespan()) {
            return null;
        }

        return Age::between($this->inceptionEvent->event_datetime, $moment->event_datetime);
    }

    /**
     * Whether this entry exists at a moment: true with no moment, a type
     * that does not track a lifespan, an inverted lifespan (always shown),
     * or when inception <= moment <= termination (each bound inclusive, an
     * unset bound open).
     */
    public function existsAt(?Event $moment): bool
    {
        if (! $moment || ! $this->type->tracksLifespan() || $this->hasInvertedLifespan()) {
            return true;
        }

        $afterInception = ! $this->inceptionEvent
            || ! $moment->event_datetime->lt($this->inceptionEvent->event_datetime);

        $beforeTermination = ! $this->terminationEvent
            || ! $moment->event_datetime->gt($this->terminationEvent->event_datetime);

        return $afterInception && $beforeTermination;
    }

    /**
     * Resolve this entry's value for an attribute as of a scene/event moment.
     *
     * Thin wrapper over AttributeTimeline for the scene/event "as of" views. Returns
     * null when $event is null (an unassigned scene → the value is "undetermined"),
     * so callers never have to guess.
     */
    public function attributeValueAt(CodexAttribute $attribute, ?Event $event): ?string
    {
        if ($event === null) {
            return null;
        }

        return (new AttributeTimeline($this, $attribute))->valueAt($event)?->value;
    }
}
