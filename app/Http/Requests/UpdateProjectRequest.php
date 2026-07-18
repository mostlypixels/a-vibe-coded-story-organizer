<?php

namespace App\Http\Requests;

use App\Enums\BookLanguage;
use App\Rules\SanitizeHtml;
use App\Rules\ValidIsbn;
use App\Rules\ValidMarkdown;
use App\Support\CodexMediaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', new SanitizeHtml],

            // Book (epub) metadata. `language` is required with a DB default of 'en', and is
            // a closed dropdown (BookLanguage) rather than free BCP-47 text — add a case there
            // when another language is supported, don't widen this back to a string rule.
            // The rest are optional. `cover_image` reuses the Codex cover file rules (same
            // mime/size list) rather than duplicating them here.
            'language' => ['required', Rule::enum(BookLanguage::class)],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'rights' => ['nullable', 'string', 'max:1000'],
            'isbn' => ['nullable', 'string', new ValidIsbn],
            'cover_image' => CodexMediaRules::coverRules(),

            // Book front/back-matter Markdown (task 02, epub-configuration). These
            // stay raw Markdown like Scene.contents — ValidMarkdown reuses the same
            // well-formedness gate, never a rich-HTML sanitizer.
            'dedication' => ['nullable', 'string', 'max:20000', new ValidMarkdown],
            'acknowledgements' => ['nullable', 'string', 'max:20000', new ValidMarkdown],
            'preface' => ['nullable', 'string', 'max:20000', new ValidMarkdown],
            'postface' => ['nullable', 'string', 'max:20000', new ValidMarkdown],
        ];
    }
}
