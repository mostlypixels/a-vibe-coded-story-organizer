<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates deleting a chapter, including the optional "move my scenes to another
 * chapter, then delete" choice. When `move_children_to` is omitted the delete is the
 * plain cascade (unchanged behaviour); when present it must name a *different* chapter
 * in the *same* book.
 */
class DestroyChapterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Same boundary as ChapterController::destroy() itself — authorization walks up
        // to the owning project, never a new policy (CLAUDE.md authorization rule).
        return $this->user()->can('update', $this->route('chapter')->act->book->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $chapter = $this->route('chapter');

        return [
            'move_children_to' => [
                'nullable',
                // Must be a real chapter in the same book — the same set the dialog
                // offers, so a valid choice is always one the controller can find.
                Rule::exists('chapters', 'id')->whereIn('act_id', $chapter->act->book->acts()->pluck('id')),
                // … and never the chapter being deleted itself.
                Rule::notIn([$chapter->id]),
            ],
        ];
    }
}
