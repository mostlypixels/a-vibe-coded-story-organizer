<?php

namespace App\Models;

use App\Enums\NoteLinkType;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\SanitizesRichHtml;
use App\Models\Contracts\Revisionable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

class Note extends Model implements Revisionable
{
    use HasFactory;
    use HasRevisions;
    use SanitizesRichHtml;

    protected $fillable = [
        'project_id',
        'note_category_id',
        'title',
        'body',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<NoteCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NoteCategory::class, 'note_category_id');
    }

    /** The project that owns this note's revisions (see HasRevisions). */
    public function revisionProject(): Project
    {
        return $this->project;
    }

    /**
     * The title of a note made from a scene's old `notes` field. The scene notes
     * migration repeats this rule inline.
     */
    public static function titleForSceneNotes(string $sceneName): string
    {
        return mb_substr('Notes: '.$sceneName, 0, 255);
    }

    /** A note is titled by `title`, not `name`. */
    public static function revisionDisplayColumn(): string
    {
        return 'title';
    }

    /**
     * Sanitize the rich-HTML `body` on write.
     *
     * @return Attribute<?string, ?string>
     */
    protected function body(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $this->cleanRichHtml($value),
        );
    }

    /**
     * Links the note to an entity. Linking twice is a no-op. The caller must
     * resolve `$target` through {@see NoteLinkType::queryFor()}.
     */
    public function linkTo(Model $target): void
    {
        $relation = NoteLinkType::fromModel($target)->noteRelation();

        $this->{$relation}()->syncWithoutDetaching([$target->getKey()]);
    }

    public function unlink(NoteLinkType $type, int $id): void
    {
        $this->{$type->noteRelation()}()->detach($id);
    }

    /**
     * The linked entities, grouped by type, in the enum order. Empty types are left out.
     *
     * @return array<string, array{type: NoteLinkType, items: Collection<int, Model>}>
     */
    public function linkGroups(): array
    {
        $this->loadMissing(array_map(fn (NoteLinkType $type) => $type->noteRelation(), NoteLinkType::cases()));
        $this->books->each(fn (Book $book) => $book->setRelation('project', $this->project));

        $groups = [];

        foreach (NoteLinkType::cases() as $type) {
            /** @var Collection<int, Model> $items */
            $items = $this->getRelation($type->noteRelation());

            if ($items->isNotEmpty()) {
                $groups[$type->value] = ['type' => $type, 'items' => $items->sortBy(fn (Model $item) => mb_strtolower($type->labelFor($item)))->values()];
            }
        }

        return $groups;
    }

    /** @return MorphToMany<Book, $this> */
    public function books(): MorphToMany
    {
        return $this->morphedByMany(Book::class, 'notable');
    }

    /** @return MorphToMany<Act, $this> */
    public function acts(): MorphToMany
    {
        return $this->morphedByMany(Act::class, 'notable');
    }

    /** @return MorphToMany<Chapter, $this> */
    public function chapters(): MorphToMany
    {
        return $this->morphedByMany(Chapter::class, 'notable');
    }

    /** @return MorphToMany<Scene, $this> */
    public function scenes(): MorphToMany
    {
        return $this->morphedByMany(Scene::class, 'notable');
    }

    /** @return MorphToMany<Event, $this> */
    public function events(): MorphToMany
    {
        return $this->morphedByMany(Event::class, 'notable');
    }

    /** @return MorphToMany<Plotline, $this> */
    public function plotlines(): MorphToMany
    {
        return $this->morphedByMany(Plotline::class, 'notable');
    }

    /** @return MorphToMany<CodexEntry, $this> */
    public function codexEntries(): MorphToMany
    {
        return $this->morphedByMany(CodexEntry::class, 'notable');
    }
}
