<?php

namespace Tests\Feature\Mcmc;

use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TriageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_tab_lists_submitted_inquiries(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $inquiry = Inquiry::factory()->create(['status' => InquiryStatus::Submitted, 'title' => 'Needs triage']);

        Volt::actingAs($staff)
            ->test('mcmc.triage.index')
            ->assertSee('Needs triage');
    }

    public function test_discarding_an_inquiry_marks_it_terminal_and_logs_it(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $inquiry = Inquiry::factory()->create(['status' => InquiryStatus::Submitted]);

        Volt::actingAs($staff)
            ->test('mcmc.triage.index')
            ->call('openReview', $inquiry->id)
            ->set('discardNotes', 'Not credible.')
            ->call('confirmDiscard');

        $inquiry->refresh();
        $this->assertSame(InquiryStatus::Discarded, $inquiry->status);
        $this->assertSame($staff->id, $inquiry->reviewed_by);
        $this->assertDatabaseHas('inquiry_activity_logs', [
            'inquiry_id' => $inquiry->id,
            'action' => 'discarded',
            'notes' => 'Not credible.',
        ]);
    }

    public function test_bulk_discard_marks_all_selected_inquiries_terminal(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $a = Inquiry::factory()->create(['status' => InquiryStatus::Submitted]);
        $b = Inquiry::factory()->create(['status' => InquiryStatus::Submitted]);

        Volt::actingAs($staff)
            ->test('mcmc.triage.index')
            ->call('toggleSelect', $a->id)
            ->call('toggleSelect', $b->id)
            ->call('confirmBulkDiscard');

        $this->assertSame(InquiryStatus::Discarded, $a->fresh()->status);
        $this->assertSame(InquiryStatus::Discarded, $b->fresh()->status);
    }

    public function test_validating_and_assigning_sets_agency_and_under_investigation(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create();
        $inquiry = Inquiry::factory()->create(['status' => InquiryStatus::Submitted]);

        Volt::actingAs($staff)
            ->test('mcmc.triage.index')
            ->call('openAssign', $inquiry->id)
            ->set('selectedAgencyId', $agency->id)
            ->call('confirmAssign');

        $inquiry->refresh();
        $this->assertSame(InquiryStatus::UnderInvestigation, $inquiry->status);
        $this->assertSame($agency->id, $inquiry->agency_id);
        $this->assertTrue($inquiry->isAwaitingJurisdiction());
        $this->assertDatabaseHas('inquiry_activity_logs', [
            'inquiry_id' => $inquiry->id,
            'action' => 'assigned',
        ]);
    }

    public function test_reassigning_a_rejected_inquiry_moves_it_back_to_under_investigation(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $oldAgency = Agency::factory()->create();
        $newAgency = Agency::factory()->create();
        $inquiry = Inquiry::factory()->create([
            'status' => InquiryStatus::Rejected,
            'agency_id' => $oldAgency->id,
            'resolution_notes' => 'Outside our jurisdiction.',
        ]);

        Volt::actingAs($staff)
            ->test('mcmc.triage.index')
            ->set('tab', 'reassign')
            ->assertSee('Outside our jurisdiction')
            ->call('openAssign', $inquiry->id)
            ->set('selectedAgencyId', $newAgency->id)
            ->call('confirmAssign');

        $inquiry->refresh();
        $this->assertSame(InquiryStatus::UnderInvestigation, $inquiry->status);
        $this->assertSame($newAgency->id, $inquiry->agency_id);
        $this->assertNull($inquiry->resolution_notes);
        $this->assertDatabaseHas('inquiry_activity_logs', [
            'inquiry_id' => $inquiry->id,
            'action' => 'reassigned',
        ]);
    }

    public function test_assign_requires_an_agency_to_be_selected(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $inquiry = Inquiry::factory()->create(['status' => InquiryStatus::Submitted]);

        Volt::actingAs($staff)
            ->test('mcmc.triage.index')
            ->call('openAssign', $inquiry->id)
            ->call('confirmAssign')
            ->assertHasErrors(['selectedAgencyId']);
    }

    public function test_public_user_cannot_access_triage(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        $this->actingAs($user)->get(route('mcmc.triage.index'))->assertForbidden();
    }
}
