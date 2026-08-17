<?php

namespace App\Http\Requests;

use App\Support\AutosavableFields;
use App\Support\CodexMediaRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * This form's `description` is also autosaved, so its rule comes from
 * AutosavableFields::validationRule() rather than being spelled out here — see
 * that class's docblock for why the two paths must never disagree.
 */
class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * The goal fields are plain number inputs left empty for "no goal" — an
     * empty string would otherwise fail the `integer` rule instead of clearing
     * the column, the same nudge UpdatePublicationSettingRequest applies to
     * its checkboxes.
     */
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

            // The dashboard card image. It reuses the Codex cover file rules
            // (same mime/size list) rather than duplicating them here. The EPUB
            // metadata and the EPUB cover belong to a Book — see
            // UpdateBookRequest.
            'cover_image' => CodexMediaRules::coverRules(),

            // Open-ended writing targets. No cross-validation against each
            // other — a daily goal that does not multiply out to the total
            // goal is a reasonable intent, not a mistake to reject.
            'daily_word_goal' => ['nullable', 'integer', 'min:0'],
            'total_word_goal' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
