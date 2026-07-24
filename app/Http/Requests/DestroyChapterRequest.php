<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates deleting a chapter, including the optional "move my scenes to another
 * chapter, then delete" choice. When `move_children_to` is omitted the delete is the
 * plain cascade (unchanged behaviour); when present it must name a *different* chapter
 * in the *same* project.
 */
class DestroyChapterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Same boundary as ChapterController::destroy() itself — authorization walks up
        // to the owning project, never a new policy (CLAUDE.md authorization rule).
        return $this->user()->can('update', $this->route('chapter')->act->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $chapter = $this->route('chapter');
        $project = $chapter->act->project;

        return [
            'move_children_to' => [
                'nullable',
                // Must be a real chapter in the same project (mirrors UpdateSceneRequest's
                // chapter_id scoping: any chapter whose act belongs to this project) …
                Rule::exists('chapters', 'id')->whereIn('act_id', $project->acts()->pluck('id')),
                // … and never the chapter being deleted itself.
                Rule::notIn([$chapter->id]),
            ],
        ];
    }
}
