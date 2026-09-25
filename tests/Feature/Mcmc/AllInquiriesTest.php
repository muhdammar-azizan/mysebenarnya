<?php

namespace Tests\Feature\Mcmc;

use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AllInquiriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_inquiries_across_every_status(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Inquiry::factory()->create(['title' => 'A discarded one', 'status' => InquiryStatus::Discarded]);
        Inquiry::factory()->create(['title' => 'A submitted one', 'status' => InquiryStatus::Submitted]);

        Volt::actingAs($staff)
            ->test('mcmc.inquiries.index')
            ->assertSee('A discarded one')
            ->assertSee('A submitted one');
    }

    public function test_status_filter_narrows_results(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Inquiry::factory()->create(['title' => 'Verified item', 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['title' => 'Pending item', 'status' => InquiryStatus::Submitted]);

        Volt::actingAs($staff)
            ->test('mcmc.inquiries.index')
            ->set('status', InquiryStatus::VerifiedTrue->value)
            ->assertSee('Verified item')
            ->assertDontSee('Pending item');
    }

    public function test_mcmc_staff_can_view_any_inquiry_detail_including_submitter_name(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $submitter = User::factory()->create(['role' => UserRole::Public, 'name' => 'Visible Submitter']);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $submitter->id]);

        Volt::actingAs($staff)
            ->test('mcmc.inquiries.show', ['inquiry' => $inquiry])
            ->assertSee('Visible Submitter');
    }

    public function test_agency_staff_cannot_access_all_inquiries_registry(): void
    {
        $agencyStaff = User::factory()->agencyStaff()->create();

        $this->actingAs($agencyStaff)->get(route('mcmc.inquiries.index'))->assertForbidden();
    }
}
