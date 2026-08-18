<?php

namespace App\Http\Requests;

use App\Enums\BookLanguage;
use App\Rules\ValidIsbn;
use App\Support\AutosavableFields;
use App\Support\CodexMediaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Uses the same rich-text rules as the autosave endpoint. */
class UpdateBookRequest extends FormRequest
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
            // Only a project's sole book can use the project name.
            'name' => [
                Rule::requiredIf(fn () => $book->project->books()->whereKeyNot($book->id)->exists()),
                'nullable',
                'string',
                'max:255',
            ],
            'description' => AutosavableFields::validationRule('book', 'description'),

            'language' => ['required', Rule::enum(BookLanguage::class)],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', new ValidIsbn],
            'cover_image' => CodexMediaRules::coverRules(),

            'rights' => AutosavableFields::validationRule('book', 'rights'),
            'dedication' => AutosavableFields::validationRule('book', 'dedication'),
            'acknowledgements' => AutosavableFields::validationRule('book', 'acknowledgements'),
            'preface' => AutosavableFields::validationRule('book', 'preface'),
            'postface' => AutosavableFields::validationRule('book', 'postface'),
        ];
    }
}
