<?php

namespace App\Models;

use App\Enums\CodexEntryType;
use App\Enums\CodexMediaCollection;
use App\Models\Concerns\SanitizesRichHtml;
use App\Services\AttributeTimeline;
use App\Services\CodexMediaService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CodexEntry extends Model
{
    use HasFactory;
    use SanitizesRichHtml;

    protected $fillable = [
        'project_id',
        'type',
        'name',
        'description',
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
