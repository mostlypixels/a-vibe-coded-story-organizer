<?php

namespace App\Http\Requests;

use App\Enums\ChapterTitleFormat;
use App\Enums\CodexEntryType;
use App\Enums\DividerType;
use App\Enums\TableOfContentsDepth;
use App\Rules\ValidSectionOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates one book's EPUB publication settings. */
class UpdatePublicationSettingRequest extends FormRequest
{
    /** @var array<int, string> Every Boolean setting on the form. */
    private const BOOLEAN_FIELDS = [
        'include_book_cover',
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
        return $this->user()->can('update', $this->route('book')->project);
    }

    /** Converts absent checkboxes to false and absent appendix types to an empty array. */
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
     * Shares one rule set with the archive importer.
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
