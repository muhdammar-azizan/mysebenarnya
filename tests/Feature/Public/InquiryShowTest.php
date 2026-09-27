<?php

namespace Tests\Feature\Public;

use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InquiryShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_their_own_inquiry(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Public]);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $owner->id, 'title' => 'My inquiry title']);

        Volt::actingAs($owner)
            ->test('public.inquiries.show', ['inquiry' => $inquiry])
            ->assertSee('My inquiry title');
    }

    public function test_a_different_public_user_cannot_view_someone_elses_inquiry(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Public]);
        $stranger = User::factory()->create(['role' => UserRole::Public]);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $owner->id]);

        $this->actingAs($stranger)
            ->get(route('inquiries.show', $inquiry))
            ->assertForbidden();
    }

    public function test_status_history_shows_submitted_as_always_done(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Public]);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $owner->id, 'status' => InquiryStatus::Submitted]);

        Volt::actingAs($owner)
            ->test('public.inquiries.show', ['inquiry' => $inquiry])
            ->assertSee('Status History')
            ->assertSee('Inquiry received')
            ->assertSee('Awaiting review assignment');
    }

    public function test_status_history_shows_the_assigned_agency_and_final_outcome(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Public]);
        $agency = \App\Models\Agency::factory()->create(['name' => 'Ministry of Test']);
        $inquiry = Inquiry::factory()->create([
            'submitted_by' => $owner->id,
            'status' => InquiryStatus::VerifiedTrue,
            'agency_id' => $agency->id,
            'reviewed_at' => now()->subDays(2),
            'resolved_at' => now(),
            'resolution_notes' => 'Matches the official statement.',
        ]);

        Volt::actingAs($owner)
            ->test('public.inquiries.show', ['inquiry' => $inquiry])
            ->assertSee('Assigned to Ministry of Test for review')
            ->assertSee('Confirmed accurate')
            ->assertSee('Matches the official statement.');
    }
}
