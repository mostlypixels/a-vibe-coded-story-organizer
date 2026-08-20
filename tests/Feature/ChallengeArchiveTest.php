<?php

namespace Tests\Feature;

use App\Enums\ChallengeRecurrence;
use App\Models\Challenge;
use App\Models\Project;
use App\Models\User;
use App\Services\StaticSiteExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Round-trip challenges through the real archive flow. */
class ChallengeArchiveTest extends TestCase
{
    use RefreshDatabase;

    /**
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

    public function test_challenges_round_trip_including_a_null_ends_on(): void
    {
        $owner = User::factory()->create();
        $source = Project::factory()->for($owner)->create();
        Challenge::factory()->for($source)->create([
            'name' => 'Fixed sprint',
            'recurrence' => ChallengeRecurrence::None,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-30',
            'target_words' => 50000,
        ]);
        Challenge::factory()->for($source)->monthly()->create([
            'name' => 'Every month',
            'starts_on' => '2026-01-10',
            'target_words' => 20000,
        ]);

        $imported = $this->exportThenImport($source);

        $this->assertSame(2, $imported->challenges()->count());

        $fixed = $imported->challenges()->where('name', 'Fixed sprint')->sole();
        $this->assertSame(ChallengeRecurrence::None, $fixed->recurrence);
        $this->assertSame('2026-08-01', $fixed->starts_on->toDateString());
        $this->assertSame('2026-08-30', $fixed->ends_on->toDateString());
        $this->assertSame(50000, $fixed->target_words);

        $monthly = $imported->challenges()->where('name', 'Every month')->sole();
        $this->assertSame(ChallengeRecurrence::Monthly, $monthly->recurrence);
        $this->assertSame('2026-01-10', $monthly->starts_on->toDateString());
        $this->assertNull($monthly->ends_on);
        $this->assertSame(20000, $monthly->target_words);

        // Ownership: imported challenges belong to the NEW project only.
        $this->assertNotSame($source->id, $imported->id);
        $this->assertSame(0, $source->challenges()->where('project_id', $imported->id)->count());
    }

    public function test_an_archive_without_a_challenges_section_imports_as_none_not_an_error(): void
    {
        $owner = User::factory()->create();
        $source = Project::factory()->for($owner)->create();

        $zipPath = app(StaticSiteExporter::class)->export($source, includeMedia: false);
        $this->tempFiles[] = $zipPath;

        // Pre-feature archives never wrote data/challenges.json at all;
        // simulate that by stripping the entry the real exporter added.
        $zip = new \ZipArchive;
        $zip->open($zipPath);
        $zip->deleteName('data/challenges.json');
        $zip->close();

        $importer = User::factory()->create();

        $this->actingAs($importer)
            ->post(route('admin.data.import'), [
                'archive' => new UploadedFile($zipPath, 'export.zip', 'application/zip', null, true),
            ])
            ->assertSessionHasNoErrors();

        $imported = $importer->projects()->sole();

        $this->assertSame(0, $imported->challenges()->count());
    }

    private function exportThenImport(Project $source): Project
    {
        $zipPath = app(StaticSiteExporter::class)->export($source, includeMedia: false);
        $this->tempFiles[] = $zipPath;

        $importer = User::factory()->create();

        $this->actingAs($importer)
            ->post(route('admin.data.import'), [
                'archive' => new UploadedFile($zipPath, 'export.zip', 'application/zip', null, true),
            ])
            ->assertSessionHasNoErrors();

        return $importer->projects()->sole();
    }
}
