<?php

namespace Database\Seeders\Concerns;

use App\Models\Notable;
use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Project;
use App\Services\StarterNoteCategories;
use Illuminate\Database\Eloquent\Model;

/**
 * Gives a demo project its starter note categories, one nested category and a few
 * notes linked to a scene and a codex entry.
 *
 * Links are `notables` rows written directly: model events are off while seeding.
 * Call it after scenes and codex entries exist. Only fictional demo projects.
 */
trait SeedsNotes
{
    /**
     * `category` is a path of names from a root category down.
     *
     * @param  list<array{title: string, body: string, category: list<string>, scene?: string, codex?: string}>  $notes
     */
    private function seedNotes(Project $project, array $notes): void
    {
        app(StarterNoteCategories::class)->createFor($project);

        foreach ($notes as $data) {
            $category = $this->noteCategoryAt($project, $data['category']);

            $note = Note::create([
                'project_id' => $project->id,
                'note_category_id' => $category->id,
                'title' => $data['title'],
                'body' => $data['body'],
            ]);

            $this->linkNote($note, isset($data['scene']) ? $project->sceneQuery()->where('scenes.name', $data['scene'])->first() : null);
            $this->linkNote($note, isset($data['codex']) ? $project->codexEntries()->where('name', $data['codex'])->first() : null);
        }
    }

    /** @param  list<string>  $path */
    private function noteCategoryAt(Project $project, array $path): NoteCategory
    {
        $category = null;

        foreach ($path as $name) {
            $category = $project->noteCategories()->firstOrCreate([
                'parent_id' => $category?->id,
                'name' => $name,
            ]);
        }

        return $category;
    }

    private function linkNote(Note $note, ?Model $target): void
    {
        if ($target === null) {
            return;
        }

        Notable::query()->insertOrIgnore([
            'note_id' => $note->id,
            'notable_type' => $target->getMorphClass(),
            'notable_id' => $target->getKey(),
        ]);
    }
}
