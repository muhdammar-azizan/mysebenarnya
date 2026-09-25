<?php

namespace Tests\Feature\Mcmc;

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inquiry_overview_tab_shows_status_breakdown(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['status' => InquiryStatus::VerifiedTrue]);

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->assertSee('Verified True');
    }

    public function test_agency_performance_tab_shows_resolution_rate(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create(['name' => 'Test Agency']);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation]);

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('tab', 'agencies')
            ->assertSee('Test Agency')
            ->assertSee('50%');
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
            ->set('tab', 'agencies')
            ->set('agencyFilter', $agencyA->id);

        $rows = $component->get('agencyPerformance');
        $this->assertCount(1, $rows);
        $this->assertSame('Agency Alpha', $rows->first()->name);
    }

    public function test_agency_performance_tab_can_be_filtered_by_category(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create(['name' => 'Test Agency']);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'category' => InquiryCategory::HealthMedical, 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'category' => InquiryCategory::ElectoralPolitical, 'status' => InquiryStatus::VerifiedTrue]);

        $component = Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('tab', 'agencies')
            ->set('categoryFilter', InquiryCategory::HealthMedical->value);

        $this->assertSame(1, $component->get('agencyPerformance')->first()->inquiries_count);
    }

    public function test_user_growth_tab_shows_registration_stats(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('tab', 'users')
            ->assertSee('Total Public Users');
    }

    public function test_user_growth_trend_can_be_isolated_to_mcmc_staff(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        User::factory()->mcmcStaff()->create();
        User::factory()->create();

        $component = Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('tab', 'users')
            ->set('userTypeFilter', 'mcmc_staff');

        $totalInTrend = collect($component->get('userTrend'))->sum('count');
        $this->assertSame(2, $totalInTrend);
    }

    public function test_users_by_agency_can_be_filtered_to_a_single_agency(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agencyA = Agency::factory()->create(['name' => 'Agency Alpha']);
        $agencyB = Agency::factory()->create(['name' => 'Agency Beta']);
        User::factory()->agencyStaff()->create(['agency_id' => $agencyA->id]);
        User::factory()->agencyStaff()->create(['agency_id' => $agencyB->id]);

        $component = Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('tab', 'users')
            ->set('userAgencyFilter', $agencyA->id);

        $rows = $component->get('usersByAgency');
        $this->assertCount(1, $rows);
        $this->assertSame('Agency Alpha', $rows->first()->name);
    }
}
