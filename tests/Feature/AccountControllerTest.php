<?php

namespace Tests\Feature;

use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('account'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_signed_in_user_sees_the_account_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account'));

        $response->assertOk();
        $response->assertSee('Account');
        $response->assertSee(route('profile.edit'), false);
        $response->assertSee(route('admin.index'), false);

        // Log Out stays only in the user menu, not as a card on this page.
        $main = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR)
            ->getElementById('main-content');
        $this->assertNull($main->querySelector('form[action="'.route('logout').'"]'));
    }
}
