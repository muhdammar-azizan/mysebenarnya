<?php

namespace Tests\Feature\Agency;

use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use App\Notifications\InquiryStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AgencyNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_staff_sees_their_own_notifications(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id]);

        $staff->notify(new InquiryStatusChanged($inquiry, 'Under Investigation', 'Identified Fake'));

        Volt::actingAs($staff)
            ->test('agency.notifications.index')
            ->assertSee('Identified as Fake');
    }

    public function test_route_is_forbidden_for_mcmc_staff(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        $this->actingAs($staff)->get(route('agency.notifications.index'))->assertForbidden();
    }
}
