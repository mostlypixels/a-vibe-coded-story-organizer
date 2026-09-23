<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Scene;
use App\Models\User;
use App\Support\ScriptTranslations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/** JavaScript text must come from Laravel's translator, so a locale can translate it. */
class ScriptTranslationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // JSON translations live in the `*` namespace and group.
        Lang::addLines(['*.Saved' => 'Enregistré', '*.Table' => 'Tableau'], 'fr');
        App::setLocale('fr');
    }

    public function test_the_lists_hold_the_translation_for_each_key(): void
    {
        $this->assertSame('Enregistré', ScriptTranslations::autosaveBadge()['Saved']);
        $this->assertSame('Tableau', ScriptTranslations::wysiwyg()['Table']);
        $this->assertSame('No matches', ScriptTranslations::wysiwyg()['No matches']);
    }

    public function test_the_autosave_badge_passes_the_translations_to_its_script(): void
    {
        $html = Blade::render('<x-autosave-status-badge />');

        $this->assertStringContainsString('autosaveBadge(JSON.parse(', $html);
        $this->assertStringContainsString('Enregistré', $html);
    }

    public function test_the_editor_passes_the_translations_to_its_script(): void
    {
        $html = Blade::render('<x-wysiwyg name="notes" />');

        $this->assertStringContainsString('strings: JSON.parse(', $html);
        $this->assertStringContainsString('Tableau', $html);
    }

    public function test_the_story_overview_renders_a_hidden_message_for_a_failed_scene_move(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        Scene::factory()->for($chapter)->create();

        $this->actingAs($user)
            ->get(route('books.story.overview', $book))
            ->assertOk()
            ->assertSee('data-move-error role="alert" hidden', false)
            ->assertSee(__('The scene did not move. Reload the page and try again.'));
    }
}
