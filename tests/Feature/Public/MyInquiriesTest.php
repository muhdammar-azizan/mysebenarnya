<?php

namespace Tests\Feature\Public;

use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MyInquiriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_lists_the_authenticated_users_own_inquiries(): void
    {
        $me = User::factory()->create(['role' => UserRole::Public]);
        $someoneElse = User::factory()->create(['role' => UserRole::Public]);

        $mine = Inquiry::factory()->create(['submitted_by' => $me->id, 'title' => 'My own inquiry']);
        Inquiry::factory()->create(['submitted_by' => $someoneElse->id, 'title' => 'Not mine']);

        Volt::actingAs($me)
            ->test('public.inquiries.index')
            ->assertSee('My own inquiry')
            ->assertDontSee('Not mine');

        $this->assertTrue($mine->submitted_by === $me->id);
    }

    public function test_status_filter_narrows_the_list(): void
    {
        $me = User::factory()->create(['role' => UserRole::Public]);

        Inquiry::factory()->create(['submitted_by' => $me->id, 'title' => 'Verified one', 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['submitted_by' => $me->id, 'title' => 'Submitted one', 'status' => InquiryStatus::Submitted]);

        Volt::actingAs($me)
            ->test('public.inquiries.index')
            ->set('status', InquiryStatus::VerifiedTrue->value)
            ->assertSee('Verified one')
            ->assertDontSee('Submitted one');
    }

    public function test_search_filters_by_title(): void
    {
        $me = User::factory()->create(['role' => UserRole::Public]);

        Inquiry::factory()->create(['submitted_by' => $me->id, 'title' => 'Vaccine rumor']);
        Inquiry::factory()->create(['submitted_by' => $me->id, 'title' => 'Election claim']);

        Volt::actingAs($me)
            ->test('public.inquiries.index')
            ->set('search', 'vaccine')
            ->assertSee('Vaccine rumor')
            ->assertDontSee('Election claim');
    }
}
