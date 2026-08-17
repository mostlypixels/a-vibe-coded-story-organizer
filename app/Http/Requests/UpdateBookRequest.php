<?php

namespace App\Http\Requests;

use App\Enums\BookLanguage;
use App\Rules\ValidIsbn;
use App\Support\AutosavableFields;
use App\Support\CodexMediaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `description` and the four front/back-matter fields are also autosaved, so
 * their rules come from AutosavableFields::validationRule() rather than being
 * spelled out here — see that class's docblock for why the two paths must
 * never disagree.
 */
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
            // Optional only while this is the project's sole book — a second
            // book always needs a name of its own, so two books never show
            // the same label (see Book::hasOwnName()).
            'name' => [
                Rule::requiredIf(fn () => $book->project->books()->whereKeyNot($book->id)->exists()),
                'nullable',
                'string',
                'max:255',
            ],
            'description' => AutosavableFields::validationRule('book', 'description'),

            // The moved EPUB metadata (data-model.md "Changed foreign keys") —
            // same rules as UpdateProjectRequest applied to it before the move.
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
