<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.agency')] class extends Component
{
    use WithPagination;

    public string $filter = 'all';

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function markRead(string $id): void
    {
        Auth::user()->notifications()->where('id', $id)->first()?->markAsRead();
    }

    public function getUnreadCountProperty(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    public function getRowsProperty()
    {
        return Auth::user()->notifications()
            ->when($this->filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(10);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Notifications') }}</h1>
        <button wire:click="markAllRead" class="text-sm font-semibold text-brand hover:underline">{{ __('Mark all as read') }}</button>
    </div>
    <p class="text-gray-500 text-sm mt-1 mb-5">{{ __('Stay updated on assigned inquiries and system events.') }}</p>

    <div class="flex gap-2 mb-5">
        <button wire:click="$set('filter', 'all')" class="px-3.5 py-1.5 rounded-full text-xs font-bold {{ $filter === 'all' ? 'bg-brand-light text-brand' : 'bg-gray-100 text-gray-500' }}">{{ __('All') }}</button>
        <button wire:click="$set('filter', 'unread')" class="px-3.5 py-1.5 rounded-full text-xs font-bold flex items-center gap-1.5 {{ $filter === 'unread' ? 'bg-brand-light text-brand' : 'bg-gray-100 text-gray-500' }}">
            {{ __('Unread') }}
            @if ($this->unreadCount > 0)
                <span class="bg-brand text-white rounded-full px-1.5 text-[10px]">{{ $this->unreadCount }}</span>
            @endif
        </button>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        @forelse ($this->rows as $notification)
            <div wire:key="notif-{{ $notification->id }}" wire:click="markRead('{{ $notification->id }}')"
                class="flex items-start gap-3 px-5 py-4 border-b border-gray-50 last:border-0 cursor-pointer hover:bg-gray-50">
                <div class="w-7 h-7 rounded-full bg-gray-100 flex-shrink-0 flex items-center justify-center text-sm">🔔</div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm {{ $notification->read_at ? 'font-medium text-gray-600' : 'font-bold text-gray-900' }}">
                        {{ $notification->data['message'] ?? 'Notification' }}
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">{{ $notification->created_at->diffForHumans() }}</div>
                </div>
                @unless ($notification->read_at)
                    <div class="w-2 h-2 rounded-full bg-brand mt-1.5 flex-shrink-0"></div>
                @endunless
            </div>
        @empty
            <div class="px-5 py-14 text-center text-gray-400 text-sm">{{ __('No notifications in this filter.') }}</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $this->rows->links() }}</div>
</div>
