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
            <svg class="w-4.5 h-4.5 text-white" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9a6 6 0 0112 0c0 4 1.5 5 1.5 5h-15S6 13 6 9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
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
        <button @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-white/10">
            <div class="w-8 h-8 rounded-full bg-brand-light text-brand font-bold text-xs flex items-center justify-center flex-shrink-0">
                {{ collect(explode(' ', auth()->user()->name))->map(fn ($w) => $w[0] ?? '')->take(2)->implode('') }}
            </div>
            <div class="text-left hidden sm:block">
                <div class="text-xs font-semibold text-white leading-tight">{{ auth()->user()->name }}</div>
                <div class="text-[11px] text-white/70 leading-tight">{{ auth()->user()->agency?->name ?? auth()->user()->role->label() }}</div>
            </div>
        </button>
        <div x-show="open" x-cloak class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-100 overflow-hidden z-30">
            <div class="px-4 py-3 border-b border-gray-100">
                <div class="text-sm font-semibold text-gray-900">{{ auth()->user()->name }}</div>
                <div class="text-xs text-gray-500">{{ auth()->user()->email }}</div>
            </div>
            <div class="p-1.5">
                <a href="{{ route('profile') }}" wire:navigate class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">{{ __('Profile Settings') }}</a>
                <button wire:click="logout" class="w-full text-left px-3 py-2 rounded-md text-sm font-medium text-brand hover:bg-brand-light">{{ __('Log Out') }}</button>
            </div>
        </div>
    </div>
</div>
