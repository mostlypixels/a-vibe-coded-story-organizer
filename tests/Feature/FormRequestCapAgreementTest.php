<?php

namespace Tests\Feature;

use App\Http\Requests\StoreActRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\StoreChapterRequest;
use App\Http\Requests\StoreCodexEntryRequest;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\StorePlotlineRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\StoreSceneRequest;
use App\Http\Requests\UpdateActRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Requests\UpdateChapterRequest;
use App\Http\Requests\UpdateCodexEntryRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Requests\UpdatePlotlineRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Requests\UpdateSceneRequest;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Support\AutosavableFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Keep manual-save and autosave limits equal for every registered field. */
class FormRequestCapAgreementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every Form Request that can write a given slug's fields.
     *
     * The only hand-maintained part of this test, and
     * {@see self::test_the_request_map_covers_every_registered_slug()} fails if a
     * new slug is registered without being listed here.
     *
     * @var array<string, list<class-string<FormRequest>>>
     */
    private const REQUESTS_BY_SLUG = [
        'project' => [StoreProjectRequest::class, UpdateProjectRequest::class],
        'book' => [StoreBookRequest::class, UpdateBookRequest::class],
        'act' => [StoreActRequest::class, UpdateActRequest::class],
        'chapter' => [StoreChapterRequest::class, UpdateChapterRequest::class],
        'plotline' => [StorePlotlineRequest::class, UpdatePlotlineRequest::class],
        'event' => [StoreEventRequest::class, UpdateEventRequest::class],
        'scene' => [StoreSceneRequest::class, UpdateSceneRequest::class],
        'codex' => [StoreCodexEntryRequest::class, UpdateCodexEntryRequest::class],
    ];

    /**
     * Route parameters the requests' rules() methods resolve, e.g.
     * `$this->route('scene')->chapter->act->book->project`.
     *
     * @var array<string, mixed>
     */
    private array $routeParameters = [];

    protected function setUp(): void
    {
        parent::setUp();

        [$project, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        $this->routeParameters = [
            'project' => $project,
            'book' => $book,
            'act' => $act,
            'chapter' => $chapter,
            'scene' => Scene::factory()->for($chapter)->create(),
            'plotline' => Plotline::factory()->for($project)->create(),
            'event' => Event::factory()->for($project)->create(),
            'codexEntry' => CodexEntry::factory()->for($project)->create(),
        ];
    }

    // ---------------------------------------------------------------------
    // The structural guard
    // ---------------------------------------------------------------------

    public function test_every_form_request_rule_for_a_registered_field_matches_the_registry(): void
    {
        $checked = 0;

        foreach (AutosavableFields::REGISTRY as $slug => [$modelClass, $fields]) {
            foreach (array_keys($fields) as $field) {
                foreach (self::REQUESTS_BY_SLUG[$slug] as $requestClass) {
                    $rules = $this->rulesFor($requestClass);

                    // A request need not expose every field — project creation asks
                    // only for a name and description. It may not disagree about the
                    // ones it does expose.
                    if (! array_key_exists($field, $rules)) {
                        continue;
                    }

                    $this->assertEquals(
                        AutosavableFields::validationRule($slug, $field),
                        $rules[$field],
                        "{$requestClass} validates [{$field}] differently from "
                        ."AutosavableFields::validationRule('{$slug}', '{$field}'). Call that "
                        .'method instead of spelling the rule out — the autosave endpoint uses '
                        .'it, and the two paths must agree.',
                    );

                    $checked++;
                }
            }
        }

        // Guards the guard: a typo'd map or a rules() that stopped exposing its
        // fields would otherwise make this test pass by checking nothing.
        $this->assertSame(25, $checked, 'Expected 25 registered field rules across the Form Requests.');
    }

    public function test_the_request_map_covers_every_registered_slug(): void
    {
        $this->assertEqualsCanonicalizing(
            AutosavableFields::slugs(),
            array_keys(self::REQUESTS_BY_SLUG),
            'A newly registered autosave slug also needs its Form Requests listed here.',
        );
    }

    // ---------------------------------------------------------------------
    // The drift, end to end: both paths now refuse the same text
    // ---------------------------------------------------------------------

    public function test_the_save_form_and_autosave_agree_on_the_front_matter_cap(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        // 40,000 characters is the exact gap the two caps used to leave open:
        // under autosave's 100,000 it was accepted and stored, over the form's
        // 20,000 it was then refused by Save. The writer saw "must not be greater
        // than 20000" about text the server had already taken. Both paths must
        // now refuse it, and refuse it for the same reason.
        $inTheOldGap = str_repeat('a', 40_000);

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'book', 'id' => $book->id, 'field' => 'dedication']),
            ['value' => $inTheOldGap, 'base_hash' => hash('sha256', '')],
        )->assertStatus(422)->assertJsonValidationErrors(['value']);

        $this->actingAs($user)->put(route('books.update', $book), [
            'language' => 'en',
            'dedication' => $inTheOldGap,
        ])->assertSessionHasErrors('dedication');

        $this->assertNull($book->fresh()->dedication);
    }

    public function test_the_save_form_now_caps_a_field_it_used_to_leave_unbounded(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();

        // `description` had no `max:` rule on the form path at all, so Save
        // accepted text every subsequent autosave would refuse.
        $this->actingAs($user)->put(route('acts.update', $act), [
            'name' => $act->name,
            'description' => str_repeat('a', AutosavableFields::characterCap('act', 'description') + 1),
        ])->assertSessionHasErrors('description');
    }

    public function test_front_matter_up_to_the_cap_is_still_accepted(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $atCap = str_repeat('a', AutosavableFields::characterCap('book', 'dedication'));

        $this->actingAs($user)->patchJson(
            route('autosave.update', ['entity' => 'book', 'id' => $book->id, 'field' => 'dedication']),
            ['value' => $atCap, 'base_hash' => hash('sha256', '')],
        )->assertOk();

        $this->assertSame($atCap, $book->fresh()->dedication);

        $this->actingAs($user)->put(route('books.update', $book), [
            'language' => 'en',
            'dedication' => $atCap,
        ])->assertSessionHasNoErrors();

        $this->assertSame($atCap, $book->fresh()->dedication);
    }

    // ---------------------------------------------------------------------

    /**
     * @param  class-string<FormRequest>  $requestClass
     * @return array<string, mixed>
     */
    private function rulesFor(string $requestClass): array
    {
        $parameters = $this->routeParameters;

        $request = new $requestClass;

        $request->setRouteResolver(fn () => new class($parameters)
        {
            public function __construct(private array $parameters) {}

            public function parameter(string $name, mixed $default = null): mixed
            {
                return $this->parameters[$name] ?? $default;
            }
        });

        return $request->rules();
    }
}
