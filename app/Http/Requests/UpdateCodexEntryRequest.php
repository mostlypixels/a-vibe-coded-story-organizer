<?php

namespace App\Http\Requests;

use App\Support\AutosavableFields;
use App\Support\CodexMediaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCodexEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('codexEntry')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => AutosavableFields::validationRule('codex', 'description'),
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:255'],

            'cover' => CodexMediaRules::coverRules(),
            'reference_images' => ['nullable', 'array'],
            'reference_images.*' => CodexMediaRules::referenceImageRules(),
            'reference_files' => ['nullable', 'array'],
            'reference_files.*' => CodexMediaRules::referenceFileRules(),

            // A removal ID must belong to this entry.
            'remove_media' => ['nullable', 'array'],
            'remove_media.*' => [
                'integer',
                Rule::exists('codex_media', 'id')->where('codex_entry_id', $this->route('codexEntry')->id),
            ],

        ];
    }
}
