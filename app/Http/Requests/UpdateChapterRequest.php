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
                // A chapter moves between the acts of its own book. Moving a whole
                // act to another book is the act edit page's job.
                Rule::exists('acts', 'id')->where('book_id', $this->route('chapter')->act->book_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => AutosavableFields::validationRule('chapter', 'description'),

            // The chapter cover image. Reuses the same mime/size list as the
            // project cover and Codex covers rather than duplicating the constraints.
            'cover_image' => CodexMediaRules::coverRules(),
        ];
    }
}
