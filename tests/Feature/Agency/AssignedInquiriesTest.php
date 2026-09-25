<?php

namespace Tests\Feature\Agency;

use App\Enums\AgencyStaffRole;
use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AssignedInquiriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_lists_inquiries_assigned_to_the_staff_members_own_agency(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agencyA->id]);

        Inquiry::factory()->create(['agency_id' => $agencyA->id, 'title' => 'Mine to review']);
        Inquiry::factory()->create(['agency_id' => $agencyB->id, 'title' => 'Not my agency']);

        Volt::actingAs($staff)
            ->test('agency.inquiries.index')
            ->assertSee('Mine to review')
            ->assertDontSee('Not my agency');
    }

    public function test_status_filter_narrows_results(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);

        Inquiry::factory()->create(['agency_id' => $agency->id, 'title' => 'Resolved item', 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'title' => 'Active item', 'status' => InquiryStatus::UnderInvestigation]);

        Volt::actingAs($staff)
            ->test('agency.inquiries.index')
            ->set('status', InquiryStatus::VerifiedTrue->value)
            ->assertSee('Resolved item')
            ->assertDontSee('Active item');
    }

    public function test_staff_from_another_agency_cannot_view_this_inquiry(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $outsider = User::factory()->agencyStaff()->create(['agency_id' => $agencyB->id]);
        $inquiry = Inquiry::factory()->create(['agency_id' => $agencyA->id]);

        $this->actingAs($outsider)->get(route('agency.inquiries.show', $inquiry))->assertForbidden();
    }

    public function test_public_user_cannot_access_agency_portal(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        $this->actingAs($user)->get(route('agency.inquiries.index'))->assertForbidden();
    }
}
