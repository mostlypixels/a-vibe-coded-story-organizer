<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The whole-user `editor_layout` preference: the PATCH that stores it. */
class EditorLayoutPreferenceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'left' => ['width' => 300, 'collapsed' => true],
            'right' => ['width' => 400, 'collapsed' => false],
            'sections' => ['codex' => true, 'notes' => false, 'recent' => true],
        ], $overrides);
    }

    public function test_patch_stores_the_layout_and_returns_204(): void
    {
        $user = User::factory()->create(['editor_layout' => null]);

        $this->actingAs($user)
            ->patchJson(route('preferences.editor-layout'), $this->payload())
            ->assertNoContent();

        $this->assertSame($this->payload(), $user->fresh()->editor_layout);
    }

    public function test_a_width_outside_the_limits_fails_validation(): void
    {
        $user = User::factory()->create(['editor_layout' => null]);

        foreach ([199, 641] as $width) {
            $this->actingAs($user)
                ->patchJson(route('preferences.editor-layout'), $this->payload(['left' => ['width' => $width]]))
                ->assertJsonValidationErrors('left.width');
        }

        $this->assertNull($user->fresh()->editor_layout);
    }

    public function test_a_missing_or_wrongly_typed_key_fails_validation(): void
    {
        $user = User::factory()->create(['editor_layout' => null]);

        $this->actingAs($user)
            ->patchJson(route('preferences.editor-layout'), ['left' => ['width' => 'wide']])
            ->assertJsonValidationErrors(['left.width', 'left.collapsed', 'right', 'sections']);
    }

    public function test_unknown_keys_are_dropped(): void
    {
        $user = User::factory()->create(['editor_layout' => null]);

        $this->actingAs($user)
            ->patchJson(route('preferences.editor-layout'), $this->payload([
                'left' => ['evil' => 'x'],
                'sections' => ['journal' => true],
                'extra' => 1,
            ]))
            ->assertNoContent();

        $this->assertSame($this->payload(), $user->fresh()->editor_layout);
    }

    public function test_a_payload_without_the_recent_section_fails_validation(): void
    {
        $user = User::factory()->create(['editor_layout' => null]);
        $payload = $this->payload();
        unset($payload['sections']['recent']);

        $this->actingAs($user)
            ->patchJson(route('preferences.editor-layout'), $payload)
            ->assertJsonValidationErrors('sections.recent');

        $this->assertNull($user->fresh()->editor_layout);
    }

    public function test_one_users_write_leaves_another_users_column_untouched(): void
    {
        $userA = User::factory()->create(['editor_layout' => null]);
        $userB = User::factory()->create(['editor_layout' => null]);

        $this->actingAs($userA)->patchJson(route('preferences.editor-layout'), $this->payload());

        $this->assertNotNull($userA->fresh()->editor_layout);
        $this->assertNull($userB->fresh()->editor_layout);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->patch(route('preferences.editor-layout'), $this->payload())
            ->assertRedirect(route('login'));
    }
}
