<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public function getUnreadCountProperty(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="flex items-center gap-3">
    <a href="{{ route('notifications.index') }}" wire:navigate wire:poll.20s class="relative w-9 h-9 rounded-lg bg-white/15 hover:bg-white/25 flex items-center justify-center flex-shrink-0">
        <x-icon name="bell" class="w-[18px] h-[18px] text-white" />
        @if ($this->unreadCount > 0)
            <span class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-white text-brand border border-brand text-[9px] font-bold flex items-center justify-center">{{ min(9, $this->unreadCount) }}{{ $this->unreadCount > 9 ? '+' : '' }}</span>
        @endif
    </a>

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
                <a href="{{ route('profile') }}" wire:navigate class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">{{ __('Profile Settings') }}</a>
                <button wire:click="logout" class="w-full text-left px-3 py-2 rounded-md text-sm font-medium text-brand hover:bg-brand-light">{{ __('Log Out') }}</button>
            </div>
        </div>
    </div>
</div>
