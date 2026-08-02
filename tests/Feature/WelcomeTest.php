<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_a_login_link(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('login'), false);
        $response->assertDontSee(route('dashboard'), false);
    }

    public function test_an_authenticated_user_sees_a_dashboard_link(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee(route('dashboard'), false);
        $response->assertDontSee(route('login'), false);
    }
}
