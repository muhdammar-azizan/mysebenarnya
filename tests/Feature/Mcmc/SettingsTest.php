<?php

namespace Tests\Feature\Mcmc;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcmc_staff_can_view_the_settings_page(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)
            ->test('mcmc.settings.index')
            ->assertSee('Account Settings')
            ->assertSee('Notification Preferences');
    }

    public function test_all_preferences_default_to_enabled_for_a_staff_member_who_never_saved_any(): void
    {
        $staff = User::factory()->mcmcStaff()->create(['notification_preferences' => null]);

        $this->assertTrue($staff->wantsNotification('newInquiry'));
        $this->assertTrue($staff->wantsNotification('agencyRejects'));
    }

    public function test_mcmc_staff_can_save_notification_preferences(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)
            ->test('mcmc.settings.index')
            ->set('tab', 'notifications')
            ->set('prefs.agencyRejects', false)
            ->call('savePreferences')
            ->assertSet('prefsSaved', true);

        $staff->refresh();
        $this->assertFalse($staff->wantsNotification('agencyRejects'));
        $this->assertTrue($staff->wantsNotification('newInquiry'));
    }

    public function test_public_user_cannot_access_mcmc_settings(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        $this->actingAs($user)->get(route('mcmc.settings.index'))->assertForbidden();
    }
}
