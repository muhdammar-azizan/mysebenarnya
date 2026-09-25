<?php

namespace Tests\Feature\Public;

use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
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

    public function test_activity_log_tab_shows_the_inquirys_history(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Public]);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $owner->id]);

        InquiryActivityLog::create([
            'inquiry_id' => $inquiry->id,
            'user_id' => $owner->id,
            'action' => 'submitted',
            'to_status' => InquiryStatus::Submitted->value,
            'notes' => 'Inquiry submitted by public user.',
        ]);

        Volt::actingAs($owner)
            ->test('public.inquiries.show', ['inquiry' => $inquiry])
            ->call('setTab', 'activity')
            ->assertSee('Inquiry submitted by public user.');
    }
}
