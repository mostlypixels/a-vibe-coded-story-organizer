<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\WordCountSnapshot;
use App\Services\StaticSiteExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A project's two word goals and its `word_count_snapshots` rows travel in the
 * export .zip and are restored on import (word-count-goals spec, task 11).
 *
 * Exercises the real stack: the archive is built by the real
 * {@see StaticSiteExporter} and restored through the real HTTP import route.
 */
class WordCountGoalsArchiveTest extends TestCase
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

    public function test_goals_and_snapshots_round_trip_and_belong_to_the_importing_user(): void
    {
        $owner = User::factory()->create();
        $source = Project::factory()->for($owner)->create([
            'daily_word_goal' => 500,
            'total_word_goal' => 80000,
        ]);
        WordCountSnapshot::factory()->for($source)->create(['recorded_on' => '2026-08-01', 'word_count' => 1200]);
        WordCountSnapshot::factory()->for($source)->create(['recorded_on' => '2026-08-02', 'word_count' => 1900]);
        WordCountSnapshot::factory()->for($source)->create(['recorded_on' => '2026-08-03', 'word_count' => 1750]);

        $imported = $this->exportThenImport($source);

        $this->assertSame(500, $imported->daily_word_goal);
        $this->assertSame(80000, $imported->total_word_goal);

        // Ownership: the imported snapshots belong to the NEW project, and to
        // no one else's — never the source project's rows reused.
        $this->assertNotSame($source->id, $imported->id);
        $this->assertSame(3, $imported->wordCountSnapshots()->count());
        $this->assertSame(0, $source->wordCountSnapshots()->where('project_id', $imported->id)->count());

        // The restored series matches the source's, day for day.
        $expected = $source->wordCountSnapshots()
            ->orderBy('recorded_on')
            ->get()
            ->map(fn (WordCountSnapshot $s) => [$s->recorded_on->toDateString(), $s->word_count])
            ->all();
        $actual = $imported->wordCountSnapshots()
            ->orderBy('recorded_on')
            ->get()
            ->map(fn (WordCountSnapshot $s) => [$s->recorded_on->toDateString(), $s->word_count])
            ->all();
        $this->assertSame($expected, $actual);
    }

    public function test_an_archive_without_a_snapshots_section_imports_as_none_not_an_error(): void
    {
        $owner = User::factory()->create();
        $source = Project::factory()->for($owner)->create(['daily_word_goal' => null, 'total_word_goal' => null]);

        $zipPath = app(StaticSiteExporter::class)->export($source, includeMedia: false);
        $this->tempFiles[] = $zipPath;

        // Pre-feature archives never wrote data/word-count-snapshots.json at
        // all; simulate that by stripping the entry the real exporter added.
        $zip = new \ZipArchive;
        $zip->open($zipPath);
        $zip->deleteName('data/word-count-snapshots.json');
        $zip->close();

        $importer = User::factory()->create();

        $this->actingAs($importer)
            ->post(route('admin.data.import'), [
                'archive' => new UploadedFile($zipPath, 'export.zip', 'application/zip', null, true),
            ])
            ->assertSessionHasNoErrors();

        $imported = $importer->projects()->sole();

        $this->assertNull($imported->daily_word_goal);
        $this->assertNull($imported->total_word_goal);
        $this->assertSame(0, $imported->wordCountSnapshots()->count());
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
