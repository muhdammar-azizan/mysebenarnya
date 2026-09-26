<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_landing_page_shows_the_brand_and_links_to_login_and_register(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Tidak Pasti')
            ->assertSee('SEBENARNYA.MY')
            ->assertSee(route('login'), false)
            ->assertSee(route('register'), false);
    }
}
