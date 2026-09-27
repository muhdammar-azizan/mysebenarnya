<?php

namespace Tests\Feature\Public;

use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\User;
use App\Notifications\InquiryStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_their_own_notifications(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $user->id]);

        $user->notify(new InquiryStatusChanged($inquiry, 'Submitted', 'Verified True'));

        Volt::actingAs($user)
            ->test('public.notifications.index')
            ->assertSee('Verified as True');
    }

    public function test_mark_all_read_clears_unread_count(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $user->id]);

        $user->notify(new InquiryStatusChanged($inquiry, 'Submitted', 'Verified True'));
        $user->notify(new InquiryStatusChanged($inquiry, 'Submitted', 'Identified Fake'));

        $this->assertSame(2, $user->fresh()->unreadNotifications()->count());

        Volt::actingAs($user)
            ->test('public.notifications.index')
            ->call('markAllRead');

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_unread_filter_hides_read_notifications(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $user->id]);

        $user->notify(new InquiryStatusChanged($inquiry, 'Submitted', 'Verified True'));
        $user->unreadNotifications->first()->markAsRead();

        Volt::actingAs($user)
            ->test('public.notifications.index')
            ->set('filter', 'unread')
            ->assertSee('No notifications in this filter');
    }

    public function test_mentions_filter_only_shows_actionable_statuses(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $user->id]);

        $user->notify(new InquiryStatusChanged($inquiry, 'Submitted', 'Verified True'));
        $user->notify(new InquiryStatusChanged($inquiry, 'Submitted', 'Discarded'));

        $component = Volt::actingAs($user)
            ->test('public.notifications.index')
            ->set('filter', 'action');

        $this->assertCount(1, $component->get('rows'));
    }

    public function test_topbar_bell_links_directly_to_the_notifications_page_without_a_preview_dropdown(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        Volt::actingAs($user)
            ->test('public.topbar-widgets')
            ->assertSeeHtml(route('notifications.index'))
            ->assertDontSee('Mark all as read');
    }
}
