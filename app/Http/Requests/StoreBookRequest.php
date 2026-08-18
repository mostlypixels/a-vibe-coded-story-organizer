<?php

namespace App\Http\Requests;

use App\Support\AutosavableFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A new book needs a name because each project already has its default book. */
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
