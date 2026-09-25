<?php

namespace Tests\Feature\Notifications;

use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use App\Notifications\InquiryStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class StatusChangeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitter_is_notified_when_mcmc_discards_an_inquiry(): void
    {
        Notification::fake();

        $staff = User::factory()->mcmcStaff()->create();
        $submitter = User::factory()->create();
        $inquiry = Inquiry::factory()->create(['submitted_by' => $submitter->id, 'status' => InquiryStatus::Submitted]);

        Volt::actingAs($staff)
            ->test('mcmc.triage.index')
            ->call('openReview', $inquiry->id)
            ->call('confirmDiscard');

        Notification::assertSentTo($submitter, InquiryStatusChanged::class);
    }

    public function test_submitter_is_notified_when_mcmc_first_assigns_an_inquiry(): void
    {
        Notification::fake();

        $staff = User::factory()->mcmcStaff()->create();
        $submitter = User::factory()->create();
        $agency = Agency::factory()->create();
        $inquiry = Inquiry::factory()->create(['submitted_by' => $submitter->id, 'status' => InquiryStatus::Submitted]);

        Volt::actingAs($staff)
            ->test('mcmc.triage.index')
            ->call('openAssign', $inquiry->id)
            ->set('selectedAgencyId', $agency->id)
            ->call('confirmAssign');

        Notification::assertSentTo($submitter, InquiryStatusChanged::class);
    }

    public function test_reassignment_does_not_re_notify_the_submitter(): void
    {
        Notification::fake();

        $staff = User::factory()->mcmcStaff()->create();
        $submitter = User::factory()->create();
        $oldAgency = Agency::factory()->create();
        $newAgency = Agency::factory()->create();
        $inquiry = Inquiry::factory()->create([
            'submitted_by' => $submitter->id,
            'status' => InquiryStatus::Rejected,
            'agency_id' => $oldAgency->id,
        ]);

        Volt::actingAs($staff)
            ->test('mcmc.triage.index')
            ->call('openAssign', $inquiry->id)
            ->set('selectedAgencyId', $newAgency->id)
            ->call('confirmAssign');

        Notification::assertNotSentTo($submitter, InquiryStatusChanged::class);
    }

    public function test_submitter_and_mcmc_reviewer_are_notified_when_agency_rejects_jurisdiction(): void
    {
        Notification::fake();

        $reviewer = User::factory()->mcmcStaff()->create();
        $submitter = User::factory()->create();
        $agency = Agency::factory()->create();
        $agencyStaff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create([
            'submitted_by' => $submitter->id,
            'agency_id' => $agency->id,
            'reviewed_by' => $reviewer->id,
            'status' => InquiryStatus::UnderInvestigation,
            'jurisdiction_accepted_at' => null,
        ]);

        Volt::actingAs($agencyStaff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->set('rejectReason', 'Outside our scope.')
            ->call('confirmReject');

        Notification::assertSentTo($submitter, InquiryStatusChanged::class);
        Notification::assertSentTo($reviewer, InquiryStatusChanged::class);
    }

    public function test_submitter_and_mcmc_reviewer_are_notified_when_agency_finalizes_a_verdict(): void
    {
        Notification::fake();

        $reviewer = User::factory()->mcmcStaff()->create();
        $submitter = User::factory()->create();
        $agency = Agency::factory()->create();
        $agencyStaff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create([
            'submitted_by' => $submitter->id,
            'agency_id' => $agency->id,
            'reviewed_by' => $reviewer->id,
            'status' => InquiryStatus::UnderInvestigation,
            'jurisdiction_accepted_at' => now(),
        ]);

        Volt::actingAs($agencyStaff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->set('verdict', 'verified')
            ->set('investigationNotes', 'Confirmed accurate after cross-checking official records.')
            ->call('confirmFinalize');

        Notification::assertSentTo($submitter, InquiryStatusChanged::class);
        Notification::assertSentTo($reviewer, InquiryStatusChanged::class);
    }
}
