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
}
