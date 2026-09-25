<?php

namespace Tests\Feature\Mcmc;

use App\Models\Inquiry;
use App\Models\User;
use App\Notifications\InquiryStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class McmcNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcmc_staff_sees_their_own_notifications(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $inquiry = Inquiry::factory()->create();

        $staff->notify(new InquiryStatusChanged($inquiry, 'Submitted', 'Verified True'));

        Volt::actingAs($staff)
            ->test('mcmc.notifications.index')
            ->assertSee('Verified as True');
    }

    public function test_route_is_forbidden_for_public_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('mcmc.notifications.index'))->assertForbidden();
    }
}
