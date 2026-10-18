<?php

namespace App\Enums;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Note;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * The kinds of entity a link can point to: a note link or an `entity:` text link.
 * Routes, validation, the pickers and the archive all read this list, so a new kind
 * is added here only. The values are stored in `notables` rows and archives: never
 * change them.
 */
enum LinkTargetType: string
{
    case Book = 'book';
    case Act = 'act';
    case Chapter = 'chapter';
    case Scene = 'scene';
    case Event = 'event';
    case Plotline = 'plotline';
    case Codex = 'codex';
    case Note = 'note';

    /** A picker shows this many candidates at most. */
    public const CANDIDATE_LIMIT = 20;

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Book => Book::class,
            self::Act => Act::class,
            self::Chapter => Chapter::class,
            self::Scene => Scene::class,
            self::Event => Event::class,
            self::Plotline => Plotline::class,
            self::Codex => CodexEntry::class,
            self::Note => Note::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Book => 'Book',
            self::Act => 'Act',
            self::Chapter => 'Chapter',
            self::Scene => 'Scene',
            self::Event => 'Event',
            self::Plotline => 'Plotline',
            self::Codex => 'Codex entry',
            self::Note => 'Note',
        };
    }

    public function showRoute(): string
    {
        return match ($this) {
            self::Book => 'books.show',
            self::Act => 'acts.show',
            self::Chapter => 'chapters.show',
            self::Scene => 'scenes.show',
            self::Event => 'events.show',
            self::Plotline => 'plotlines.show',
            self::Codex => 'codex.show',
            self::Note => 'notes.show',
        };
    }

    /**
     * True when a note-link pivot row can point at this type. A note links to
     * another note only through an `entity:` text link.
     */
    public function noteLinkable(): bool
    {
        return $this !== self::Note;
    }

    /** @return list<self> */
    public static function noteLinkableCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type): bool => $type->noteLinkable()));
    }

    /** The `Note` relation that holds links of this type. */
    public function noteRelation(): string
    {
        return match ($this) {
            self::Book => 'books',
            self::Act => 'acts',
            self::Chapter => 'chapters',
            self::Scene => 'scenes',
            self::Event => 'events',
            self::Plotline => 'plotlines',
            self::Codex => 'codexEntries',
            self::Note => throw new LogicException('A note is not a note-link target.'),
        };
    }

    /** The column the picker searches and sorts by. */
    public function labelColumn(): string
    {
        return match ($this) {
            self::Event, self::Note => 'title',
            default => 'name',
        };
    }

    /** The name to show for a row of this type. A book without a name shows the project's. */
    public function labelFor(Model $model): string
    {
        return $model instanceof Book ? $model->displayName() : (string) $model->getAttribute($this->labelColumn());
    }

    /**
     * The rows of this type in the project. The only safe way to resolve a link
     * target: a raw id from a request is never trusted.
     *
     * @return Builder<covariant Model>
     */
    public function queryFor(Project $project): Builder
    {
        return match ($this) {
            self::Book => Book::query()->where('books.project_id', $project->id),
            self::Act => Act::query()->whereIn('acts.book_id', Book::query()->select('books.id')->where('books.project_id', $project->id)),
            self::Chapter => $project->chapterQuery(),
            self::Scene => $project->sceneQuery(),
            self::Event => Event::query()->where('events.project_id', $project->id),
            self::Plotline => Plotline::query()->where('plotlines.project_id', $project->id),
            self::Codex => CodexEntry::query()->where('codex_entries.project_id', $project->id),
            self::Note => Note::query()->where('notes.project_id', $project->id),
        };
    }

    /**
     * Rows of this type whose label contains `$search`, as picker candidates.
     * Both candidate endpoints use it, so the limit and search rule stay equal.
     *
     * @return list<array{type: string, id: int, label: string}>
     */
    public function candidates(Project $project, ?string $search, int $limit = self::CANDIDATE_LIMIT): array
    {
        $column = $this->labelColumn();

        return $this->queryFor($project)
            ->when(filled($search), fn (Builder $query) => $this->whereMatches($query, $column, (string) $search))
            ->orderBy($column)
            ->limit($limit)
            ->get()
            ->map(function (Model $row) use ($project): array {
                // displayName() falls back to the project's name.
                if ($row instanceof Book) {
                    $row->setRelation('project', $project);
                }

                return ['type' => $this->value, 'id' => $row->getKey(), 'label' => $this->labelFor($row)];
            })
            ->values()
            ->all();
    }

    /**
     * The label column matches. A codex entry also matches through its aliases,
     * and the project scope stays outside the `or` group.
     *
     * @param  Builder<covariant Model>  $query
     */
    private function whereMatches(Builder $query, string $column, string $search): void
    {
        if ($this !== self::Codex) {
            LikeSearch::whereContains($query, $column, $search);

            return;
        }

        $query->where(function (Builder $group) use ($column, $search): void {
            LikeSearch::whereContains($group, 'codex_entries.'.$column, $search);
            $group->orWhereHas('aliases', fn (Builder $aliases) => LikeSearch::whereContains($aliases, 'codex_aliases.alias', $search));
        });
    }

    /** The project that owns a target row. */
    public static function projectOf(Model $model): Project
    {
        return match (true) {
            $model instanceof Act => $model->book->project,
            $model instanceof Chapter, $model instanceof Scene => $model->project(),
            $model instanceof Book, $model instanceof Event, $model instanceof Plotline, $model instanceof CodexEntry, $model instanceof Note => $model->project,
            default => throw new InvalidArgumentException($model::class.' is not a linkable type.'),
        };
    }

    public static function fromModel(Model $model): self
    {
        return self::tryFromModel($model)
            ?? throw new InvalidArgumentException($model::class.' is not a linkable type.');
    }

    /** Null for a model that no link can point to, for example a Project. */
    public static function tryFromModel(Model $model): ?self
    {
        foreach (self::cases() as $case) {
            $class = $case->modelClass();

            if ($model instanceof $class) {
                return $case;
            }
        }

        return null;
    }
}
