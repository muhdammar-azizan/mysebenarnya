<?php

namespace Tests\Feature\Reports;

use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcmc_can_export_inquiry_overview_as_pdf(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['status' => InquiryStatus::VerifiedTrue]);

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->call('exportInquiriesPdf')
            ->assertFileDownloaded();
    }

    public function test_mcmc_can_export_inquiry_overview_as_excel(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['status' => InquiryStatus::VerifiedTrue]);

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->call('exportInquiriesExcel')
            ->assertFileDownloaded();
    }

    public function test_mcmc_can_export_agency_performance_as_pdf(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Agency::factory()->create();

        Volt::actingAs($staff)->test('mcmc.reports.index')->set('tab', 'agencies')
            ->call('exportAgenciesPdf')->assertFileDownloaded();
    }

    public function test_mcmc_can_export_agency_performance_as_excel(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Agency::factory()->create();

        Volt::actingAs($staff)->test('mcmc.reports.index')->set('tab', 'agencies')
            ->call('exportAgenciesExcel')->assertFileDownloaded();
    }

    public function test_mcmc_can_export_user_growth_as_pdf(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)->test('mcmc.reports.index')->set('tab', 'users')
            ->call('exportUsersPdf')->assertFileDownloaded();
    }

    public function test_mcmc_can_export_user_growth_as_excel(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)->test('mcmc.reports.index')->set('tab', 'users')
            ->call('exportUsersExcel')->assertFileDownloaded();
    }

    public function test_agency_can_export_their_own_report_as_pdf(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::VerifiedTrue, 'resolved_at' => now()]);

        Volt::actingAs($staff)->test('agency.reports.index')
            ->call('exportPdf')->assertFileDownloaded();
    }

    public function test_agency_can_export_their_own_report_as_excel(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::VerifiedTrue, 'resolved_at' => now()]);

        Volt::actingAs($staff)->test('agency.reports.index')
            ->call('exportExcel')->assertFileDownloaded();
    }

    public function test_agency_export_only_contains_their_own_agencys_data(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $staffA = User::factory()->agencyStaff()->create(['agency_id' => $agencyA->id]);

        Inquiry::factory()->create(['agency_id' => $agencyA->id, 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['agency_id' => $agencyB->id, 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['agency_id' => $agencyB->id, 'status' => InquiryStatus::VerifiedTrue]);

        $summary = Volt::actingAs($staffA)->test('agency.reports.index')->instance()->summary;

        $this->assertSame(1, $summary['verified']);
    }
}
