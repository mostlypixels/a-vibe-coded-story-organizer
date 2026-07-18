<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\PublicationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicationSetting>
 */
class PublicationSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'include_project_cover' => true,
            'include_chapter_covers' => false,
            'include_scene_titles' => false,
            'include_act_descriptions' => false,
            'include_chapter_descriptions' => false,
            'include_scene_descriptions' => false,
            'include_dedication' => false,
            'include_acknowledgements' => false,
            'include_preface' => false,
            'include_postface' => false,
            'include_author' => true,
            'include_publisher' => true,
            'include_rights' => true,
            'include_isbn' => true,
            'chapter_title_format' => 'chapter_number_title',
            'table_of_contents_depth' => 'chapters',
            'divider_type' => 'horizontal_rule',
            'section_order' => PublicationSetting::SECTION_KEYS,
            'include_codex_appendix' => false,
            'appendix_entry_types' => [],
            'appendix_include_images' => false,
        ];
    }
}
