<?php

namespace Tests\Feature\Clarification;

use App\Enums\ClarificationPriority;
use App\Enums\ClarificationStatus;
use App\Enums\ClarificationTopic;
use App\Enums\ConsultStatus;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\ClarificationThread;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ClarificationModelTest extends TestCase
{
    use RefreshDatabase;

    protected function agencyInquiry(): array
    {
        $agency = Agency::factory()->create();
        $agencyStaff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'status' => InquiryStatus::UnderInvestigation,
            'jurisdiction_accepted_at' => now(),
        ]);

        return [$agency, $agencyStaff, $inquiry];
    }

    public function test_opening_a_thread_creates_the_first_message_from_the_agency(): void
    {
        [$agency, $agencyStaff, $inquiry] = $this->agencyInquiry();

        $thread = ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::SourceVerification, ClarificationPriority::Urgent, 'Can you confirm the source?');

        $this->assertSame(ClarificationStatus::Open, $thread->status);
        $this->assertTrue($thread->unread_by_mcmc);
        $this->assertFalse($thread->unread_by_agency);
        $this->assertCount(1, $thread->messages);
        $this->assertSame($agencyStaff->id, $thread->messages->first()->user_id);
    }

    public function test_cannot_open_a_second_active_thread_for_the_same_inquiry(): void
    {
        [, $agencyStaff, $inquiry] = $this->agencyInquiry();

        ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'First request.');

        $this->expectException(RuntimeException::class);
        ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Second request.');
    }

    public function test_a_new_thread_can_be_opened_after_the_first_is_closed(): void
    {
        [, $agencyStaff, $inquiry] = $this->agencyInquiry();

        $first = ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'First request.');
        $first->close($agencyStaff, 'Resolved.', 'agency');

        $second = ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Second request.');

        $this->assertNotSame($first->id, $second->id);
    }

    public function test_mcmc_reply_flips_status_to_answered(): void
    {
        [, $agencyStaff, $inquiry] = $this->agencyInquiry();
        $mcmc = User::factory()->mcmcStaff()->create();

        $thread = ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Question?');
        $thread->reply($mcmc, 'Here is the answer.');

        $this->assertSame(ClarificationStatus::Answered, $thread->status);
        $this->assertTrue($thread->unread_by_agency);
        $this->assertFalse($thread->unread_by_mcmc);
    }

    public function test_agency_follow_up_flips_status_back_to_open(): void
    {
        [, $agencyStaff, $inquiry] = $this->agencyInquiry();
        $mcmc = User::factory()->mcmcStaff()->create();

        $thread = ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Question?');
        $thread->reply($mcmc, 'Answer.');
        $thread->reply($agencyStaff, 'Follow-up question.');

        $this->assertSame(ClarificationStatus::Open, $thread->status);
        $this->assertTrue($thread->unread_by_mcmc);
    }

    public function test_cannot_reply_to_a_closed_thread(): void
    {
        [, $agencyStaff, $inquiry] = $this->agencyInquiry();

        $thread = ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Question?');
        $thread->close($agencyStaff, 'Resolved.', 'agency');

        $this->expectException(RuntimeException::class);
        $thread->reply($agencyStaff, 'Too late.');
    }

    public function test_inviting_a_consult_creates_a_pending_consult_and_system_message(): void
    {
        [, $agencyStaff, $inquiry] = $this->agencyInquiry();
        $mcmc = User::factory()->mcmcStaff()->create();
        $otherAgency = Agency::factory()->create();

        $thread = ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Question?');
        $consult = $thread->inviteConsult($mcmc, $otherAgency, 'Please advise on this angle.');

        $this->assertSame(ConsultStatus::Pending, $consult->status);
        $this->assertTrue($consult->unread);
        $this->assertTrue($thread->messages()->where('is_system', true)->where('consult_agency_id', $otherAgency->id)->exists());
    }

    public function test_cannot_invite_the_case_owning_agency_to_consult_on_its_own_case(): void
    {
        [$agency, $agencyStaff, $inquiry] = $this->agencyInquiry();
        $mcmc = User::factory()->mcmcStaff()->create();

        $thread = ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Question?');

        $this->expectException(RuntimeException::class);
        $thread->inviteConsult($mcmc, $agency, 'This should fail.');
    }

    public function test_cannot_invite_the_same_agency_twice_while_active(): void
    {
        [, $agencyStaff, $inquiry] = $this->agencyInquiry();
        $mcmc = User::factory()->mcmcStaff()->create();
        $otherAgency = Agency::factory()->create();

        $thread = ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Question?');
        $thread->inviteConsult($mcmc, $otherAgency, 'First invite.');

        $this->expectException(RuntimeException::class);
        $thread->inviteConsult($mcmc, $otherAgency, 'Duplicate invite.');
    }

    public function test_consult_reply_creates_a_tagged_message_not_a_direct_field(): void
    {
        [, $agencyStaff, $inquiry] = $this->agencyInquiry();
        $mcmc = User::factory()->mcmcStaff()->create();
        $otherAgency = Agency::factory()->create();
        $otherAgencyStaff = User::factory()->agencyStaff()->create(['agency_id' => $otherAgency->id]);

        $thread = ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Question?');
        $consult = $thread->inviteConsult($mcmc, $otherAgency, 'Please advise.');

        $consult->reply($otherAgencyStaff, 'Here is our advice.');

        $this->assertSame(ConsultStatus::Responded, $consult->fresh()->status);
        $taggedMessage = $thread->messages()->where('consult_agency_id', $otherAgency->id)->where('is_system', false)->first();
        $this->assertNotNull($taggedMessage);
        $this->assertSame('Here is our advice.', $taggedMessage->message);
        $this->assertTrue($thread->fresh()->unread_by_mcmc);
    }

    public function test_closing_a_thread_ends_all_active_consults(): void
    {
        [, $agencyStaff, $inquiry] = $this->agencyInquiry();
        $mcmc = User::factory()->mcmcStaff()->create();
        $otherAgency = Agency::factory()->create();

        $thread = ClarificationThread::open($inquiry, $agencyStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Question?');
        $consult = $thread->inviteConsult($mcmc, $otherAgency, 'Advise please.');

        $thread->close($mcmc, 'Resolved.', 'mcmc');

        $this->assertSame(ConsultStatus::Ended, $consult->fresh()->status);
    }
}
