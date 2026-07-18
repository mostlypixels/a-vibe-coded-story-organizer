<?php

namespace Tests\Unit;

use App\Enums\ChapterTitleFormat;
use App\Enums\DividerType;
use App\Enums\TableOfContentsDepth;
use App\Models\Project;
use App\Models\PublicationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_attributes_match_overview_defaults(): void
    {
        $project = Project::factory()->create();
        $setting = PublicationSetting::factory()->create(['project_id' => $project->id]);

        // Metadata toggles default to true
        $this->assertTrue($setting->include_project_cover);
        $this->assertTrue($setting->include_author);
        $this->assertTrue($setting->include_publisher);
        $this->assertTrue($setting->include_rights);
        $this->assertTrue($setting->include_isbn);

        // New rendering toggles default to false
        $this->assertFalse($setting->include_scene_titles);
        $this->assertFalse($setting->include_act_descriptions);
        $this->assertFalse($setting->include_chapter_descriptions);
        $this->assertFalse($setting->include_scene_descriptions);

        // Front/back matter toggles default to false
        $this->assertFalse($setting->include_dedication);
        $this->assertFalse($setting->include_acknowledgements);
        $this->assertFalse($setting->include_preface);
        $this->assertFalse($setting->include_postface);

        // Format choices default to v1 behaviour
        $this->assertSame(ChapterTitleFormat::ChapterNumberTitle, $setting->chapter_title_format);
        $this->assertSame(TableOfContentsDepth::Chapters, $setting->table_of_contents_depth);
        $this->assertSame(DividerType::HorizontalRule, $setting->divider_type);

        // Appendix options default to off/empty
        $this->assertFalse($setting->include_codex_appendix);
        $this->assertSame([], $setting->appendix_entry_types);
        $this->assertFalse($setting->appendix_include_images);
    }

    public function test_enum_casts_round_trip(): void
    {
        $project = Project::factory()->create();
        $setting = PublicationSetting::factory()->create([
            'project_id' => $project->id,
            'chapter_title_format' => 'number_title',
            'table_of_contents_depth' => 'scenes',
            'divider_type' => 'decorative',
        ]);

        $reloaded = PublicationSetting::find($setting->id);

        $this->assertInstanceOf(ChapterTitleFormat::class, $reloaded->chapter_title_format);
        $this->assertSame(ChapterTitleFormat::NumberTitle, $reloaded->chapter_title_format);

        $this->assertInstanceOf(TableOfContentsDepth::class, $reloaded->table_of_contents_depth);
        $this->assertSame(TableOfContentsDepth::Scenes, $reloaded->table_of_contents_depth);

        $this->assertInstanceOf(DividerType::class, $reloaded->divider_type);
        $this->assertSame(DividerType::Decorative, $reloaded->divider_type);
    }

    public function test_json_casts_round_trip(): void
    {
        $project = Project::factory()->create();
        $setting = PublicationSetting::factory()->create([
            'project_id' => $project->id,
            'appendix_entry_types' => ['character', 'location'],
        ]);

        $reloaded = PublicationSetting::find($setting->id);

        $this->assertIsArray($reloaded->appendix_entry_types);
        $this->assertSame(['character', 'location'], $reloaded->appendix_entry_types);
    }

    public function test_public_project_relation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $setting = PublicationSetting::factory()->create(['project_id' => $project->id]);

        $reloaded = PublicationSetting::find($setting->id);

        $this->assertInstanceOf(Project::class, $reloaded->project);
        $this->assertSame($project->id, $reloaded->project->id);
    }

    public function test_publication_setting_or_default_returns_unsaved_default_when_no_row_exists(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $setting = $project->publicationSettingOrDefault();

        $this->assertInstanceOf(PublicationSetting::class, $setting);
        $this->assertNull($setting->id); // Unsaved
        $this->assertSame($project->id, $setting->project_id);

        // Default values are set
        $this->assertTrue($setting->include_project_cover);
        $this->assertFalse($setting->include_scene_titles);
        $this->assertSame(ChapterTitleFormat::ChapterNumberTitle, $setting->chapter_title_format);
    }

    public function test_publication_setting_or_default_returns_persisted_row_when_it_exists(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $persistedSetting = PublicationSetting::factory()->create([
            'project_id' => $project->id,
            'include_scene_titles' => true,
        ]);

        $setting = $project->publicationSettingOrDefault();

        $this->assertInstanceOf(PublicationSetting::class, $setting);
        $this->assertNotNull($setting->id); // Saved
        $this->assertSame($persistedSetting->id, $setting->id);
        $this->assertTrue($setting->include_scene_titles);
    }
}
