<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $notifOpen = false;

    public function getUnreadCountProperty(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    public function getRecentNotificationsProperty()
    {
        return Auth::user()->notifications()->latest()->limit(5)->get();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="flex items-center gap-3">
    <div class="relative" x-data="{ open: @entangle('notifOpen') }" @click.outside="open = false">
        <button @click="open = !open" wire:poll.20s class="relative w-9 h-9 rounded-lg bg-white/15 hover:bg-white/25 flex items-center justify-center">
            <x-icon name="bell" class="w-[18px] h-[18px] text-white" />
            @if ($this->unreadCount > 0)
                <span class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-white text-brand border border-brand text-[9px] font-bold flex items-center justify-center">{{ min(9, $this->unreadCount) }}{{ $this->unreadCount > 9 ? '+' : '' }}</span>
            @endif
        </button>

        <div x-show="open" x-cloak class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-100 overflow-hidden z-30">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <span class="text-sm font-bold text-gray-900">{{ __('Notifications') }}</span>
                <button wire:click="markAllRead" class="text-xs font-semibold text-brand hover:underline">{{ __('Mark all as read') }}</button>
            </div>
            <div class="max-h-80 overflow-auto divide-y divide-gray-100">
                @forelse ($this->recentNotifications as $notification)
                    <div class="px-4 py-3 text-sm {{ $notification->read_at ? 'font-medium text-gray-600' : 'font-semibold text-gray-900' }}">
                        {{ $notification->data['message'] ?? 'Notification' }}
                        <div class="text-[11px] text-gray-400 font-normal mt-0.5">{{ $notification->created_at->diffForHumans() }}</div>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-sm text-gray-400">{{ __('No notifications yet.') }}</div>
                @endforelse
            </div>
            <a href="{{ route('agency.notifications.index') }}" wire:navigate class="block text-center py-2.5 text-xs font-semibold text-brand border-t border-gray-100 hover:bg-gray-50">{{ __('View all') }}</a>
        </div>
    </div>

    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
        <button @click="open = !open" class="flex items-center rounded-full hover:ring-2 hover:ring-white/40" aria-label="{{ __('Account menu') }}">
            <x-user-avatar />
        </button>
        <div x-show="open" x-cloak class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-100 overflow-hidden z-30">
            <div class="px-4 py-3 border-b border-gray-100">
                <div class="text-sm font-semibold text-gray-900">{{ auth()->user()->name }}</div>
                <div class="text-xs text-gray-500">{{ auth()->user()->email }}</div>
            </div>
            <div class="p-1.5">
                <a href="{{ route('agency.organization.index') }}" wire:navigate class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">{{ __('Profile Settings') }}</a>
                <button wire:click="logout" class="w-full text-left px-3 py-2 rounded-md text-sm font-medium text-brand hover:bg-brand-light">{{ __('Log Out') }}</button>
            </div>
        </div>
    </div>
</div>
