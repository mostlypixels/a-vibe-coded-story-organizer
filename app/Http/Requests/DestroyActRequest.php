<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates deleting an act, including the optional "move my chapters to another
 * act, then delete" choice. When `move_children_to` is omitted the delete is the
 * plain cascade (unchanged behaviour); when present it must name a *different* act
 * in the *same* project.
 */
class DestroyActRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Same boundary as ActController::destroy() itself — authorization walks up
        // to the owning project, never a new policy (CLAUDE.md authorization rule).
        return $this->user()->can('update', $this->route('act')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $act = $this->route('act');

        return [
            'move_children_to' => [
                'nullable',
                // Must be a real act in the same project (mirrors UpdateChapterRequest's
                // act_id scoping) …
                Rule::exists('acts', 'id')->where('project_id', $act->project->id),
                // … and never the act being deleted itself.
                Rule::notIn([$act->id]),
            ],
        ];
    }
}
