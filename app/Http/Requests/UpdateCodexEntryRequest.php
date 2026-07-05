<?php

namespace App\Http\Requests;

use App\Rules\SanitizeHtml;
use App\Support\CodexMediaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCodexEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Walk up to the owning project (the flat route only binds the entry).
        return $this->user()->can('update', $this->route('codexEntry')->project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', new SanitizeHtml],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:255'],

            // Media uploads — same centralized rules as the store request.
            'cover' => CodexMediaRules::coverRules(),
            'reference_images' => ['nullable', 'array'],
            'reference_images.*' => CodexMediaRules::referenceImageRules(),
            'reference_files' => ['nullable', 'array'],
            'reference_files.*' => CodexMediaRules::referenceFileRules(),

            // Per-item removals fed by checkboxes in the single-save form. Each id must
            // be a media row belonging to *this* entry, so a cross-entry id is rejected
            // even for the project owner (never trust route/form ids for access scope).
            'remove_media' => ['nullable', 'array'],
            'remove_media.*' => [
                'integer',
                Rule::exists('codex_media', 'id')->where('codex_entry_id', $this->route('codexEntry')->id),
            ],

            // Attribute timeline edits post to their own endpoint (task 06), not this form.
        ];
    }
}
