<?php

namespace Tests\Feature\Mcmc;

use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RecentActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_lists_the_authenticated_staff_members_own_actions(): void
    {
        $me = User::factory()->mcmcStaff()->create();
        $someoneElse = User::factory()->mcmcStaff()->create();

        $myInquiry = Inquiry::factory()->create(['status' => InquiryStatus::Discarded, 'title' => 'My discard']);
        InquiryActivityLog::create(['inquiry_id' => $myInquiry->id, 'user_id' => $me->id, 'action' => 'discarded']);

        $otherInquiry = Inquiry::factory()->create(['status' => InquiryStatus::Discarded, 'title' => 'Someone elses discard']);
        InquiryActivityLog::create(['inquiry_id' => $otherInquiry->id, 'user_id' => $someoneElse->id, 'action' => 'discarded']);

        Volt::actingAs($me)
            ->test('mcmc.recent-actions.index')
            ->assertSee('My discard')
            ->assertDontSee('Someone elses discard');
    }

    public function test_public_user_cannot_access_recent_actions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        $this->actingAs($user)->get(route('mcmc.recent-actions.index'))->assertForbidden();
    }
}
