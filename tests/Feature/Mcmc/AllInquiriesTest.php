<?php

namespace Tests\Feature\Mcmc;

use App\Enums\ClarificationPriority;
use App\Enums\ClarificationTopic;
use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\ClarificationThread;
use App\Models\Inquiry;
use App\Models\InquiryEvidence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AllInquiriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_discarded_inquiries_are_excluded_from_the_registry(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Inquiry::factory()->create(['title' => 'A discarded one', 'status' => InquiryStatus::Discarded]);
        Inquiry::factory()->create(['title' => 'A submitted one', 'status' => InquiryStatus::Submitted]);

        Volt::actingAs($staff)
            ->test('mcmc.inquiries.index')
            ->assertDontSee('A discarded one')
            ->assertSee('A submitted one');
    }

    public function test_status_filter_narrows_results(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Inquiry::factory()->create(['title' => 'Verified item', 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['title' => 'Pending item', 'status' => InquiryStatus::Submitted]);

        Volt::actingAs($staff)
            ->test('mcmc.inquiries.index')
            ->set('status', 'Verified True')
            ->assertSee('Verified item')
            ->assertDontSee('Pending item');
    }

    public function test_awaiting_agency_review_filter_only_shows_unaccepted_jurisdiction(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create();

        Inquiry::factory()->create(['title' => 'Not yet accepted', 'status' => InquiryStatus::UnderInvestigation, 'agency_id' => $agency->id, 'jurisdiction_accepted_at' => null]);
        Inquiry::factory()->create(['title' => 'Already accepted', 'status' => InquiryStatus::UnderInvestigation, 'agency_id' => $agency->id, 'jurisdiction_accepted_at' => now()]);

        Volt::actingAs($staff)
            ->test('mcmc.inquiries.index')
            ->set('status', 'Awaiting Agency Review')
            ->assertSee('Not yet accepted')
            ->assertDontSee('Already accepted');
    }

    public function test_awaiting_clarification_filter_only_shows_inquiries_with_an_open_thread(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create();
        $agencyStaff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);

        $withClarify = Inquiry::factory()->create(['title' => 'Has open clarification', 'status' => InquiryStatus::UnderInvestigation, 'agency_id' => $agency->id, 'jurisdiction_accepted_at' => now()]);
        ClarificationThread::open($withClarify, $agencyStaff, ClarificationTopic::SourceVerification, ClarificationPriority::Normal, 'Can you confirm?');
        Inquiry::factory()->create(['title' => 'No clarification', 'status' => InquiryStatus::UnderInvestigation, 'agency_id' => $agency->id, 'jurisdiction_accepted_at' => now()]);

        Volt::actingAs($staff)
            ->test('mcmc.inquiries.index')
            ->set('status', 'Awaiting Clarification')
            ->assertSee('Has open clarification')
            ->assertDontSee('No clarification');
    }

    public function test_assigned_row_shows_agency_and_date_subtext(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create(['name' => 'Ministry of Test']);
        Inquiry::factory()->create(['title' => 'Assigned inquiry', 'status' => InquiryStatus::UnderInvestigation, 'agency_id' => $agency->id, 'reviewed_at' => now()]);

        Volt::actingAs($staff)
            ->test('mcmc.inquiries.index')
            ->assertSee('Assigned to Ministry of Test');
    }

    public function test_opening_the_panel_shows_details_evidence_and_activity_tabs(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $inquiry = Inquiry::factory()->create(['description' => 'A detailed claim description here.']);
        InquiryEvidence::create(['inquiry_id' => $inquiry->id, 'uploaded_by' => $staff->id, 'file_path' => 'evidence/proof.pdf', 'file_name' => 'proof.pdf', 'file_type' => 'application/pdf', 'file_size' => 1024]);

        $component = Volt::actingAs($staff)
            ->test('mcmc.inquiries.index')
            ->call('openPanel', $inquiry->id)
            ->assertSee('A detailed claim description here.');

        $component->set('panelTab', 'evidence')->assertSee('proof.pdf');
        $component->set('panelTab', 'activity');
    }

    public function test_panel_shows_the_open_clarification_request(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create();
        $agencyStaff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation]);
        ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::MissingEvidence, ClarificationPriority::Urgent, 'Need the original photo.');

        Volt::actingAs($staff)
            ->test('mcmc.inquiries.index')
            ->call('openPanel', $inquiry->id)
            ->assertSee('Clarification Request')
            ->assertSee('Missing or unclear evidence');
    }

    public function test_export_report_downloads_a_pdf(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['status' => InquiryStatus::Submitted]);

        Volt::actingAs($staff)
            ->test('mcmc.inquiries.index')
            ->call('exportPdf')
            ->assertFileDownloaded();
    }

    public function test_mcmc_staff_can_view_any_inquiry_detail_including_submitter_name(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $submitter = User::factory()->create(['role' => UserRole::Public, 'name' => 'Visible Submitter']);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $submitter->id]);

        Volt::actingAs($staff)
            ->test('mcmc.inquiries.show', ['inquiry' => $inquiry])
            ->assertSee('Visible Submitter');
    }

    public function test_agency_staff_cannot_access_all_inquiries_registry(): void
    {
        $agencyStaff = User::factory()->agencyStaff()->create();

        $this->actingAs($agencyStaff)->get(route('mcmc.inquiries.index'))->assertForbidden();
    }
}
