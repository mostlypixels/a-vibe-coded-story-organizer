<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The browser tab's <title>: "<project> - <app>" inside a project, the bare app
 * name outside one. Titles come from App\Support\PageTitle, fed by the same
 * project resolution the navigation uses — including the shallow child routes
 * (/scenes/{scene}/edit) that carry no {project} parameter.
 */
class PageTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_outside_a_project_show_the_app_name_alone(): void
    {
        config(['app.name' => 'AVCSO']);

        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $response->assertSee('<title>AVCSO</title>', false);
    }

    public function test_a_project_page_leads_with_the_project_name(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);

        $response = $this->actingAs($user)->get(route('projects.show', $project));

        $response->assertSee('<title>Melusine - AVCSO</title>', false);
    }

    public function test_a_shallow_child_route_still_finds_its_project(): void
    {
        config(['app.name' => 'AVCSO']);
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        $response = $this->actingAs($user)->get(route('scenes.edit', $scene));

        $response->assertSee('<title>Melusine - AVCSO</title>', false);
    }
}
