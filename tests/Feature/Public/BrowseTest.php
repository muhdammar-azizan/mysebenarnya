<?php

namespace Tests\Feature\Public;

use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_public_inquiries_from_any_submitter(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Public]);
        $someoneElse = User::factory()->create(['role' => UserRole::Public]);

        Inquiry::factory()->create(['submitted_by' => $someoneElse->id, 'title' => 'Publicly visible inquiry']);

        Volt::actingAs($viewer)
            ->test('public.browse.index')
            ->assertSee('Publicly visible inquiry');
    }

    public function test_discarded_inquiries_are_excluded_from_browse(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Public]);
        $submitter = User::factory()->create(['role' => UserRole::Public]);

        Inquiry::factory()->create(['submitted_by' => $submitter->id, 'title' => 'Discarded spam report', 'status' => InquiryStatus::Discarded]);

        Volt::actingAs($viewer)
            ->test('public.browse.index')
            ->assertDontSee('Discarded spam report');
    }

    public function test_viewing_a_discarded_inquiry_via_browse_is_forbidden(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Public]);
        $submitter = User::factory()->create(['role' => UserRole::Public]);

        $inquiry = Inquiry::factory()->create(['submitted_by' => $submitter->id, 'status' => InquiryStatus::Discarded]);

        $this->actingAs($viewer)
            ->get(route('browse.show', $inquiry))
            ->assertForbidden();
    }

    public function test_browse_detail_does_not_reveal_the_submitters_identity(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Public]);
        $submitter = User::factory()->create(['role' => UserRole::Public, 'name' => 'Secret Submitter Name']);

        $inquiry = Inquiry::factory()->create(['submitted_by' => $submitter->id]);

        Volt::actingAs($viewer)
            ->test('public.browse.show', ['inquiry' => $inquiry])
            ->assertDontSee('Secret Submitter Name');
    }
}
