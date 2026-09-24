<?php

namespace App\Http\Requests;

use App\Models\Book;
use App\Rules\SiblingDestination;
use Illuminate\Foundation\Http\FormRequest;

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
                new SiblingDestination($act->book->project->books(), except: $act->book),
            ],
        ];
    }
}
