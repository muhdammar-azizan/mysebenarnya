<?php

namespace Tests\Feature\Agency;

use App\Enums\AgencyStaffRole;
use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AssignedInquiriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_lists_inquiries_assigned_to_the_staff_members_own_agency(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agencyA->id]);

        Inquiry::factory()->create(['agency_id' => $agencyA->id, 'title' => 'Mine to review']);
        Inquiry::factory()->create(['agency_id' => $agencyB->id, 'title' => 'Not my agency']);

        Volt::actingAs($staff)
            ->test('agency.inquiries.index')
            ->assertSee('Mine to review')
            ->assertDontSee('Not my agency');
    }

    public function test_status_filter_narrows_results(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);

        Inquiry::factory()->create(['agency_id' => $agency->id, 'title' => 'Resolved item', 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'title' => 'Active item', 'status' => InquiryStatus::UnderInvestigation]);

        Volt::actingAs($staff)
            ->test('agency.inquiries.index')
            ->set('status', InquiryStatus::VerifiedTrue->value)
            ->assertSee('Resolved item')
            ->assertDontSee('Active item');
    }

    public function test_staff_from_another_agency_cannot_view_this_inquiry(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $outsider = User::factory()->agencyStaff()->create(['agency_id' => $agencyB->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $agencyA->id]);

        $this->actingAs($outsider)->get(route('agency.inquiries.show', $inquiry))->assertForbidden();
    }

    public function test_public_user_cannot_access_agency_portal(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        $this->actingAs($user)->get(route('agency.inquiries.index'))->assertForbidden();
    }

    public function test_consultations_tab_lists_consults_addressed_to_this_agency(): void
    {
        $owningAgency = Agency::factory()->create();
        $owningStaff = User::factory()->agencyStaff()->create(['agency_id' => $owningAgency->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $owningAgency->id, 'status' => InquiryStatus::UnderInvestigation, 'jurisdiction_accepted_at' => now(), 'title' => 'Case needing advice']);
        $mcmc = User::factory()->mcmcStaff()->create();
        $consultedAgency = Agency::factory()->create();
        $consultedStaff = User::factory()->agencyStaff()->create(['agency_id' => $consultedAgency->id]);

        $thread = \App\Models\ClarificationThread::open($inquiry, $owningStaff, \App\Enums\ClarificationTopic::Other, \App\Enums\ClarificationPriority::Normal, 'Question for MCMC.');
        $thread->inviteConsult($mcmc, $consultedAgency, 'What do you think?');

        Volt::actingAs($consultedStaff)
            ->test('agency.inquiries.index')
            ->set('tab', 'consult')
            ->assertSee('Case needing advice');
    }

    public function test_consultations_tab_shows_a_pending_badge(): void
    {
        $owningAgency = Agency::factory()->create();
        $owningStaff = User::factory()->agencyStaff()->create(['agency_id' => $owningAgency->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $owningAgency->id, 'status' => InquiryStatus::UnderInvestigation, 'jurisdiction_accepted_at' => now()]);
        $mcmc = User::factory()->mcmcStaff()->create();
        $consultedAgency = Agency::factory()->create();
        $consultedStaff = User::factory()->agencyStaff()->create(['agency_id' => $consultedAgency->id]);

        $thread = \App\Models\ClarificationThread::open($inquiry, $owningStaff, \App\Enums\ClarificationTopic::Other, \App\Enums\ClarificationPriority::Normal, 'Question for MCMC.');
        $thread->inviteConsult($mcmc, $consultedAgency, 'What do you think?');

        Volt::actingAs($consultedStaff)
            ->test('agency.inquiries.index')
            ->assertSeeInOrder(['Consultations', '1']);
    }

    public function test_assigning_an_inquiry_notifies_the_agencys_staff(): void
    {
        Notification::fake();

        $mcmc = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create();
        $agencyStaff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create(['status' => InquiryStatus::Submitted]);

        Volt::actingAs($mcmc)
            ->test('mcmc.triage.index')
            ->call('openAssign', $inquiry->id)
            ->set('selectedAgencyId', $agency->id)
            ->call('confirmAssign');

        Notification::assertSentTo($agencyStaff, \App\Notifications\InquiryAssignedToAgency::class);
    }

    public function test_inviting_an_agency_to_consult_notifies_its_staff(): void
    {
        Notification::fake();

        $owningAgency = Agency::factory()->create();
        $owningStaff = User::factory()->agencyStaff()->create(['agency_id' => $owningAgency->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $owningAgency->id, 'status' => InquiryStatus::UnderInvestigation, 'jurisdiction_accepted_at' => now()]);
        $mcmc = User::factory()->mcmcStaff()->create();
        $consultedAgency = Agency::factory()->create();
        $consultedStaff = User::factory()->agencyStaff()->create(['agency_id' => $consultedAgency->id]);

        $thread = \App\Models\ClarificationThread::open($inquiry, $owningStaff, \App\Enums\ClarificationTopic::Other, \App\Enums\ClarificationPriority::Normal, 'Question for MCMC.');

        Volt::actingAs($mcmc)
            ->test('mcmc.clarifications.show', ['thread' => $thread])
            ->call('openInviteModal')
            ->set('inviteAgencyId', $consultedAgency->id)
            ->set('inviteQuestion', 'Can you weigh in on this specific angle?')
            ->call('confirmInvite');

        Notification::assertSentTo($consultedStaff, \App\Notifications\AgencyConsultRequested::class);
    }
}
