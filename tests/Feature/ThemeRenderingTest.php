<?php

namespace Tests\Feature;

use App\Models\Scene;
use App\Models\User;
use App\Support\ThemeTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Require every themed layout to emit all theme tokens. */
class ThemeRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_authenticated_layout_emits_the_token_block(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('onboarding'));

        $response->assertOk();
        $this->assertEmitsEveryToken($response->getContent());
    }

    public function test_the_guest_layout_emits_the_token_block(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $this->assertEmitsEveryToken($response->getContent());
    }

    public function test_the_public_share_layout_emits_the_token_block(): void
    {
        $user = User::factory()->create();
        $scene = Scene::factory()->create();
        $scene->chapter->act->book->project->update(['user_id' => $user->id]);
        $scene->forceFill([
            'share_token' => 'theme-token',
            'share_expires_at' => now()->addDay(),
        ])->save();

        $response = $this->get(route('shared.scenes.show', 'theme-token'));

        $response->assertOk();
        $this->assertEmitsEveryToken($response->getContent());
    }

    public function test_the_landing_page_emits_the_token_block(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertEmitsEveryToken($response->getContent());
    }

    /**
     * The emitted values are the active preset's, and they name no hue-based variable —
     * a `var(--color-ocean-500)` slipping into a preset would tie the switcher back to
     * the ramps this spec deletes.
     */
    public function test_the_emitted_block_carries_literal_values_and_no_hue_variable(): void
    {
        $content = $this->actingAs(User::factory()->create())->get(route('onboarding'))->getContent();

        // Read through themes.default: this user picked no preset, so the block
        // carries whatever the default resolves to. Naming a preset here would
        // fail the day the default changes, for no reason the test is about.
        $this->assertStringContainsString(
            '--color-primary:'.config('themes.presets.'.config('themes.default').'.tokens.primary').';',
            $content,
        );

        foreach (['ocean', 'aqua', 'navy', 'sun', 'flame'] as $ramp) {
            $this->assertStringNotContainsString("--color-primary:var(--color-{$ramp}", $content);
        }
    }

    public function test_the_authenticated_layout_emits_the_font_variables(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('onboarding'));

        $response->assertOk();
        $this->assertEmitsFontVariables($response->getContent());
    }

    public function test_the_guest_layout_emits_the_font_variables(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $this->assertEmitsFontVariables($response->getContent());
    }

    public function test_the_public_share_layout_emits_the_font_variables(): void
    {
        $user = User::factory()->create();
        $scene = Scene::factory()->create();
        $scene->chapter->act->book->project->update(['user_id' => $user->id]);
        $scene->forceFill([
            'share_token' => 'font-token',
            'share_expires_at' => now()->addDay(),
        ])->save();

        $response = $this->get(route('shared.scenes.show', 'font-token'));

        $response->assertOk();
        $this->assertEmitsFontVariables($response->getContent());
    }

    public function test_a_user_with_a_stored_ui_font_gets_that_familys_stack(): void
    {
        $user = User::factory()->create(['ui_font' => 'atkinson']);

        $content = $this->actingAs($user)->get(route('onboarding'))->getContent();

        $this->assertStringContainsString(
            '--font-sans:'.config('fonts.families.atkinson.stack').';',
            $content,
        );
    }

    /**
     * The regression this feature is most likely to introduce: a guest or public-share
     * visitor must never see another user's stored choice, only the config default.
     */
    public function test_guest_and_public_share_pages_emit_the_config_default_even_when_a_user_chose_differently(): void
    {
        $owner = User::factory()->create(['ui_font' => 'atkinson']);
        $scene = Scene::factory()->create();
        $scene->chapter->act->book->project->update(['user_id' => $owner->id]);
        $scene->forceFill([
            'share_token' => 'font-default-token',
            'share_expires_at' => now()->addDay(),
        ])->save();

        $defaultStack = config('fonts.families.'.config('fonts.default_ui').'.stack');

        $guestContent = $this->get(route('login'))->getContent();
        $this->assertStringContainsString("--font-sans:{$defaultStack};", $guestContent);

        $shareContent = $this->get(route('shared.scenes.show', 'font-default-token'))->getContent();
        $this->assertStringContainsString("--font-sans:{$defaultStack};", $shareContent);
    }

    private function assertEmitsEveryToken(string $content): void
    {
        foreach (ThemeTokens::ALL as $token) {
            $this->assertStringContainsString("--color-{$token}:", $content);
        }
    }

    private function assertEmitsFontVariables(string $content): void
    {
        $this->assertStringContainsString('--font-sans:', $content);
        $this->assertStringContainsString('--font-manuscript:', $content);
    }
}
