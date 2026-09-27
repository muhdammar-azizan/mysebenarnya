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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_stat_cards_reflect_the_agencys_own_inquiries(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $otherAgency = Agency::factory()->create();

        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation, 'jurisdiction_accepted_at' => now()]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation, 'jurisdiction_accepted_at' => null]);
        Inquiry::factory()->create(['agency_id' => $otherAgency->id, 'status' => InquiryStatus::UnderInvestigation]);

        $component = Volt::actingAs($staff)->test('agency.dashboard');

        $this->assertSame(2, $component->get('stats')['total']);
        $this->assertSame(2, $component->get('stats')['investigation']);
        $this->assertSame(1, $component->get('stats')['awaiting_review']);
    }

    public function test_clicking_a_stat_card_filters_the_recent_table_without_navigating(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation, 'jurisdiction_accepted_at' => now(), 'title' => 'Under active review']);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::Rejected, 'title' => 'Rejected case']);

        Volt::actingAs($staff)
            ->test('agency.dashboard')
            ->call('jumpToQuickFilter', 'investigation')
            ->assertSet('quickFilter', 'investigation')
            ->assertSee('Under active review')
            ->assertDontSee('Rejected case');
    }

    public function test_view_full_list_link_carries_the_quick_filter_forward(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);

        $params = Volt::actingAs($staff)
            ->test('agency.dashboard')
            ->call('jumpToQuickFilter', 'awaiting')
            ->get('viewFullListParams');

        $this->assertSame(['status' => 'awaiting'], $params);
    }

    public function test_performance_summary_shows_resolution_breakdown_and_platform_comparison(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);

        Inquiry::factory()->create([
            'agency_id' => $agency->id, 'status' => InquiryStatus::VerifiedTrue,
            'reviewed_at' => now()->subDays(2), 'resolved_at' => now(),
        ]);

        $otherAgency = Agency::factory()->create();
        Inquiry::factory()->create([
            'agency_id' => $otherAgency->id, 'status' => InquiryStatus::VerifiedTrue,
            'reviewed_at' => now()->subDays(10), 'resolved_at' => now(),
        ]);

        $component = Volt::actingAs($staff)->test('agency.dashboard');

        $comparison = $component->get('resolutionComparison');
        $this->assertSame(2.0, $comparison['mine']);
        $this->assertTrue($comparison['faster']);
        $component->assertSee('Faster than platform average');
    }

    public function test_clarify_alert_shows_when_mcmc_answers_a_clarification_request(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $mcmc = User::factory()->mcmcStaff()->create();
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation]);

        $thread = ClarificationThread::open($inquiry, $staff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Question.');
        $thread->reply($mcmc, 'Here is the answer.');

        Volt::actingAs($staff)
            ->test('agency.dashboard')
            ->assertSee('MCMC responded to 1 clarification request');
    }

    public function test_clarify_alert_shows_when_a_request_is_still_awaiting_mcmc(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation]);

        ClarificationThread::open($inquiry, $staff, ClarificationTopic::Other, ClarificationPriority::Normal, 'Question.');

        Volt::actingAs($staff)
            ->test('agency.dashboard')
            ->assertSee('inquiry awaiting MCMC clarification');
    }

    public function test_recently_assigned_table_shows_the_latest_note_from_mcmc(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $mcmc = User::factory()->mcmcStaff()->create();
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id]);
        InquiryActivityLog::create(['inquiry_id' => $inquiry->id, 'user_id' => $mcmc->id, 'action' => 'assigned', 'notes' => 'Please verify quickly.']);

        Volt::actingAs($staff)
            ->test('agency.dashboard')
            ->assertSee('Please verify quickly');
    }

    public function test_opening_the_panel_shows_inquiry_details(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id, 'description' => 'A detailed description here.']);

        Volt::actingAs($staff)
            ->test('agency.dashboard')
            ->call('viewPanel', $inquiry->id)
            ->assertSee('A detailed description here.');
    }
}
