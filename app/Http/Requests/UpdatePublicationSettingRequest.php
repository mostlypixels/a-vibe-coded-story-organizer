<?php

namespace App\Http\Requests;

use App\Enums\ChapterTitleFormat;
use App\Enums\CodexEntryType;
use App\Enums\DividerType;
use App\Enums\TableOfContentsDepth;
use App\Rules\ValidSectionOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a save of the Export-ebook configuration form for one project's
 * PublicationSetting. Authorization mirrors PublicationSettingController@update
 * per CLAUDE.md: the write is ownership of the project, walked via the route
 * model binding.
 */
class UpdatePublicationSettingRequest extends FormRequest
{
    /**
     * Every `include_*` / `appendix_include_images` checkbox. Named once here
     * so prepareForValidation() and rules() share the same list instead of
     * repeating it (CLAUDE.md: no magic strings).
     *
     * @var array<int, string>
     */
    private const BOOLEAN_FIELDS = [
        'include_project_cover',
        'include_chapter_covers',
        'include_scene_titles',
        'include_act_descriptions',
        'include_chapter_descriptions',
        'include_scene_descriptions',
        'include_dedication',
        'include_acknowledgements',
        'include_preface',
        'include_postface',
        'include_author',
        'include_publisher',
        'include_rights',
        'include_isbn',
        'include_codex_appendix',
        'appendix_include_images',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * Checkboxes are simply absent from the request when unchecked, so every
     * boolean toggle is normalized explicitly here — otherwise unchecking one
     * would be silently ignored instead of persisting as false (the same
     * pattern as UpdateImportSettingRequest). `appendix_entry_types` defaults
     * to an empty array when no type checkbox is checked.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (self::BOOLEAN_FIELDS as $field) {
            $normalized[$field] = $this->boolean($field);
        }

        $normalized['appendix_entry_types'] = $this->input('appendix_entry_types', []);

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::configRules();
    }

    /**
     * The validation rules for one PublicationSetting payload, as a static
     * method so they are the SINGLE source of truth for both this HTTP form
     * request and the archive-import path (ProjectGraphImporter validates an
     * untrusted imported config against exactly these rules — CLAUDE.md: never
     * duplicate validation rules). No `$this`/route state is read here, so both
     * callers get an identical rule set. (Not named `validationRules()` — that
     * name is a non-static method on the FormRequest base class.)
     *
     * @return array<string, mixed>
     */
    public static function configRules(): array
    {
        $rules = [
            'chapter_title_format' => ['required', Rule::enum(ChapterTitleFormat::class)],
            'table_of_contents_depth' => ['required', Rule::enum(TableOfContentsDepth::class)],
            'divider_type' => ['required', Rule::enum(DividerType::class)],
            'appendix_entry_types' => ['array'],
            'appendix_entry_types.*' => [Rule::enum(CodexEntryType::class)],
            'section_order' => ['required', 'array', new ValidSectionOrder],
        ];

        foreach (self::BOOLEAN_FIELDS as $field) {
            $rules[$field] = ['boolean'];
        }

        return $rules;
    }
}
