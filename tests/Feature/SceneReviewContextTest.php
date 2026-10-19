<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Services\SceneReviewContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneReviewContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_joins_the_lines_and_ends_with_the_scene_contents(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project->books()->first())->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create(['name' => 'Dawn', 'contents' => 'The sun rose.']);

        $context = app(SceneReviewContext::class);
        $text = $context->build($scene);

        $this->assertSame(implode("\n", $context->lines($scene)), $text);
        $this->assertStringContainsString("Project: {$project->name} (ID {$project->id})", $text);
        $this->assertStringContainsString('Scene: Dawn', $text);
        $this->assertStringEndsWith("## Scene contents (Markdown)\nThe sun rose.", $text);
    }
}
