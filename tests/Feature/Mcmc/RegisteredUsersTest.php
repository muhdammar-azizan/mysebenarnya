<?php

namespace Tests\Feature\Mcmc;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegisteredUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_public_users_only(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        User::factory()->create(['role' => UserRole::Public, 'name' => 'Public Person']);
        User::factory()->mcmcStaff()->create(['name' => 'Other Staffer']);

        Volt::actingAs($staff)
            ->test('mcmc.users.index')
            ->assertSee('Public Person')
            ->assertDontSee('Other Staffer');
    }

    public function test_search_filters_by_name_or_email(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        User::factory()->create(['role' => UserRole::Public, 'name' => 'Ahmad Ismail']);
        User::factory()->create(['role' => UserRole::Public, 'name' => 'Siti Aminah']);

        Volt::actingAs($staff)
            ->test('mcmc.users.index')
            ->set('search', 'ahmad')
            ->assertSee('Ahmad Ismail')
            ->assertDontSee('Siti Aminah');
    }

    public function test_opening_the_panel_shows_the_users_profile(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $publicUser = User::factory()->create(['role' => UserRole::Public, 'phone' => '+60 12-000 0000']);

        Volt::actingAs($staff)
            ->test('mcmc.users.index')
            ->call('openPanel', $publicUser->id)
            ->assertSee('+60 12-000 0000');
    }
}
