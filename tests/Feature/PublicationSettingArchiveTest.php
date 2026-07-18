<?php

namespace Tests\Feature;

use App\Enums\ChapterTitleFormat;
use App\Enums\CodexEntryType;
use App\Enums\DividerType;
use App\Enums\ImportPhase;
use App\Enums\TableOfContentsDepth;
use App\Models\Import;
use App\Models\Project;
use App\Models\PublicationSetting;
use App\Models\Scene;
use App\Models\User;
use App\Services\StaticSiteExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Task 05 (epub-configuration): the project's PublicationSetting travels in the
 * export .zip and is restored on import — validated as UNTRUSTED input, so a
 * malformed config falls back to defaults and the content still imports.
 *
 * Exercises the real stack end-to-end: the config is serialized by the real
 * {@see StaticSiteExporter} and restored through the real HTTP import route
 * (ImportController → ProjectImporter → ProjectGraphImporter), with the security
 * gate and content sanitizer running for real. Hand-built malformed configs are
 * injected into a genuine exported archive so ArchiveValidator still passes them
 * (the file is allow-listed; only the importer judges its content).
 */
class PublicationSettingArchiveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Temp export zips the exporter writes under storage/app/exports (outside
     * Storage::fake), removed in tearDown.
     *
     * @var array<int, string>
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // A customised setting round-trips equal
    // ------------------------------------------------------------------

    public function test_a_customised_publication_setting_round_trips_equal(): void
    {
        $owner = User::factory()->create();
        $source = Project::factory()->for($owner)->create();

        // A setting that differs from every default: cover/metadata off, both
        // enum columns off their defaults, the section list reordered, and the
        // appendix fully configured.
        $reordered = ['title', 'dedication', 'acknowledgements', 'preface', 'body', 'toc', 'postface', 'appendix'];
        PublicationSetting::factory()->for($source)->create([
            'include_project_cover' => false,
            'include_scene_titles' => true,
            'include_act_descriptions' => true,
            'include_chapter_descriptions' => true,
            'include_scene_descriptions' => true,
            'include_dedication' => true,
            'include_acknowledgements' => true,
            'include_preface' => true,
            'include_postface' => true,
            'include_author' => false,
            'include_publisher' => false,
            'include_rights' => false,
            'include_isbn' => false,
            'chapter_title_format' => ChapterTitleFormat::Title,
            'table_of_contents_depth' => TableOfContentsDepth::Scenes,
            'divider_type' => DividerType::Decorative,
            'section_order' => $reordered,
            'include_codex_appendix' => true,
            'appendix_entry_types' => [CodexEntryType::Character->value, CodexEntryType::Location->value],
            'appendix_include_images' => true,
        ]);

        $imported = $this->exportThenImport($source);
        $setting = $imported->publicationSetting()->firstOrFail();

        $this->assertFalse($setting->include_project_cover);
        $this->assertTrue($setting->include_scene_titles);
        $this->assertTrue($setting->include_act_descriptions);
        $this->assertTrue($setting->include_chapter_descriptions);
        $this->assertTrue($setting->include_scene_descriptions);
        $this->assertTrue($setting->include_dedication);
        $this->assertTrue($setting->include_acknowledgements);
        $this->assertTrue($setting->include_preface);
        $this->assertTrue($setting->include_postface);
        $this->assertFalse($setting->include_author);
        $this->assertFalse($setting->include_publisher);
        $this->assertFalse($setting->include_rights);
        $this->assertFalse($setting->include_isbn);
        $this->assertSame(ChapterTitleFormat::Title, $setting->chapter_title_format);
        $this->assertSame(TableOfContentsDepth::Scenes, $setting->table_of_contents_depth);
        $this->assertSame(DividerType::Decorative, $setting->divider_type);
        $this->assertSame($reordered, $setting->section_order);
        $this->assertTrue($setting->include_codex_appendix);
        $this->assertSame(
            [CodexEntryType::Character->value, CodexEntryType::Location->value],
            $setting->appendix_entry_types,
        );
        $this->assertTrue($setting->appendix_include_images);

        // The imported setting belongs to the NEW project, not the source's.
        $this->assertSame($imported->id, $setting->project_id);
    }

    // ------------------------------------------------------------------
    // No saved setting round-trips to the lazy default
    // ------------------------------------------------------------------

    public function test_a_project_without_a_setting_round_trips_to_the_default(): void
    {
        $owner = User::factory()->create();
        $source = Project::factory()->for($owner)->create();

        // The source never visited the config form: no row exists.
        $this->assertFalse($source->publicationSetting()->exists());

        $imported = $this->exportThenImport($source);

        // No row was created on import either — the project rides the lazy default.
        $this->assertFalse($imported->publicationSetting()->exists());

        $default = $imported->publicationSettingOrDefault();
        $this->assertFalse($default->exists);
        $this->assertTrue($default->include_project_cover);
        $this->assertSame(ChapterTitleFormat::ChapterNumberTitle, $default->chapter_title_format);
        $this->assertSame(PublicationSetting::SECTION_KEYS, $default->section_order);
    }

    // ------------------------------------------------------------------
    // Malformed configs fall back to default, content still imports
    // ------------------------------------------------------------------

    public function test_a_config_with_an_invalid_enum_falls_back_to_default_and_still_imports_content(): void
    {
        $imported = $this->importWithInjectedConfig(
            $this->validConfigArray(['chapter_title_format' => 'not_a_real_format']),
        );

        $this->assertFalse($imported->publicationSetting()->exists(), 'an invalid enum discards the whole config');
        $this->assertProjectContentImported($imported);
    }

    public function test_a_config_that_is_not_a_json_object_falls_back_to_default(): void
    {
        // A JSON array (not an object) is structurally malformed for a config.
        $imported = $this->importWithInjectedConfig('["clearly", "not", "a", "config"]');

        $this->assertFalse($imported->publicationSetting()->exists());
        $this->assertProjectContentImported($imported);
    }

    public function test_a_config_missing_required_section_order_falls_back_to_default(): void
    {
        $config = $this->validConfigArray();
        unset($config['section_order']);

        $imported = $this->importWithInjectedConfig($config);

        $this->assertFalse($imported->publicationSetting()->exists());
        $this->assertProjectContentImported($imported);
    }

    // ------------------------------------------------------------------
    // Unknown appendix types are dropped, the rest of the config survives
    // ------------------------------------------------------------------

    public function test_unknown_appendix_entry_types_are_dropped_rather_than_failing_the_config(): void
    {
        $config = $this->validConfigArray([
            'include_codex_appendix' => true,
            'appendix_entry_types' => [CodexEntryType::Character->value, 'not_a_codex_type'],
        ]);

        $imported = $this->importWithInjectedConfig($config);
        $setting = $imported->publicationSetting()->firstOrFail();

        // The bogus type is silently dropped; the valid one and the rest of the
        // config are still applied.
        $this->assertSame([CodexEntryType::Character->value], $setting->appendix_entry_types);
        $this->assertTrue($setting->include_codex_appendix);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Export $source through the real service and import the zip through the real
     * HTTP route as a fresh user. Returns the imported project.
     */
    private function exportThenImport(Project $source, ?string $injectedConfig = null): Project
    {
        $zipPath = app(StaticSiteExporter::class)->export($source, includeMedia: false);
        $this->tempFiles[] = $zipPath;

        if ($injectedConfig !== null) {
            $this->injectFile($zipPath, 'data/publication-setting.json', $injectedConfig);
        }

        $importer = User::factory()->create();

        $this->actingAs($importer)
            ->post(route('admin.data.import'), [
                'archive' => new UploadedFile($zipPath, 'export.zip', 'application/zip', null, true),
            ])
            ->assertSessionHasNoErrors();

        return $importer->projects()->sole();
    }

    /**
     * Seed a project with real content (a scene with prose), export it WITHOUT a
     * setting, overwrite the archive's publication-setting.json with the given
     * hand-built (malformed) config, then import. Returns the imported project.
     *
     * @param  string|array<string, mixed>  $config
     */
    private function importWithInjectedConfig(string|array $config): Project
    {
        $owner = User::factory()->create();
        $source = Project::factory()->for($owner)->create(['name' => 'Malformed Config Source']);
        $act = $source->acts()->create(['name' => 'Act One', 'position' => 1]);
        $chapter = $act->chapters()->create(['name' => 'Chapter One', 'position' => 1]);
        Scene::factory()->for($chapter)->create([
            'name' => 'Scene One', 'position' => 1, 'contents' => 'The opening prose.',
        ]);

        $payload = is_array($config)
            ? json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $config;

        return $this->exportThenImport($source, $payload);
    }

    /**
     * A structurally-valid serialized config (all keys present, all values legal),
     * as the exporter would write for a customised setting. Overrides let a test
     * corrupt exactly one field.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validConfigArray(array $overrides = []): array
    {
        return array_merge([
            'include_project_cover' => true,
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
            'chapter_title_format' => ChapterTitleFormat::ChapterNumberTitle->value,
            'table_of_contents_depth' => TableOfContentsDepth::Chapters->value,
            'divider_type' => DividerType::HorizontalRule->value,
            'section_order' => PublicationSetting::SECTION_KEYS,
            'include_codex_appendix' => false,
            'appendix_entry_types' => [],
            'appendix_include_images' => false,
        ], $overrides);
    }

    /**
     * Assert the imported project kept its content despite a rejected config —
     * the point of "config is a preference, never fail the whole import".
     */
    private function assertProjectContentImported(Project $imported): void
    {
        $this->assertSame('Malformed Config Source', $imported->name);
        $scene = $imported->acts()->firstOrFail()
            ->chapters()->firstOrFail()
            ->scenes()->firstOrFail();
        $this->assertSame('The opening prose.', $scene->contents);
        $this->assertSame(ImportPhase::Completed, Import::firstOrFail()->phase);
    }

    /**
     * Add or overwrite one entry inside an existing zip on disk.
     */
    private function injectFile(string $zipPath, string $entry, string $contents): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true, 'could not reopen the export zip to inject a config');
        $zip->addFromString($entry, $contents);
        $zip->close();
    }
}
