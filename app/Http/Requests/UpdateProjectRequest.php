<?php

namespace App\Http\Requests;

use App\Enums\BookLanguage;
use App\Rules\ValidIsbn;
use App\Support\AutosavableFields;
use App\Support\CodexMediaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Six of this form's fields are also autosaved, so their rules come from
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

            // Book (epub) metadata. `language` is required with a DB default of 'en', and is
            // a closed dropdown (BookLanguage) rather than free BCP-47 text — add a case there
            // when another language is supported, don't widen this back to a string rule.
            // The rest are optional. `cover_image` reuses the Codex cover file rules (same
            // mime/size list) rather than duplicating them here.
            'language' => ['required', Rule::enum(BookLanguage::class)],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'rights' => AutosavableFields::validationRule('project', 'rights'),
            'isbn' => ['nullable', 'string', new ValidIsbn],
            'cover_image' => CodexMediaRules::coverRules(),

            // Book front/back-matter Markdown. These stay raw Markdown like
            // Scene.contents — ValidMarkdown reuses the same well-formedness
            // gate, never a rich-HTML sanitizer.
            'dedication' => AutosavableFields::validationRule('project', 'dedication'),
            'acknowledgements' => AutosavableFields::validationRule('project', 'acknowledgements'),
            'preface' => AutosavableFields::validationRule('project', 'preface'),
            'postface' => AutosavableFields::validationRule('project', 'postface'),

            // Open-ended writing targets. No cross-validation against each
            // other — a daily goal that does not multiply out to the total
            // goal is a reasonable intent, not a mistake to reject.
            'daily_word_goal' => ['nullable', 'integer', 'min:0'],
            'total_word_goal' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
