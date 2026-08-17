<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes moving a whole act to another book.
 *
 * Mirrors EpubExportRequest: the destination is a second user-owned resource,
 * so authorize() also confirms it belongs to the SAME project as the act's
 * current book — a foreign or missing book_id is a 403, never a silent
 * cross-project move. Moving a book between projects stays out of scope.
 */
class MoveActToBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $act = $this->route('act');

        if (! $this->user()->can('update', $act->book->project)) {
            return false;
        }

        $destination = Book::find($this->input('book_id'));

        return $destination !== null
            && $destination->project_id === $act->book->project_id
            && $destination->isNot($act->book);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $act = $this->route('act');

        return [
            'book_id' => [
                'required',
                'integer',
                // Same set authorize() already checked — belt and braces, the
                // same pairing DestroyActRequest keeps for move_children_to.
                Rule::exists('books', 'id')->where('project_id', $act->book->project_id),
                Rule::notIn([$act->book_id]),
            ],
        ];
    }
}
