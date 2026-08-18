<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The destination book must belong to the act's current project. */
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
                Rule::exists('books', 'id')->where('project_id', $act->book->project_id),
                Rule::notIn([$act->book_id]),
            ],
        ];
    }
}
