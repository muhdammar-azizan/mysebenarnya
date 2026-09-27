<?php

namespace Tests\Feature\Reports;

use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcmc_can_generate_a_combined_pdf_export(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['status' => InquiryStatus::VerifiedTrue]);

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->call('generateExport')
            ->assertFileDownloaded();
    }

    public function test_mcmc_can_generate_a_combined_excel_export(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['status' => InquiryStatus::VerifiedTrue]);

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('exportFormat', 'excel')
            ->call('generateExport')
            ->assertFileDownloaded();
    }

    public function test_mcmc_export_requires_at_least_one_section(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('exportSections', ['user' => false, 'inquiry' => false, 'agency' => false])
            ->call('generateExport')
            ->assertHasErrors(['exportSections']);
    }

    public function test_mcmc_export_can_be_narrowed_to_a_single_section(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Agency::factory()->create();

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('exportSections', ['user' => false, 'inquiry' => false, 'agency' => true])
            ->call('generateExport')
            ->assertFileDownloaded();
    }

    public function test_every_mcmc_export_is_logged_for_the_dashboard_counter(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['status' => InquiryStatus::VerifiedTrue]);

        Volt::actingAs($staff)->test('mcmc.reports.index')->call('generateExport');
        Volt::actingAs($staff)->test('mcmc.reports.index')->set('exportFormat', 'excel')->call('generateExport');

        $this->assertSame(2, ReportExport::where('user_id', $staff->id)->count());
        $this->assertDatabaseHas('report_exports', ['format' => 'pdf', 'user_id' => $staff->id]);
        $this->assertDatabaseHas('report_exports', ['format' => 'excel', 'user_id' => $staff->id]);
    }

    public function test_generating_an_mcmc_export_records_it_in_the_session_history_and_completes(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->call('generateExport')
            ->assertSet('exportJustCompleted', true)
            ->assertCount('exportHistory', 1);
    }

    public function test_agency_can_generate_their_own_pdf_export(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::VerifiedTrue, 'resolved_at' => now()]);

        Volt::actingAs($staff)
            ->test('agency.reports.index')
            ->call('generateExport')
            ->assertFileDownloaded();
    }

    public function test_agency_can_generate_their_own_excel_export(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::VerifiedTrue, 'resolved_at' => now()]);

        Volt::actingAs($staff)
            ->test('agency.reports.index')
            ->set('exportFormat', 'excel')
            ->call('generateExport')
            ->assertFileDownloaded();
    }

    public function test_agency_export_only_contains_their_own_agencys_data(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $staffA = User::factory()->agencyStaff()->create(['agency_id' => $agencyA->id]);

        Inquiry::factory()->create(['agency_id' => $agencyA->id, 'status' => InquiryStatus::VerifiedTrue, 'resolved_at' => now()]);
        Inquiry::factory()->create(['agency_id' => $agencyB->id, 'status' => InquiryStatus::VerifiedTrue, 'resolved_at' => now()]);
        Inquiry::factory()->create(['agency_id' => $agencyB->id, 'status' => InquiryStatus::VerifiedTrue, 'resolved_at' => now()]);

        $totalResolved = Volt::actingAs($staffA)->test('agency.reports.index')->get('totalResolved');

        $this->assertSame(1, $totalResolved);
    }

    public function test_agency_activity_log_export_downloads_a_pdf(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id]);
        \App\Models\InquiryActivityLog::create(['inquiry_id' => $inquiry->id, 'user_id' => $staff->id, 'action' => 'investigation_updated', 'notes' => 'Progress note.']);

        Volt::actingAs($staff)
            ->test('agency.activity.index')
            ->call('generateExport')
            ->assertFileDownloaded();
    }

    public function test_agency_activity_log_export_downloads_an_excel(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id]);
        \App\Models\InquiryActivityLog::create(['inquiry_id' => $inquiry->id, 'user_id' => $staff->id, 'action' => 'investigation_updated', 'notes' => 'Progress note.']);

        Volt::actingAs($staff)
            ->test('agency.activity.index')
            ->set('exportFormat', 'excel')
            ->call('generateExport')
            ->assertFileDownloaded();
    }
}
