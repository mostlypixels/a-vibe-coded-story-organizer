<?php

namespace App\View\Components;

use App\Enums\NoteLinkType;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Note;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The notes linked to one entity, with the buttons to add and remove links.
 *
 * The caller eager-loads the notes.
 */
class NotesCard extends Component
{
    public readonly NoteLinkType $type;

    public readonly Project $project;

    /** @var Collection<int, Note> */
    public readonly Collection $notes;

    /** @param Collection<int, Note> $notes */
    public function __construct(Collection $notes, public readonly Model $linkable)
    {
        $this->type = NoteLinkType::fromModel($linkable);
        $this->project = $this->projectOf($linkable);
        $this->notes = $notes->sortBy(fn ($note) => mb_strtolower($note->title))->values();
    }

    /** The "New note" URL: the create form with this entity pre-linked. */
    public function createUrl(): string
    {
        return route('projects.notes.create', [
            'project' => $this->project,
            'link' => $this->type->value.':'.$this->linkable->getKey(),
        ]);
    }

    public function render(): View
    {
        return view('components.notes-card');
    }

    private function projectOf(Model $linkable): Project
    {
        return match (true) {
            $linkable instanceof Act => $linkable->book->project,
            $linkable instanceof Chapter, $linkable instanceof Scene => $linkable->project(),
            $linkable instanceof Book, $linkable instanceof Event, $linkable instanceof Plotline, $linkable instanceof CodexEntry => $linkable->project,
            default => throw new \InvalidArgumentException($linkable::class.' is not a linkable type.'),
        };
    }
}
