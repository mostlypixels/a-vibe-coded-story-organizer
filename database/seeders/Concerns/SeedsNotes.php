<?php

namespace Database\Seeders\Concerns;

use App\Enums\LinkTargetType;
use App\Models\Notable;
use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Project;
use App\Services\EntityLinkIndex;
use App\Services\StarterNoteCategories;
use App\Support\EntityLinks;
use Illuminate\Database\Eloquent\Model;

/**
 * Gives a demo project its starter note categories, one nested category and a few
 * notes linked to a scene and a codex entry.
 *
 * Links are `notables` rows written directly: model events are off while seeding.
 * For the same reason, the `entity_links` index is synced at the end.
 * Call it after scenes and codex entries exist. Only fictional demo projects.
 */
trait SeedsNotes
{
    /**
     * `category` is a path of names from a root category down. A `{scene}` in the body
     * becomes the `entity:` href of the note's scene.
     *
     * @param  list<array{title: string, body: string, category: list<string>, scene?: string, codex?: string}>  $notes
     */
    private function seedNotes(Project $project, array $notes): void
    {
        app(StarterNoteCategories::class)->createFor($project);

        foreach ($notes as $data) {
            $category = $this->noteCategoryAt($project, $data['category']);
            $scene = isset($data['scene']) ? $project->sceneQuery()->where('scenes.name', $data['scene'])->first() : null;
            $body = $scene === null ? $data['body'] : str_replace('{scene}', EntityLinks::href(LinkTargetType::Scene, $scene->id), $data['body']);

            $note = Note::create([
                'project_id' => $project->id,
                'note_category_id' => $category->id,
                'title' => $data['title'],
                'body' => $body,
            ]);

            $this->linkNote($note, $scene);
            $this->linkNote($note, isset($data['codex']) ? $project->codexEntries()->where('name', $data['codex'])->first() : null);
        }

        app(EntityLinkIndex::class)->syncProject($project);
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
