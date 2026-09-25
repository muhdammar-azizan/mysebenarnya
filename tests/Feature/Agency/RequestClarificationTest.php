<?php

namespace Tests\Feature\Agency;

use App\Enums\ClarificationTopic;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\ClarificationThread;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RequestClarificationTest extends TestCase
{
    use RefreshDatabase;

    protected function assignedInquiry(): array
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'status' => InquiryStatus::UnderInvestigation,
            'jurisdiction_accepted_at' => now(),
        ]);

        return [$staff, $inquiry];
    }

    public function test_agency_can_request_clarification_from_the_inquiry_page(): void
    {
        [$staff, $inquiry] = $this->assignedInquiry();

        Volt::actingAs($staff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->set('tab', 'clarify')
            ->set('clarifyTopic', ClarificationTopic::SourceVerification->value)
            ->set('clarifyText', 'Can MCMC confirm the original source of this claim?')
            ->call('submitClarify')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clarification_threads', [
            'inquiry_id' => $inquiry->id,
            'topic' => ClarificationTopic::SourceVerification->value,
        ]);
    }

    public function test_cannot_request_clarification_while_awaiting_jurisdiction_review(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'status' => InquiryStatus::UnderInvestigation,
            'jurisdiction_accepted_at' => null,
        ]);

        Volt::actingAs($staff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->call('openClarifyModal')
            ->assertForbidden();
    }

    public function test_cannot_request_a_second_clarification_while_one_is_active(): void
    {
        [$staff, $inquiry] = $this->assignedInquiry();

        ClarificationThread::open($inquiry, $staff, ClarificationTopic::Other, \App\Enums\ClarificationPriority::Normal, 'Already asked.');

        Volt::actingAs($staff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->call('openClarifyModal')
            ->assertForbidden();
    }

    public function test_agency_can_follow_up_and_resolve_their_own_thread(): void
    {
        [$staff, $inquiry] = $this->assignedInquiry();
        $mcmc = User::factory()->mcmcStaff()->create();

        $thread = ClarificationThread::open($inquiry, $staff, ClarificationTopic::Other, \App\Enums\ClarificationPriority::Normal, 'Initial question.');
        $thread->reply($mcmc, 'MCMC answer.');

        Volt::actingAs($staff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->set('tab', 'clarify')
            ->set("replyText.{$thread->id}", 'Thanks, that resolves it.')
            ->call('sendReply', $thread->id)
            ->call('openCloseModal', $thread->id)
            ->set('closeNote', 'All clear now.')
            ->call('confirmCloseThread')
            ->assertHasNoErrors();

        $this->assertSame('closed', $thread->fresh()->status->value);
    }
}
