<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_landing_page(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Welcome'));
    }

    public function test_signed_in_users_are_sent_to_the_home_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_logging_in_lands_on_the_home_dashboard(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
