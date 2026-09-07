<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The whole-user `page_size` preference: the PATCH that stores it and
 * returns the writer to the list they came from.
 */
class PageSizePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_patch_with_a_valid_size_stores_it_and_redirects_to_the_previous_list(): void
    {
        $user = User::factory()->create(['page_size' => null]);

        $response = $this
            ->actingAs($user)
            ->from('/projects')
            ->patch(route('preferences.page-size.update'), ['page_size' => 250]);

        $response->assertRedirect('/projects');
        $this->assertSame(250, $user->fresh()->page_size);
    }

    public function test_a_previous_url_carrying_a_page_parameter_redirects_without_it(): void
    {
        $user = User::factory()->create(['page_size' => null]);

        $response = $this
            ->actingAs($user)
            ->from('/projects?page=12&sort=title')
            ->patch(route('preferences.page-size.update'), ['page_size' => 250]);

        $response->assertRedirect('/projects?sort=title');
    }

    public function test_an_unlisted_size_fails_validation_and_leaves_the_column_unchanged(): void
    {
        $user = User::factory()->create(['page_size' => 100]);

        $response = $this
            ->actingAs($user)
            ->from('/projects')
            ->patch(route('preferences.page-size.update'), ['page_size' => 999]);

        $response->assertSessionHasErrors('page_size');
        $this->assertSame(100, $user->fresh()->page_size);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->patch(route('preferences.page-size.update'), ['page_size' => 250])
            ->assertRedirect(route('login'));
    }

    public function test_one_users_write_leaves_another_users_column_untouched(): void
    {
        $userA = User::factory()->create(['page_size' => null]);
        $userB = User::factory()->create(['page_size' => 50]);

        $this
            ->actingAs($userA)
            ->from('/projects')
            ->patch(route('preferences.page-size.update'), ['page_size' => 500]);

        $this->assertSame(500, $userA->fresh()->page_size);
        $this->assertSame(50, $userB->fresh()->page_size);
    }
}
