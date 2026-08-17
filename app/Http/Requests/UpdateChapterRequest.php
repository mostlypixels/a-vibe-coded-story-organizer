<?php

namespace App\Http\Requests;

use App\Support\AutosavableFields;
use App\Support\CodexMediaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChapterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('chapter')->act->book->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'act_id' => [
                'required',
                'integer',
                // An act belongs to a book, so the project's acts are the acts of
                // its books.
                Rule::exists('acts', 'id')->whereIn(
                    'book_id',
                    $this->route('chapter')->act->book->project->books()->pluck('id')
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => AutosavableFields::validationRule('chapter', 'description'),

            // The chapter cover image. Reuses the same mime/size list as the
            // project cover and Codex covers rather than duplicating the constraints.
            'cover_image' => CodexMediaRules::coverRules(),
        ];
    }
}
