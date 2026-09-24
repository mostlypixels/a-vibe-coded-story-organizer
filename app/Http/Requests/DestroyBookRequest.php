<?php

namespace App\Http\Requests;

use App\Rules\SiblingDestination;
use Illuminate\Foundation\Http\FormRequest;

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
                new SiblingDestination($book->project->books(), except: $book),
            ],
        ];
    }
}
