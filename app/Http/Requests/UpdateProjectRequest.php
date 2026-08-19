<?php

namespace App\Http\Requests;

use App\Support\AutosavableFields;
use App\Support\CodexMediaRules;
use Illuminate\Foundation\Http\FormRequest;

/** Uses the same description rule as the autosave endpoint. */
class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /** Converts empty goal fields to null before integer validation. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'daily_word_goal' => $this->input('daily_word_goal') === '' ? null : $this->input('daily_word_goal'),
            'total_word_goal' => $this->input('total_word_goal') === '' ? null : $this->input('total_word_goal'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => AutosavableFields::validationRule('project', 'description'),

            'cover_image' => CodexMediaRules::coverRules(),

            // The daily and total goals are independent targets.
            'daily_word_goal' => ['nullable', 'integer', 'min:0'],
            'total_word_goal' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
