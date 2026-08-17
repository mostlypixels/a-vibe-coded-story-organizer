<?php

namespace App\Http\Requests;

use App\Support\AutosavableFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A project always already has a book (Project::booted()), so any book this
 * creates is at least the project's second — its name is never optional (see
 * Book::hasOwnName()). The rest of the book's metadata is edited later, not on
 * this form — see UpdateBookRequest.
 */
class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                Rule::requiredIf(fn () => $this->route('project')->books()->exists()),
                'nullable',
                'string',
                'max:255',
            ],
            'description' => AutosavableFields::validationRule('book', 'description'),
        ];
    }
}
