<?php

namespace Tests\Feature;

use App\Enums\FieldKind;
use App\Enums\LinkTargetType;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Note;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Services\EntityLinkResolver;
use App\Services\RevisionDiffer;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EntityLinkResolverTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{LinkTargetType}> */
    public static function linkTypes(): array
    {
        return array_combine(
            array_map(fn (LinkTargetType $case) => $case->value, LinkTargetType::cases()),
            array_map(fn (LinkTargetType $case) => [$case], LinkTargetType::cases()),
        );
    }

    private function makeFor(LinkTargetType $type, Project $project): Model
    {
        $book = $project->books()->first();

        return match ($type) {
            LinkTargetType::Book => $book,
            LinkTargetType::Act => Act::factory()->for($book)->create(),
            LinkTargetType::Chapter => Chapter::factory()->for(Act::factory()->for($book)->create())->create(),
            LinkTargetType::Scene => $this->sceneIn($project),
            LinkTargetType::Event => Event::factory()->for($project)->create(),
            LinkTargetType::Plotline => Plotline::factory()->for($project)->create(),
            LinkTargetType::Codex => CodexEntry::factory()->for($project)->create(),
            LinkTargetType::Note => Note::factory()->for($project)->create(),
        };
    }

    private function sceneIn(Project $project, array $attributes = []): Scene
    {
        $act = Act::factory()->for($project->books()->first())->create();

        return Scene::factory()->for(Chapter::factory()->for($act))->create($attributes);
    }

    private function render(string $html, Project $project): string
    {
        return app(EntityLinkResolver::class)->render($html, $project);
    }

    #[DataProvider('linkTypes')]
    public function test_a_found_link_becomes_its_route_url(LinkTargetType $type): void
    {
        $project = Project::factory()->create();
        $target = $this->makeFor($type, $project);

        $html = $this->render('<p><a href="entity:'.$type->value.'/'.$target->getKey().'" target="_blank">see</a></p>', $project);

        $this->assertSame('<p><a href="'.route($type->showRoute(), $target).'" target="_blank">see</a></p>', $html);
    }

    public function test_a_deleted_target_becomes_a_missing_span(): void
    {
        $project = Project::factory()->create();
        $note = Note::factory()->for($project)->create();
        $note->delete();

        $html = $this->render('<p><a href="entity:note/'.$note->id.'"><strong>gone</strong></a></p>', $project);

        $this->assertSame(
            '<p><span class="entity-link-missing" title="The linked item no longer exists."><strong>gone</strong></span></p>',
            $html,
        );
    }

    public function test_an_id_from_another_project_shows_nothing_of_that_project(): void
    {
        $project = Project::factory()->create();
        $foreign = Note::factory()->for(Project::factory())->create(['title' => 'Secret plans']);

        $html = $this->render('<p><a href="entity:note/'.$foreign->id.'">my text</a></p>', $project);

        $this->assertStringContainsString('<span class="entity-link-missing"', $html);
        $this->assertStringContainsString('my text', $html);
        $this->assertStringNotContainsString('Secret plans', $html);
        $this->assertStringNotContainsString(route('notes.show', $foreign), $html);
        $this->assertStringNotContainsString('<a', $html);
    }

    public function test_many_links_of_two_types_run_two_lookups(): void
    {
        $project = Project::factory()->create();
        $notes = Note::factory()->for($project)->count(4)->create();
        $events = Event::factory()->for($project)->count(3)->create();

        $html = $notes->map(fn (Note $note) => '<a href="entity:note/'.$note->id.'">n</a>')
            ->merge($events->map(fn (Event $event) => '<a href="entity:event/'.$event->id.'">e</a>'))
            ->push('<a href="entity:note/999999">missing</a>')
            ->implode(' ');

        DB::enableQueryLog();
        $rendered = $this->render($html, $project);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(2, $queries);
        $this->assertSame(7, substr_count($rendered, '<a href="http'));
    }

    public function test_text_without_links_runs_no_query(): void
    {
        $project = Project::factory()->create();

        DB::enableQueryLog();
        $html = $this->render('<p><a href="https://example.com">web</a> entity:note/1</p>', $project);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $queries);
        $this->assertSame('<p><a href="https://example.com">web</a> entity:note/1</p>', $html);
    }

    public function test_a_markdown_shaped_link_in_rendered_html_stays_literal_text(): void
    {
        $project = Project::factory()->create();
        $note = Note::factory()->for($project)->create();
        $code = '<pre><code>[x](entity:note/'.$note->id.')</code></pre>';

        $this->assertSame($code, $this->render($code, $project));
    }

    public function test_the_note_read_page_renders_a_resolved_link(): void
    {
        $user = User::factory()->create();
        [$project] = $this->projectWithBook($user);
        $target = Note::factory()->for($project)->create();
        $note = Note::factory()->for($project)->create([
            'body' => '<p>See <a href="entity:note/'.$target->id.'">the other note</a>.</p>',
        ]);

        $this->actingAs($user)
            ->get(route('notes.show', $note))
            ->assertOk()
            ->assertSee('<a href="'.route('notes.show', $target).'">the other note</a>', false)
            ->assertDontSee('entity:note/', false);
    }

    public function test_the_scene_read_page_renders_resolved_links_in_description_and_contents(): void
    {
        $user = User::factory()->create();
        [$project] = $this->projectWithBook($user);
        $codex = CodexEntry::factory()->for($project)->create();
        $event = Event::factory()->for($project)->create();
        $scene = $this->sceneIn($project, [
            'description' => '<p>About <a href="entity:codex/'.$codex->id.'">Javert</a>.</p>',
            'contents' => 'On [the night](entity:event/'.$event->id.') he left. [Gone](entity:note/999999).',
        ]);

        $this->actingAs($user)
            ->get(route('scenes.show', $scene))
            ->assertOk()
            ->assertSee('<a href="'.route('codex.show', $codex).'">Javert</a>', false)
            ->assertSee('<a href="'.route('events.show', $event).'">the night</a>', false)
            ->assertSee('<span class="entity-link-missing" title="The linked item no longer exists.">Gone</span>', false)
            ->assertDontSee('entity:', false);
    }

    public function test_a_non_owner_cannot_see_a_page_with_links(): void
    {
        [$project] = $this->projectWithBook();
        $note = Note::factory()->for($project)->create(['body' => '<p><a href="entity:note/1">x</a></p>']);

        $this->actingAs(User::factory()->create())
            ->get(route('notes.show', $note))
            ->assertForbidden();
    }

    public function test_excerpt_and_plain_text_keep_the_link_text(): void
    {
        $project = Project::factory()->create();
        $note = Note::factory()->for($project)->create();
        $html = '<p>See <a href="entity:note/'.$note->id.'">the other note</a> now.</p>';
        $scene = $this->sceneIn($project, ['contents' => 'See [the other note](entity:note/'.$note->id.') now.']);

        $this->assertSame('See the other note now.', RichText::toPlainText($html));
        $this->assertSame('See the other note now.', trim(Blade::render('<x-rich-text-excerpt :html="$html" />', ['html' => $html])));
        $this->assertSame('See the other note now.', $scene->contentsExcerpt());
    }

    public function test_a_visual_revision_diff_resolves_its_links(): void
    {
        $project = Project::factory()->create();
        $note = Note::factory()->for($project)->create();
        $link = '<a href="entity:note/'.$note->id.'">the note</a>';
        $diff = app(RevisionDiffer::class)->diff(FieldKind::Rich, "<p>Old {$link}</p>", "<p>New {$link}</p>")->html;

        $rendered = Blade::render(
            '<x-diff :html="$html" :kind="$kind" :project="$project" />',
            ['html' => $diff, 'kind' => FieldKind::Rich, 'project' => $project],
        );

        $this->assertStringContainsString('href="'.route('notes.show', $note).'"', $rendered);
        $this->assertStringNotContainsString('entity:', $rendered);
    }
}
