<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The resolved LocaleChoice must reach every view, logged in or not. */
class LocaleSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_logged_in_users_locale_is_shared_to_the_view(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);

        $response = $this->actingAs($user)->get('/onboarding');

        $response->assertOk();
        $response->assertViewHas('locale', fn ($locale) => $locale->slug === 'fr');
    }

    public function test_a_guest_gets_the_default_locale_without_error(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertViewHas('locale', fn ($locale) => $locale->slug === config('locales.default'));
    }
}
