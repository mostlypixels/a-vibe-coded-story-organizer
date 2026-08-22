<?php

namespace Tests\Feature;

use App\Enums\Genre;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\User;
use App\Services\AttributeTimeline;
use App\Services\SeedsGenreBundle;
use App\Support\Bundles\Bundles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SeedsGenreBundleTest extends TestCase
{
    use RefreshDatabase;

    public function test_fantasy_seed_creates_one_book_with_the_bundle_content(): void
    {
        $user = User::factory()->create();

        $project = app(SeedsGenreBundle::class)->seed($user, Genre::Fantasy, 'Dragon tale');

        $this->assertSame(Genre::Fantasy, $project->genre);
        $this->assertSame('Dragon tale', $project->name);

        // The created hook makes exactly one book; the seed must not add a second.
        $this->assertCount(1, $project->books);

        $bundle = Bundles::for(Genre::Fantasy);

        foreach ($bundle->attributes() as $attribute) {
            $stored = $project->codexAttributes()->where('name', $attribute->name)->first();
            $this->assertNotNull($stored, "missing attribute {$attribute->name}");
            $this->assertEqualsCanonicalizing(
                $attribute->appliesTo,
                $stored->applies_to->all(),
            );
        }

        foreach ($bundle->tags() as $tagName) {
            $this->assertDatabaseHas('tags', ['project_id' => $project->id, 'name' => $tagName]);
        }

        foreach ($bundle->sampleEntries() as $sample) {
            $this->assertDatabaseHas('codex_entries', [
                'project_id' => $project->id,
                'type' => $sample->type->value,
                'name' => $sample->name,
            ]);
        }
    }

    public function test_seeded_attribute_values_resolve_at_start(): void
    {
        $user = User::factory()->create();

        $project = app(SeedsGenreBundle::class)->seed($user, Genre::Fantasy, 'Dragon tale');
        $startEvent = $project->startEvent();

        foreach (Bundles::for(Genre::Fantasy)->sampleEntries() as $sample) {
            $entry = $project->codexEntries()->where('name', $sample->name)->firstOrFail();

            foreach ($sample->attributeValues as $attributeName => $value) {
                $attribute = $project->codexAttributes()->where('name', $attributeName)->firstOrFail();

                $resolved = (new AttributeTimeline($entry, $attribute))->valueAt($startEvent);

                $this->assertNotNull($resolved, "no Start value for {$attributeName}");
                $this->assertSame($value, $resolved->value);
            }
        }
    }

    public function test_fantasy_seed_builds_the_act_and_chapter_skeleton(): void
    {
        $user = User::factory()->create();

        $project = app(SeedsGenreBundle::class)->seed($user, Genre::Fantasy, 'Dragon tale');
        $book = $project->books()->first();

        foreach (Bundles::for(Genre::Fantasy)->skeleton() as $actIndex => $act) {
            $actModel = $book->acts()->where('name', $act->name)->firstOrFail();
            $this->assertSame($actIndex + 1, $actModel->position);

            foreach ($act->chapters as $chapterIndex => $chapterName) {
                $chapter = $actModel->chapters()->where('name', $chapterName)->firstOrFail();
                $this->assertSame($chapterIndex + 1, $chapter->position);
            }
        }
    }

    public function test_blank_seed_creates_only_the_project_scaffold(): void
    {
        $user = User::factory()->create();

        $project = app(SeedsGenreBundle::class)->seed($user, Genre::Blank, 'Empty book');

        $this->assertSame(Genre::Blank, $project->genre);
        $this->assertCount(1, $project->books);
        $this->assertSame(0, $project->codexAttributes()->count());
        $this->assertSame(0, $project->codexEntries()->count());
        $this->assertSame(0, $project->tags()->count());
        $this->assertSame(0, $project->books()->first()->acts()->count());

        // Scaffold from the created hook still present.
        $this->assertTrue($project->plotlines()->where('is_main', true)->exists());
        $this->assertNotNull($project->startEvent());
        $this->assertNotNull($project->endEvent());
    }

    public function test_a_failure_mid_seed_rolls_back_the_project(): void
    {
        $user = User::factory()->create();

        // Force a failure after the project row is inserted, while the bundle applies.
        CodexAttribute::creating(function (): void {
            throw new RuntimeException('boom');
        });

        try {
            app(SeedsGenreBundle::class)->seed($user, Genre::Fantasy, 'Doomed');
            $this->fail('Expected the seed to throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertDatabaseMissing('projects', ['name' => 'Doomed']);
        $this->assertSame(0, CodexEntry::count());
    }
}
