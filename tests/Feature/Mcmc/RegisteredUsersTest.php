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

    public function test_date_range_filter_narrows_the_list_for_report_drilldowns(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $inRange = User::factory()->create(['role' => UserRole::Public, 'name' => 'In Range User', 'created_at' => now()->subDays(10)]);
        $outOfRange = User::factory()->create(['role' => UserRole::Public, 'name' => 'Out Of Range User', 'created_at' => now()->subMonths(3)]);

        Volt::actingAs($staff)
            ->test('mcmc.users.index')
            ->set('dateFrom', now()->subDays(15)->format('Y-m-d'))
            ->set('dateTo', now()->format('Y-m-d'))
            ->assertSee('In Range User')
            ->assertDontSee('Out Of Range User');
    }

    public function test_summary_stats_reflect_all_public_users_regardless_of_filters(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        User::factory()->create(['role' => UserRole::Public, 'name' => 'Zed Verified', 'email_verified_at' => now()]);
        User::factory()->create(['role' => UserRole::Public, 'name' => 'Aaron Unverified', 'email_verified_at' => null]);

        Volt::actingAs($staff)
            ->test('mcmc.users.index')
            ->set('search', 'Zed')
            ->assertSeeText('Total: 2')
            ->assertSeeText('Verified: 1')
            ->assertSeeText('Unverified: 1');
    }

    public function test_export_report_downloads_a_pdf(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        User::factory()->create(['role' => UserRole::Public]);

        Volt::actingAs($staff)
            ->test('mcmc.users.index')
            ->call('exportPdf')
            ->assertFileDownloaded();
    }

    public function test_panel_lists_the_users_submitted_inquiries(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $publicUser = User::factory()->create(['role' => UserRole::Public]);
        \App\Models\Inquiry::factory()->create(['submitted_by' => $publicUser->id, 'title' => 'My submitted claim']);

        Volt::actingAs($staff)
            ->test('mcmc.users.index')
            ->call('openPanel', $publicUser->id)
            ->assertSee('Submitted Inquiries')
            ->assertSee('My submitted claim');
    }

    public function test_activity_tab_includes_account_registration_and_actions_taken_on_their_inquiries(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $publicUser = User::factory()->create(['role' => UserRole::Public]);
        $inquiry = \App\Models\Inquiry::factory()->create(['submitted_by' => $publicUser->id, 'status' => \App\Enums\InquiryStatus::Discarded]);
        \App\Models\InquiryActivityLog::create(['inquiry_id' => $inquiry->id, 'user_id' => $staff->id, 'action' => 'discarded']);

        Volt::actingAs($staff)
            ->test('mcmc.users.index')
            ->call('openPanel', $publicUser->id)
            ->set('panelTab', 'activity')
            ->assertSee('Account registered')
            ->assertSee('Discarded');
    }
}
