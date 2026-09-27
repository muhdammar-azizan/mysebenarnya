<?php

namespace Tests\Feature\Mcmc;

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_reports_is_the_default_tab(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->assertSet('tab', 'user')
            ->assertSee('Total Registered Users');
    }

    public function test_user_reports_shows_registration_stats(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        User::factory()->create();

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->assertSee('Total Registered Users')
            ->assertSee('Verified Email Users')
            ->assertSee('Active (Last 30 Days)');
    }

    public function test_user_growth_trend_can_be_isolated_to_mcmc_staff(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        User::factory()->mcmcStaff()->create();
        User::factory()->create();

        $component = Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('userTypeFilter', 'mcmc_staff');

        $totalInTrend = collect($component->get('userTrend'))->sum('count');
        $this->assertSame(2, $totalInTrend);
    }

    public function test_view_user_list_link_is_hidden_for_staff_user_types(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('userTypeFilter', 'mcmc_staff')
            ->assertDontSee('View User List');
    }

    public function test_inquiry_reports_tab_shows_status_kpis(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['status' => InquiryStatus::VerifiedTrue]);

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('tab', 'inquiry')
            ->assertSee('Verified as True')
            ->assertSee('Identified as Fake');
    }

    public function test_inquiry_reports_category_breakdown_sums_to_the_period_total(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['category' => InquiryCategory::HealthMedical]);
        Inquiry::factory()->create(['category' => InquiryCategory::HealthMedical]);
        Inquiry::factory()->create(['category' => InquiryCategory::FinancialScams]);

        $component = Volt::actingAs($staff)->test('mcmc.reports.index')->set('tab', 'inquiry');

        $donut = $component->get('categoryDonut');
        $health = collect($donut)->firstWhere('label', InquiryCategory::HealthMedical->value);

        $this->assertSame(67, $health['pct']);
    }

    public function test_agency_performance_tab_shows_assigned_resolved_pending_and_rejection_rate(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create(['name' => 'Test Agency']);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation]);

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('tab', 'agency')
            ->assertSee('Test Agency')
            ->assertDontSee('Resolution Rate');
    }

    public function test_agency_performance_tab_can_be_filtered_to_a_single_agency(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agencyA = Agency::factory()->create(['name' => 'Agency Alpha']);
        $agencyB = Agency::factory()->create(['name' => 'Agency Beta']);
        Inquiry::factory()->create(['agency_id' => $agencyA->id, 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['agency_id' => $agencyB->id, 'status' => InquiryStatus::VerifiedTrue]);

        $component = Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('tab', 'agency')
            ->set('agencyFilter', $agencyA->id);

        $rows = $component->get('agencyPerformance');
        $this->assertCount(1, $rows);
        $this->assertSame('Agency Alpha', $rows->first()->name);
    }

    public function test_agency_rejection_rate_is_computed_from_jurisdiction_decisions(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create(['name' => 'Rejection Rate Agency']);
        $agencyStaff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);

        $accepted = Inquiry::factory()->create(['agency_id' => $agency->id]);
        InquiryActivityLog::create(['inquiry_id' => $accepted->id, 'user_id' => $agencyStaff->id, 'action' => 'jurisdiction_accepted']);

        $rejected = Inquiry::factory()->create(['agency_id' => $agency->id]);
        InquiryActivityLog::create(['inquiry_id' => $rejected->id, 'user_id' => $agencyStaff->id, 'action' => 'jurisdiction_rejected']);
        InquiryActivityLog::create(['inquiry_id' => $rejected->id, 'user_id' => $agencyStaff->id, 'action' => 'jurisdiction_rejected']);

        $component = Volt::actingAs($staff)->test('mcmc.reports.index')->set('tab', 'agency');
        $row = $component->get('agencyPerformance')->firstWhere('name', 'Rejection Rate Agency');

        $this->assertSame(67, $row->rejection_rate);
    }

    public function test_public_user_cannot_access_reports(): void
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Public]);

        $this->actingAs($user)->get(route('mcmc.reports.index'))->assertForbidden();
    }
}
