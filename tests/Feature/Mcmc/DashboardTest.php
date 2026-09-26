<?php

namespace Tests\Feature\Mcmc;

use App\Enums\ClarificationPriority;
use App\Enums\ClarificationStatus;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\ClarificationThread;
use App\Models\Inquiry;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_stat_tiles_show_total_and_assigned_counts(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create();
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation]);
        Inquiry::factory()->create(['agency_id' => null, 'status' => InquiryStatus::Submitted]);

        Volt::actingAs($staff)
            ->test('mcmc.dashboard')
            ->assertSee('Total Inquiries Received')
            ->assertSee('Assigned to Agencies')
            ->assertSee('Reports Generated This Month');
    }

    public function test_reports_generated_this_month_reflects_actual_exports(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        ReportExport::create(['user_id' => $staff->id, 'report_id' => 'RPT-TEST-0001', 'report_type' => 'Inquiry-Overview', 'format' => 'pdf']);
        ReportExport::create(['user_id' => $staff->id, 'report_id' => 'RPT-TEST-0002', 'report_type' => 'Agency-Performance', 'format' => 'excel']);

        $component = Volt::actingAs($staff)->test('mcmc.dashboard');

        $this->assertSame(2, $component->get('plainStats')['reportsThisMonth']);
    }

    public function test_clarify_alert_shows_when_a_thread_is_open(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create(['name' => 'Ministry of Test']);
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation]);

        ClarificationThread::create([
            'inquiry_id' => $inquiry->id,
            'opened_by' => User::factory()->agencyStaff()->create(['agency_id' => $agency->id])->id,
            'subject' => 'Need more context',
            'topic' => \App\Enums\ClarificationTopic::SourceVerification,
            'priority' => ClarificationPriority::Urgent,
            'status' => ClarificationStatus::Open,
            'unread_by_mcmc' => true,
        ]);

        Volt::actingAs($staff)
            ->test('mcmc.dashboard')
            ->assertSee('clarification request')
            ->assertSee('urgent')
            ->assertSee('Ministry of Test');
    }

    public function test_clarify_alert_is_hidden_when_nothing_is_open(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)
            ->test('mcmc.dashboard')
            ->assertDontSee('awaiting your response');
    }

    public function test_inquiries_awaiting_triage_table_lists_submitted_inquiries(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['status' => InquiryStatus::Submitted, 'title' => 'Awaiting triage example']);

        Volt::actingAs($staff)
            ->test('mcmc.dashboard')
            ->assertSee('Inquiries Awaiting Triage')
            ->assertSee('Awaiting triage example');
    }

    public function test_agency_performance_snapshot_lists_agencies(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create(['name' => 'Snapshot Agency']);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::VerifiedTrue, 'reviewed_at' => now()->subDays(3), 'resolved_at' => now()]);

        Volt::actingAs($staff)
            ->test('mcmc.dashboard')
            ->assertSee('Agency Performance Snapshot')
            ->assertSee('Snapshot Agency');
    }
}
