<?php

namespace App\View\Components;

use App\Enums\LinkTargetType;
use App\Models\Note;
use App\Models\Project;
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
    public readonly LinkTargetType $type;

    public readonly Project $project;

    /** @var Collection<int, Note> */
    public readonly Collection $notes;

    /**
     * @param  Collection<int, Note>  $notes
     * @param  bool  $expanded  Show each full note body, not an excerpt.
     */
    public function __construct(Collection $notes, public readonly Model $linkable, public readonly bool $expanded = false)
    {
        $this->type = LinkTargetType::fromModel($linkable);
        $this->project = LinkTargetType::projectOf($linkable);
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
}
