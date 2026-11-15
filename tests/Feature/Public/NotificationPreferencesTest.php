<?php

namespace Tests\Feature\Public;

use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use App\Notifications\InquiryAssignedToAgency;
use App\Notifications\InquiryStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_user_sees_status_preference_and_profile_tab(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee('Inquiry status updates');
    }

    public function test_agency_user_sees_agency_preferences_and_account_link(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertSee('New inquiry assigned')
            ->assertSee('Consultation requested');
    }

    public function test_preferences_default_to_on_and_can_be_switched_off_and_saved(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public, 'notification_preferences' => null]);

        Volt::actingAs($user)->test('profile.notification-preferences')
            ->assertSet('prefs.statusChanged', true)
            ->call('toggle', 'statusChanged')
            ->assertSet('prefs.statusChanged', false)
            ->call('save')
            ->assertSet('saved', true);

        $this->assertFalse($user->fresh()->wantsNotification('statusChanged'));
    }

    public function test_unknown_keys_cannot_be_toggled_or_saved(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public, 'notification_preferences' => null]);

        Volt::actingAs($user)->test('profile.notification-preferences')
            ->call('toggle', 'newAgency')
            ->call('save');

        $this->assertArrayNotHasKey('newAgency', $user->fresh()->notification_preferences ?? []);
    }

    public function test_saving_keeps_unrelated_stored_preferences(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public, 'notification_preferences' => ['other' => false]]);

        Volt::actingAs($user)->test('profile.notification-preferences')
            ->call('save');

        $this->assertFalse($user->fresh()->notification_preferences['other']);
        $this->assertTrue($user->fresh()->notification_preferences['statusChanged']);
    }

    public function test_status_update_is_skipped_for_a_user_who_opted_out(): void
    {
        Notification::fake();

        $mcmc = User::factory()->mcmcStaff()->create();
        $optedOut = User::factory()->create(['role' => UserRole::Public, 'notification_preferences' => ['statusChanged' => false]]);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $optedOut->id, 'status' => InquiryStatus::Submitted]);

        Volt::actingAs($mcmc)->test('mcmc.triage.index')
            ->call('toggleSelect', $inquiry->id)
            ->call('confirmBulkDiscard');

        Notification::assertNotSentTo($optedOut, InquiryStatusChanged::class);
    }

    public function test_status_update_is_still_sent_by_default(): void
    {
        Notification::fake();

        $mcmc = User::factory()->mcmcStaff()->create();
        $submitter = User::factory()->create(['role' => UserRole::Public]);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $submitter->id, 'status' => InquiryStatus::Submitted]);

        Volt::actingAs($mcmc)->test('mcmc.triage.index')
            ->call('toggleSelect', $inquiry->id)
            ->call('confirmBulkDiscard');

        Notification::assertSentTo($submitter, InquiryStatusChanged::class);
    }

    public function test_assignment_notice_is_skipped_for_agency_staff_who_opted_out(): void
    {
        Notification::fake();

        $mcmc = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create();
        $optedOut = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'notification_preferences' => ['inquiryAssigned' => false]]);
        $wantsIt = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create(['status' => InquiryStatus::Submitted]);

        Volt::actingAs($mcmc)->test('mcmc.triage.index')
            ->call('openAssign', $inquiry->id)
            ->set('selectedAgencyId', $agency->id)
            ->call('confirmAssign');

        Notification::assertNotSentTo($optedOut, InquiryAssignedToAgency::class);
        Notification::assertSentTo($wantsIt, InquiryAssignedToAgency::class);
    }
}
