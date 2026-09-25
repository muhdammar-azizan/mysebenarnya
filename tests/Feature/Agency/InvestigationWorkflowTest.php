<?php

namespace Tests\Feature\Agency;

use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InvestigationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function assignedInquiry(Agency $agency, bool $jurisdictionAccepted = false): Inquiry
    {
        return Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'status' => InquiryStatus::UnderInvestigation,
            'jurisdiction_accepted_at' => $jurisdictionAccepted ? now() : null,
        ]);
    }

    public function test_agency_can_accept_jurisdiction(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = $this->assignedInquiry($agency);

        Volt::actingAs($staff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->call('acceptJurisdiction');

        $inquiry->refresh();
        $this->assertNotNull($inquiry->jurisdiction_accepted_at);
        $this->assertFalse($inquiry->isAwaitingJurisdiction());
        $this->assertDatabaseHas('inquiry_activity_logs', [
            'inquiry_id' => $inquiry->id,
            'action' => 'jurisdiction_accepted',
        ]);
    }

    public function test_agency_can_reject_jurisdiction_with_a_reason(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = $this->assignedInquiry($agency);

        Volt::actingAs($staff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->set('rejectReason', 'This is outside our jurisdiction.')
            ->call('confirmReject')
            ->assertRedirect(route('agency.inquiries.index'));

        $inquiry->refresh();
        $this->assertSame(InquiryStatus::Rejected, $inquiry->status);
        $this->assertSame('This is outside our jurisdiction.', $inquiry->resolution_notes);
        $this->assertDatabaseHas('inquiry_activity_logs', [
            'inquiry_id' => $inquiry->id,
            'action' => 'jurisdiction_rejected',
        ]);
    }

    public function test_rejecting_jurisdiction_requires_a_reason(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = $this->assignedInquiry($agency);

        Volt::actingAs($staff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->set('rejectReason', '')
            ->call('confirmReject')
            ->assertHasErrors(['rejectReason']);

        $this->assertSame(InquiryStatus::UnderInvestigation, $inquiry->fresh()->status);
    }

    public function test_cannot_review_jurisdiction_once_already_accepted(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = $this->assignedInquiry($agency, jurisdictionAccepted: true);

        Volt::actingAs($staff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->call('acceptJurisdiction')
            ->assertForbidden();
    }

    public function test_agency_can_save_draft_investigation_notes_without_changing_status(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = $this->assignedInquiry($agency, jurisdictionAccepted: true);

        Volt::actingAs($staff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->set('investigationNotes', 'Still gathering evidence from the source.')
            ->call('saveDraft');

        $inquiry->refresh();
        $this->assertSame(InquiryStatus::UnderInvestigation, $inquiry->status);
        $this->assertNull($inquiry->resolution_notes);
        $this->assertDatabaseHas('inquiry_activity_logs', [
            'inquiry_id' => $inquiry->id,
            'action' => 'investigation_updated',
            'notes' => 'Still gathering evidence from the source.',
        ]);
    }

    public function test_agency_can_finalize_a_verified_true_verdict(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = $this->assignedInquiry($agency, jurisdictionAccepted: true);

        Volt::actingAs($staff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->set('verdict', 'verified')
            ->set('investigationNotes', 'Cross-checked with official records and confirmed accurate.')
            ->call('confirmFinalize')
            ->assertRedirect(route('agency.inquiries.index'));

        $inquiry->refresh();
        $this->assertSame(InquiryStatus::VerifiedTrue, $inquiry->status);
        $this->assertNotNull($inquiry->resolved_at);
        $this->assertSame('Cross-checked with official records and confirmed accurate.', $inquiry->resolution_notes);
        $this->assertDatabaseHas('inquiry_activity_logs', [
            'inquiry_id' => $inquiry->id,
            'action' => 'verdict_finalized',
            'to_status' => InquiryStatus::VerifiedTrue->value,
        ]);
    }

    public function test_agency_can_finalize_an_identified_fake_verdict(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = $this->assignedInquiry($agency, jurisdictionAccepted: true);

        Volt::actingAs($staff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->set('verdict', 'fake')
            ->set('investigationNotes', 'No supporting evidence found; claim is fabricated.')
            ->call('confirmFinalize');

        $this->assertSame(InquiryStatus::IdentifiedFake, $inquiry->fresh()->status);
    }

    public function test_finalizing_requires_notes(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = $this->assignedInquiry($agency, jurisdictionAccepted: true);

        Volt::actingAs($staff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->set('verdict', 'verified')
            ->set('investigationNotes', '')
            ->call('confirmFinalize')
            ->assertHasErrors(['investigationNotes']);

        $this->assertSame(InquiryStatus::UnderInvestigation, $inquiry->fresh()->status);
    }

    public function test_cannot_investigate_while_still_awaiting_jurisdiction_review(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = $this->assignedInquiry($agency, jurisdictionAccepted: false);

        Volt::actingAs($staff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->set('investigationNotes', 'Trying to sneak a draft in early.')
            ->call('saveDraft')
            ->assertForbidden();
    }
}
