<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates deleting an act, including the optional "move my chapters to another
 * act, then delete" choice. When `move_children_to` is omitted the delete is the
 * plain cascade (unchanged behaviour); when present it must name a *different* act
 * in the *same* book.
 */
class DestroyActRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Same boundary as ActController::destroy() itself — authorization walks up
        // to the owning project, never a new policy (CLAUDE.md authorization rule).
        return $this->user()->can('update', $this->route('act')->book->project);
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
                // Must be a real act in the same book — the same set the dialog
                // offers, so a valid choice is always one the controller can find.
                Rule::exists('acts', 'id')->where('book_id', $act->book_id),
                // … and never the act being deleted itself.
                Rule::notIn([$act->id]),
            ],
        ];
    }
}
