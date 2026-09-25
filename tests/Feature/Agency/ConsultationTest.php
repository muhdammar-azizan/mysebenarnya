<?php

namespace Tests\Feature\Agency;

use App\Enums\ClarificationPriority;
use App\Enums\ClarificationTopic;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\ClarificationThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ConsultationTest extends TestCase
{
    use RefreshDatabase;

    protected function pendingConsult(): array
    {
        $owningAgency = Agency::factory()->create();
        $owningStaff = User::factory()->agencyStaff()->create(['agency_id' => $owningAgency->id]);
        $inquiry = \App\Models\Inquiry::factory()->create(['agency_id' => $owningAgency->id, 'status' => InquiryStatus::UnderInvestigation, 'jurisdiction_accepted_at' => now()]);
        $mcmc = User::factory()->mcmcStaff()->create();
        $consultedAgency = Agency::factory()->create();
        $consultedStaff = User::factory()->agencyStaff()->create(['agency_id' => $consultedAgency->id]);

        $thread = ClarificationThread::open($inquiry, $owningStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Question for MCMC.');
        $consult = $thread->inviteConsult($mcmc, $consultedAgency, 'What do you think about this?');

        return [$consultedStaff, $consult, $owningStaff];
    }

    public function test_consulted_agency_sees_the_invitation_in_their_inbox(): void
    {
        [$consultedStaff, $consult] = $this->pendingConsult();

        Volt::actingAs($consultedStaff)
            ->test('agency.consultations.index')
            ->assertSee($consult->thread->inquiry->title);
    }

    public function test_consulted_agency_can_reply_with_advice(): void
    {
        [$consultedStaff, $consult] = $this->pendingConsult();

        Volt::actingAs($consultedStaff)
            ->test('agency.consultations.show', ['consult' => $consult])
            ->set('replyText', 'Based on our records, this looks consistent.')
            ->call('sendReply')
            ->assertHasNoErrors();

        $this->assertSame('responded', $consult->fresh()->status->value);
    }

    public function test_consulted_agency_has_no_way_to_close_the_thread(): void
    {
        [$consultedStaff, $consult, $owningStaff] = $this->pendingConsult();

        $this->assertFalse((new \App\Policies\ClarificationThreadPolicy)->close($consultedStaff, $consult->thread));
        $this->assertTrue((new \App\Policies\ClarificationThreadPolicy)->close($owningStaff, $consult->thread));
    }

    public function test_staff_from_an_unrelated_agency_cannot_view_or_reply_to_the_consult(): void
    {
        [, $consult] = $this->pendingConsult();
        $unrelatedAgency = Agency::factory()->create();
        $unrelatedStaff = User::factory()->agencyStaff()->create(['agency_id' => $unrelatedAgency->id]);

        $this->actingAs($unrelatedStaff)->get(route('agency.consultations.show', $consult))->assertForbidden();
    }

    public function test_case_owning_agency_cannot_be_invited_to_consult_on_its_own_case_via_the_ui(): void
    {
        $owningAgency = Agency::factory()->create();
        $owningStaff = User::factory()->agencyStaff()->create(['agency_id' => $owningAgency->id]);
        $inquiry = \App\Models\Inquiry::factory()->create(['agency_id' => $owningAgency->id, 'status' => InquiryStatus::UnderInvestigation, 'jurisdiction_accepted_at' => now()]);
        $mcmc = User::factory()->mcmcStaff()->create();

        $thread = ClarificationThread::open($inquiry, $owningStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Question.');

        Volt::actingAs($mcmc)
            ->test('mcmc.clarifications.show', ['thread' => $thread])
            ->call('openInviteModal')
            ->set('inviteAgencyId', $owningAgency->id)
            ->set('inviteQuestion', 'Should not be allowed.')
            ->call('confirmInvite')
            ->assertHasErrors(['inviteAgencyId']);
    }
}
