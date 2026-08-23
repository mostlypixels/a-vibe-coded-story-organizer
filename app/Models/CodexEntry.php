<?php

namespace App\Models;

use App\Enums\CodexEntryType;
use App\Enums\CodexMediaCollection;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\SanitizesRichHtml;
use App\Services\AttributeTimeline;
use App\Services\CodexMediaService;
use App\Support\Age;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CodexEntry extends Model
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function inceptionEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

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

    public function aliases(): HasMany
    {
        return $this->hasMany(CodexAlias::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Scenes whose contents reference this entry by name or alias (whole-word match).
     * A derived cache maintained by SceneReferenceMatcher — never edited by hand.
     * The pivot has no columns of its own (plain belongsToMany, no pivot model).
     */
    public function referencingScenes(): BelongsToMany
    {
        return $this->belongsToMany(Scene::class, 'scene_codex_entry');
    }

    public function media(): HasMany
    {
        return $this->hasMany(CodexMedia::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(CodexAttributeValue::class);
    }

    /**
     * The cover image: the single media row in the Cover collection.
     * This is the single source of truth — there is deliberately no
     * cover_media_id FK on codex_entries.
     */
    public function cover(): HasOne
    {
        return $this->hasOne(CodexMedia::class)
            ->where('collection', CodexMediaCollection::Cover);
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
