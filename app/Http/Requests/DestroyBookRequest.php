<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The destination must be a different book in the same project. */
class DestroyBookRequest extends FormRequest
{
    public function authorize(): bool
    {
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
                Rule::exists('books', 'id')->where('project_id', $book->project_id),
                Rule::notIn([$book->id]),
            ],
        ];
    }
}
