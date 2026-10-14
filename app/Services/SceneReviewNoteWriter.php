<?php

namespace App\Services;

use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Scene;
use App\Support\AutosavableFields;
use Illuminate\Support\Facades\DB;

/**
 * Writes the scene-reviewer agent's findings as a note in the project's "TODO" category.
 *
 * A scene has at most one review note. A new review replaces its body and records a
 * manual revision, so the old findings stay in the history. The note is found by its
 * link to the scene and its title prefix, so a renamed scene keeps its note.
 */
class SceneReviewNoteWriter
{
    public const CATEGORY = 'TODO';

    public const TITLE_PREFIX = 'Review: ';

    public function __construct(private readonly RevisionRecorder $recorder) {}

    public function write(Scene $scene, string $body): Note
    {
        $project = $scene->project();
        $title = mb_substr(self::TITLE_PREFIX.$scene->name, 0, 255);

        return DB::transaction(function () use ($scene, $project, $title, $body): Note {
            $category = $this->category($project->id);
            $note = $this->existingNote($scene, $category);

            if ($note === null) {
                $note = $project->notes()->create([
                    'note_category_id' => $category->id,
                    'title' => $title,
                    'body' => $body,
                ]);
                $note->linkTo($scene);

                return $note;
            }

            $before = AutosavableFields::snapshotFieldsBeforeUpdate($note, ['title' => $title, 'body' => $body]);
            $heldSince = $note->updated_at;
            $note->update(['title' => $title, 'body' => $body]);
            $this->recorder->recordManualChanges($note, $before, $project->user, __('Scene review'), $heldSince);

            return $note;
        });
    }

    /** A root category wins over a nested one with the same name. */
    private function category(int $projectId): NoteCategory
    {
        return NoteCategory::query()
            ->where('project_id', $projectId)
            ->whereRaw('lower(name) = ?', [mb_strtolower(self::CATEGORY)])
            ->orderByRaw('parent_id is not null')
            ->orderBy('id')
            ->first()
            ?? NoteCategory::create(['project_id' => $projectId, 'name' => self::CATEGORY]);
    }

    private function existingNote(Scene $scene, NoteCategory $category): ?Note
    {
        return $scene->notes()
            ->where('note_category_id', $category->id)
            ->where('title', 'like', self::TITLE_PREFIX.'%')
            ->orderBy('notes.id')
            ->first();
    }
}
