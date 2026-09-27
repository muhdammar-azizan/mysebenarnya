<?php

namespace Tests\Feature\Agency;

use App\Enums\ClarificationPriority;
use App\Enums\ClarificationTopic;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\ClarificationThread;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_update_and_resolution_actions_are_categorized_correctly(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id, 'title' => 'Categorized case']);

        InquiryActivityLog::create(['inquiry_id' => $inquiry->id, 'user_id' => $staff->id, 'action' => 'jurisdiction_accepted']);
        InquiryActivityLog::create(['inquiry_id' => $inquiry->id, 'user_id' => $staff->id, 'action' => 'verdict_finalized', 'to_status' => InquiryStatus::VerifiedTrue->value]);

        $component = Volt::actingAs($staff)->test('agency.activity.index');
        $tabs = collect($component->get('filterTabs'))->keyBy('key');

        $this->assertSame(1, $tabs['update']['count']);
        $this->assertSame(1, $tabs['resolve']['count']);
        $component->assertSee('accepted jurisdiction')->assertSee('resolved inquiry as');
    }

    public function test_filter_tabs_narrow_the_visible_events(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id]);

        InquiryActivityLog::create(['inquiry_id' => $inquiry->id, 'user_id' => $staff->id, 'action' => 'jurisdiction_accepted']);
        InquiryActivityLog::create(['inquiry_id' => $inquiry->id, 'user_id' => $staff->id, 'action' => 'verdict_finalized', 'to_status' => InquiryStatus::VerifiedTrue->value]);

        Volt::actingAs($staff)
            ->test('agency.activity.index')
            ->set('filter', 'resolve')
            ->assertSee('resolved inquiry as')
            ->assertDontSee('accepted jurisdiction');
    }

    public function test_clarification_thread_events_on_the_agencys_own_case_are_merged_in(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $mcmc = User::factory()->mcmcStaff()->create();
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation]);

        $thread = ClarificationThread::open($inquiry, $staff, ClarificationTopic::SourceVerification, ClarificationPriority::Normal, 'Can MCMC confirm the source?');
        $thread->reply($mcmc, 'Yes, confirmed via official records.');

        $component = Volt::actingAs($staff)->test('agency.activity.index');

        $component->assertSee('Can MCMC confirm the source')
            ->assertSee('MCMC responded to clarification');

        $tabs = collect($component->get('filterTabs'))->keyBy('key');
        $this->assertSame(2, $tabs['clarify']['count']);
    }

    public function test_consultation_advice_given_to_another_agency_is_merged_in(): void
    {
        $owningAgency = Agency::factory()->create();
        $owningStaff = User::factory()->agencyStaff()->create(['agency_id' => $owningAgency->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $owningAgency->id, 'status' => InquiryStatus::UnderInvestigation, 'jurisdiction_accepted_at' => now()]);
        $mcmc = User::factory()->mcmcStaff()->create();
        $consultedAgency = Agency::factory()->create();
        $consultedStaff = User::factory()->agencyStaff()->create(['agency_id' => $consultedAgency->id]);

        $thread = ClarificationThread::open($inquiry, $owningStaff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Need advice.');
        $consult = $thread->inviteConsult($mcmc, $consultedAgency, 'What do you think?');
        $consult->reply($consultedStaff, 'Based on our records, this looks accurate.');

        Volt::actingAs($consultedStaff)
            ->test('agency.activity.index')
            ->assertSee('sent consultation advice')
            ->assertSee('Based on our records');
    }

    public function test_activity_from_an_unrelated_agency_never_appears(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $staffA = User::factory()->agencyStaff()->create(['agency_id' => $agencyA->id]);
        $staffB = User::factory()->agencyStaff()->create(['agency_id' => $agencyB->id]);
        $inquiryB = Inquiry::factory()->create(['agency_id' => $agencyB->id]);

        InquiryActivityLog::create(['inquiry_id' => $inquiryB->id, 'user_id' => $staffB->id, 'action' => 'jurisdiction_accepted']);

        Volt::actingAs($staffA)
            ->test('agency.activity.index')
            ->assertSee('No activity in this filter');
    }
}
