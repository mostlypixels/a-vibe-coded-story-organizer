<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates deleting a book, including the optional "move my acts to another
 * book, then delete" choice — the book-level twin of DestroyActRequest. When
 * `move_children_to` is omitted the delete is the plain cascade; when present
 * it must name a *different* book in the *same* project.
 */
class DestroyBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Same boundary as BookController::destroy() itself — authorization
        // walks up to the owning project, never a new policy (CLAUDE.md
        // authorization rule).
        return $this->user()->can('update', $this->route('book')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $book = $this->route('book');

        return [
            'move_children_to' => [
                'nullable',
                // Must be a real book in the same project — the same set the
                // dialog offers, so a valid choice is always one the controller
                // can find.
                Rule::exists('books', 'id')->where('project_id', $book->project_id),
                // … and never the book being deleted itself.
                Rule::notIn([$book->id]),
            ],
        ];
    }
}
