<?php

namespace App\Enums;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The kinds of entity a note can link to. Routes, validation, the picker and the
 * archive all read this list, so a new kind is added here only.
 */
enum NoteLinkType: string
{
    case Book = 'book';
    case Act = 'act';
    case Chapter = 'chapter';
    case Scene = 'scene';
    case Event = 'event';
    case Plotline = 'plotline';
    case Codex = 'codex';

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
        };
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
        };
    }

    /** The column the picker searches and sorts by. */
    public function labelColumn(): string
    {
        return $this === self::Event ? 'title' : 'name';
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
        };
    }

    public static function fromModel(Model $model): self
    {
        foreach (self::cases() as $case) {
            $class = $case->modelClass();

            if ($model instanceof $class) {
                return $case;
            }
        }

        throw new InvalidArgumentException($model::class.' is not a linkable type.');
    }
}
