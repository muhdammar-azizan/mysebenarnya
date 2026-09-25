<?php

namespace Tests\Feature\Mcmc;

use App\Enums\ClarificationPriority;
use App\Enums\ClarificationTopic;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\ClarificationThread;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClarificationInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function openThread(): array
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation, 'jurisdiction_accepted_at' => now()]);
        $thread = ClarificationThread::open($inquiry, $staff, ClarificationTopic::SourceVerification, ClarificationPriority::Urgent, 'Can you confirm this?');

        return [$agency, $staff, $inquiry, $thread];
    }

    public function test_inbox_lists_active_threads(): void
    {
        [, , $inquiry] = $this->openThread();
        $mcmc = User::factory()->mcmcStaff()->create();

        Volt::actingAs($mcmc)
            ->test('mcmc.clarifications.index')
            ->assertSee($inquiry->title);
    }

    public function test_mcmc_can_reply_to_a_thread(): void
    {
        [, , , $thread] = $this->openThread();
        $mcmc = User::factory()->mcmcStaff()->create();

        Volt::actingAs($mcmc)
            ->test('mcmc.clarifications.show', ['thread' => $thread])
            ->set('replyText', 'Here is the confirmation you asked for.')
            ->call('sendReply')
            ->assertHasNoErrors();

        $this->assertSame('answered', $thread->fresh()->status->value);
    }

    public function test_mcmc_can_invite_another_agency_to_consult(): void
    {
        [, , , $thread] = $this->openThread();
        $mcmc = User::factory()->mcmcStaff()->create();
        $consultAgency = Agency::factory()->create();

        Volt::actingAs($mcmc)
            ->test('mcmc.clarifications.show', ['thread' => $thread])
            ->call('openInviteModal')
            ->set('inviteAgencyId', $consultAgency->id)
            ->set('inviteQuestion', 'Can you weigh in on this specific angle?')
            ->call('confirmInvite')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clarification_consults', [
            'clarification_thread_id' => $thread->id,
            'consulted_agency_id' => $consultAgency->id,
        ]);
    }

    public function test_mcmc_can_close_a_thread(): void
    {
        [, , , $thread] = $this->openThread();
        $mcmc = User::factory()->mcmcStaff()->create();

        Volt::actingAs($mcmc)
            ->test('mcmc.clarifications.show', ['thread' => $thread])
            ->set('closeNote', 'Handled directly with the agency offline.')
            ->call('confirmClose')
            ->assertHasNoErrors();

        $this->assertSame('closed', $thread->fresh()->status->value);
    }

    public function test_agency_staff_cannot_access_the_mcmc_clarification_inbox(): void
    {
        $agencyStaff = User::factory()->agencyStaff()->create();

        $this->actingAs($agencyStaff)->get(route('mcmc.clarifications.index'))->assertForbidden();
    }
}
