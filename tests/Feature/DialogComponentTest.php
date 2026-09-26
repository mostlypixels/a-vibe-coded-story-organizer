<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Guard that screen readers announce app dialogs as dialogs with a name. */
class DialogComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_titled_dialog_is_a_modal_dialog_named_by_its_title(): void
    {
        $rendered = Blade::render('<x-dialog name="undo" title="Undo this save?">Body</x-dialog>');

        $this->assertStringContainsString('role="dialog"', $rendered);
        $this->assertStringContainsString('aria-modal="true"', $rendered);
        $this->assertStringContainsString('aria-labelledby="undo-title"', $rendered);
        $this->assertMatchesRegularExpression('/<h3[^>]*id="undo-title"[^>]*>\s*Undo this save\?/', $rendered);
    }

    public function test_an_untitled_dialog_uses_the_heading_id_the_caller_passes(): void
    {
        $rendered = Blade::render('<x-dialog name="url" labelledby="url-title"><h3 id="url-title">Link</h3></x-dialog>');

        $this->assertStringContainsString('role="dialog"', $rendered);
        $this->assertStringContainsString('aria-labelledby="url-title"', $rendered);
    }

    public function test_a_bare_modal_without_a_label_omits_aria_labelledby(): void
    {
        $rendered = Blade::render('<x-modal name="bare">Body</x-modal>');

        $this->assertStringContainsString('role="dialog"', $rendered);
        $this->assertStringContainsString('aria-modal="true"', $rendered);
        $this->assertStringNotContainsString('aria-labelledby', $rendered);
    }

    public function test_the_account_delete_modal_is_named_by_its_heading(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('aria-labelledby="confirm-user-deletion-title"', false)
            ->assertSee('id="confirm-user-deletion-title"', false);
    }
}
