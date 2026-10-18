<?php

namespace App\Models;

use App\Enums\EditorCardState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * The state of one codex or note card on the Write page, per user and per scene.
 * Exactly one of `codex_entry_id` and `note_id` is set.
 */
class EditorCard extends Model
{
    protected $fillable = [
        'user_id',
        'scene_id',
        'codex_entry_id',
        'note_id',
        'state',
    ];

    protected $casts = [
        'state' => EditorCardState::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (EditorCard $card): void {
            if (($card->codex_entry_id === null) === ($card->note_id === null)) {
                throw new LogicException('An editor card needs exactly one of a codex entry and a note.');
            }
        });
    }

    /**
     * Create or update the one row for this user, scene and target.
     * The unique index cannot do this alone: it treats a NULL target as distinct.
     */
    public static function saveState(User $user, Scene $scene, CodexEntry|Note $target, EditorCardState $state): self
    {
        return static::updateOrCreate([
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'codex_entry_id' => $target instanceof CodexEntry ? $target->id : null,
            'note_id' => $target instanceof Note ? $target->id : null,
        ], ['state' => $state]);
    }

    /**
     * The user's cards for one scene, newest change first.
     *
     * @param  Builder<EditorCard>  $query
     * @return Builder<EditorCard>
     */
    public function scopeForUserAndScene(Builder $query, User $user, Scene $scene): Builder
    {
        return $query
            ->where('user_id', $user->id)
            ->where('scene_id', $scene->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Scene, $this> */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /** @return BelongsTo<CodexEntry, $this> */
    public function codexEntry(): BelongsTo
    {
        return $this->belongsTo(CodexEntry::class);
    }

    /** @return BelongsTo<Note, $this> */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }
}
