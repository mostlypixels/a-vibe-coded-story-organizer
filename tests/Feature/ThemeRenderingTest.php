<?php

namespace Tests\Feature;

use App\Models\Scene;
use App\Models\User;
use App\Support\ThemeTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every layout paints from the token block, so no page can drift back onto the
 * compiled defaults.
 *
 * > [!NOTE]
 * > Deliberately no assertion about the block's position relative to the @vite tag. An
 * > unlayered `:root` rule outranks Tailwind's `@layer theme` at any source order, so a
 * > position assertion would pass for a reason that is not the mechanism — and would
 * > keep passing after someone wrapped the block in `@layer` and broke it.
 */
class ThemeRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_authenticated_layout_emits_the_token_block(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

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
        $scene->chapter->act->project->update(['user_id' => $user->id]);
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
        $content = $this->actingAs(User::factory()->create())->get(route('dashboard'))->getContent();

        $this->assertStringContainsString(
            '--color-primary:'.config('themes.presets.daylight.tokens.primary').';',
            $content,
        );

        foreach (['ocean', 'aqua', 'navy', 'sun', 'flame'] as $ramp) {
            $this->assertStringNotContainsString("--color-primary:var(--color-{$ramp}", $content);
        }
    }

    private function assertEmitsEveryToken(string $content): void
    {
        foreach (ThemeTokens::ALL as $token) {
            $this->assertStringContainsString("--color-{$token}:", $content);
        }
    }
}
