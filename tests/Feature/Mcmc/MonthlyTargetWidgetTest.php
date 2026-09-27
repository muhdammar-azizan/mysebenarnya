<?php

namespace Tests\Feature\Mcmc;

use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MonthlyTargetWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_this_months_processed_inquiries_by_type(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $inquiry = Inquiry::factory()->create();

        InquiryActivityLog::create(['inquiry_id' => $inquiry->id, 'user_id' => $staff->id, 'action' => 'verdict_finalized']);
        InquiryActivityLog::create(['inquiry_id' => $inquiry->id, 'user_id' => $staff->id, 'action' => 'discarded']);
        InquiryActivityLog::create(['inquiry_id' => $inquiry->id, 'user_id' => $staff->id, 'action' => 'reassigned']);

        $component = Volt::actingAs($staff)->test('mcmc.monthly-target-widget');

        $breakdown = $component->instance()->breakdown;

        $this->assertSame(1, $breakdown['validated']);
        $this->assertSame(1, $breakdown['discarded']);
        $this->assertSame(1, $breakdown['reassigned']);
        $this->assertSame(3, $breakdown['total']);
    }

    public function test_modal_opens_and_closes(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)
            ->test('mcmc.monthly-target-widget')
            ->call('openModal')
            ->assertSet('modalOpen', true)
            ->call('closeModal')
            ->assertSet('modalOpen', false);
    }
}
