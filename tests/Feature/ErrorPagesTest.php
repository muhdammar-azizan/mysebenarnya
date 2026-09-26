<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_an_unknown_route_shows_the_branded_404_page(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/this-route-does-not-exist');

        $response
            ->assertStatus(404)
            ->assertSee('404')
            ->assertSee('Page Not Found');
    }

    public function test_404_page_links_to_login_for_guests(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/this-route-does-not-exist');

        $response->assertSee(route('login'), false);
    }

    public function test_404_page_links_to_dashboard_for_authenticated_users(): void
    {
        config(['app.debug' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/this-route-does-not-exist');

        $response->assertSee(route('dashboard'), false);
    }

    public function test_419_session_expired_page_renders_with_a_login_link(): void
    {
        // Laravel renders resources/views/errors/419.blade.php automatically for
        // any TokenMismatchException (expired CSRF token/session) using the same
        // status-code-to-view convention already proven for 404 above. Routing an
        // actual CSRF failure through this app isn't practical in a feature test
        // since every mutation goes through Livewire's own AJAX endpoint rather
        // than a plain form POST, so this confirms the view itself is correct.
        $response = $this->view('errors.419');

        $response
            ->assertSee('Your session has expired')
            ->assertSee(route('login'), false);
    }
}
