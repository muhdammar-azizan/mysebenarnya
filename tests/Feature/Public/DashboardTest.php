<?php

namespace Tests\Feature\Public;

use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_public_inquiries_from_any_submitter(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Public]);
        $someoneElse = User::factory()->create(['role' => UserRole::Public]);

        Inquiry::factory()->create(['submitted_by' => $someoneElse->id, 'title' => 'Publicly visible inquiry']);

        Volt::actingAs($viewer)
            ->test('public.dashboard')
            ->assertSee('Publicly visible inquiry');
    }

    public function test_discarded_inquiries_are_excluded_from_the_feed(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Public]);
        $submitter = User::factory()->create(['role' => UserRole::Public]);

        Inquiry::factory()->create(['submitted_by' => $submitter->id, 'title' => 'Discarded spam report', 'status' => InquiryStatus::Discarded]);

        Volt::actingAs($viewer)
            ->test('public.dashboard')
            ->assertDontSee('Discarded spam report');
    }

    public function test_status_filter_narrows_the_feed(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Public]);

        Inquiry::factory()->create(['title' => 'A verified claim', 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['title' => 'A pending claim', 'status' => InquiryStatus::UnderInvestigation]);

        Volt::actingAs($viewer)
            ->test('public.dashboard')
            ->set('status', InquiryStatus::VerifiedTrue->value)
            ->assertSee('A verified claim')
            ->assertDontSee('A pending claim');
    }

    public function test_hero_slide_can_set_the_verified_status_filter(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Public]);

        Volt::actingAs($viewer)
            ->test('public.dashboard')
            ->call('setStatusFilter', InquiryStatus::VerifiedTrue->value)
            ->assertSet('status', InquiryStatus::VerifiedTrue->value);
    }
}
