<?php

namespace App\Http\Requests;

use App\Rules\SanitizeHtml;
use App\Support\CodexMediaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChapterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('chapter')->act->project);
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
                Rule::exists('acts', 'id')->where('project_id', $this->route('chapter')->act->project_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', new SanitizeHtml],

            // The chapter cover image (task 07). Reuses the same mime/size list as the
            // project cover and Codex covers rather than duplicating the constraints.
            'cover_image' => CodexMediaRules::coverRules(),
        ];
    }
}
