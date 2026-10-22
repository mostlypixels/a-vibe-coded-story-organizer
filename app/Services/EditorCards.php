<?php

namespace App\Services;

use App\Enums\EditorCardState;
use App\Enums\LinkTargetType;
use App\Models\CodexEntry;
use App\Models\EditorCard;
use App\Models\Note;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The cards that the right panel of the Write page shows on page load, per section.
 *
 * Saved rows come first, newest change first. The scene's linked notes with no row follow, collapsed.
 * A `closed` row hides its card, also for a linked note. The queries do not grow with the card count.
 *
 * @phpstan-type CodexCard array{type: string, id: int, title: string, state: EditorCardState, viewUrl: string, row: array{entry: CodexEntry, attributes: Collection<int, array{name: string, value: string}>, age: ?\App\Support\Age, present: bool}}
 * @phpstan-type NoteCard array{type: string, id: int, title: string, state: EditorCardState, viewUrl: string, note: Note}
 */
class EditorCards
{
    public function __construct(private CodexAsOfResolver $resolver) {}

    /** @return array{codex: Collection<int, CodexCard>, notes: Collection<int, NoteCard>} */
    public function forScene(User $user, Scene $scene, Project $project): array
    {
        return [
            'codex' => $this->codex($user, $scene, $project),
            'notes' => $this->notes($user, $scene),
        ];
    }

    /**
     * The codex section alone, so a refresh of it loads no note.
     *
     * @return Collection<int, CodexCard>
     */
    public function codex(User $user, Scene $scene, Project $project): Collection
    {
        $open = EditorCard::query()
            ->forUserAndScene($user, $scene)
            ->whereNotNull('codex_entry_id')
            ->where('state', '!=', EditorCardState::Closed->value)
            ->with('codexEntry.cover')
            ->get();

        $entries = $open
            ->map(fn (EditorCard $row) => $row->codexEntry)
            ->filter()
            ->values();
        $cardsByEntry = $open->keyBy('codex_entry_id');

        return $this->resolver->resolveEntries($project, $entries, $scene->event)
            ->map(fn (array $row) => [
                'type' => LinkTargetType::Codex->value,
                'id' => $row['entry']->id,
                'title' => $row['entry']->name,
                'state' => $cardsByEntry[$row['entry']->id]->state,
                'viewUrl' => route('codex.show', $row['entry']),
                'row' => $row,
            ]);
    }

    /**
     * The notes section alone, so a refresh of it resolves no codex entry.
     *
     * @return Collection<int, NoteCard>
     */
    public function notes(User $user, Scene $scene): Collection
    {
        $rows = EditorCard::query()
            ->forUserAndScene($user, $scene)
            ->whereNotNull('note_id')
            ->with('note.category')
            ->get();

        $saved = $rows
            ->reject(fn (EditorCard $row) => $row->state === EditorCardState::Closed)
            ->filter(fn (EditorCard $row) => $row->note !== null)
            ->map(fn (EditorCard $row) => $this->noteCard($row->note, $row->state));

        // A note with any row, also a closed one, already has its state.
        $linked = $scene->notes()
            ->with('category')
            ->whereNotIn('notes.id', $rows->pluck('note_id'))
            ->orderBy('title')
            ->get()
            ->map(fn (Note $note) => $this->noteCard($note, EditorCardState::Collapsed));

        return $saved->concat($linked)->values();
    }

    /** @return NoteCard */
    private function noteCard(Note $note, EditorCardState $state): array
    {
        return [
            'type' => LinkTargetType::Note->value,
            'id' => $note->id,
            'title' => $note->title,
            'state' => $state,
            'viewUrl' => route('notes.show', $note),
            'note' => $note,
        ];
    }
}
