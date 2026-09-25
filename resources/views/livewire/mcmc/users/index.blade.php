<?php

use App\Enums\UserRole;
use App\Models\InquiryActivityLog;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.mcmc')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'All';

    public ?int $panelUserId = null;

    public string $panelTab = 'profile';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function openPanel(int $id): void
    {
        $this->panelUserId = $id;
        $this->panelTab = 'profile';
    }

    public function closePanel(): void
    {
        $this->panelUserId = null;
    }

    public function getRowsProperty()
    {
        return User::where('role', UserRole::Public)
            ->withCount('submittedInquiries')
            ->when($this->search, fn ($q) => $q->where(fn ($qq) => $qq->where('name', 'like', '%'.$this->search.'%')->orWhere('email', 'like', '%'.$this->search.'%')))
            ->when($this->status === 'Verified', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($this->status === 'Unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->latest()
            ->paginate(10);
    }

    public function getPanelUserProperty(): ?User
    {
        return $this->panelUserId ? User::withCount('submittedInquiries')->find($this->panelUserId) : null;
    }

    public function getPanelActivityProperty()
    {
        if (! $this->panelUserId) {
            return collect();
        }

        return InquiryActivityLog::with('inquiry')
            ->where('user_id', $this->panelUserId)
            ->latest()
            ->limit(10)
            ->get();
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('Registered Users') }}</h1>
    <p class="text-gray-500 text-sm mb-6">{{ __('Public users, their profiles, and submission activity.') }}</p>

    <div class="flex flex-wrap gap-3 mb-6">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search by name or email...') }}"
            class="flex-1 min-w-[220px] rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All') }}</option>
            <option value="Verified">{{ __('Verified') }}</option>
            <option value="Unverified">{{ __('Unverified') }}</option>
        </select>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">
                    <th class="px-5 py-3">{{ __('Name') }}</th>
                    <th class="px-5 py-3">{{ __('Email') }}</th>
                    <th class="px-5 py-3">{{ __('Registered') }}</th>
                    <th class="px-5 py-3">{{ __('Status') }}</th>
                    <th class="px-5 py-3">{{ __('Inquiries') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $user)
                    <tr wire:key="user-{{ $user->id }}" wire:click="openPanel({{ $user->id }})" class="border-t border-gray-50 hover:bg-gray-50 cursor-pointer">
                        <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $user->name }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $user->email }}</td>
                        <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="px-5 py-3.5">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $user->email_verified_at ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $user->email_verified_at ? __('Verified') : __('Unverified') }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $user->submitted_inquiries_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">{{ __('No registered users found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t border-gray-100">{{ $this->rows->links() }}</div>
    </div>

    @if ($this->panelUser)
        <div wire:click="closePanel" class="fixed inset-0 bg-black/35 z-40"></div>
        <div class="fixed top-0 right-0 bottom-0 w-96 bg-white shadow-2xl z-50 flex flex-col">
            <div class="p-5 border-b border-gray-100 flex items-start justify-between gap-3">
                <div class="font-extrabold text-gray-900">{{ $this->panelUser->name }}</div>
                <button wire:click="closePanel" class="text-gray-400 hover:text-brand text-lg leading-none">✕</button>
            </div>
            <div class="flex border-b border-gray-100">
                <button wire:click="$set('panelTab', 'profile')" class="flex-1 text-center py-2.5 text-xs font-bold {{ $panelTab === 'profile' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Profile') }}</button>
                <button wire:click="$set('panelTab', 'activity')" class="flex-1 text-center py-2.5 text-xs font-bold {{ $panelTab === 'activity' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Activity Log') }}</button>
            </div>
            <div class="flex-1 overflow-auto p-5">
                @if ($panelTab === 'profile')
                    <div class="flex flex-col gap-4 text-sm">
                        <div><div class="text-[11px] font-bold text-gray-400 uppercase">{{ __('Email') }}</div><div class="font-semibold text-gray-800">{{ $this->panelUser->email }}</div></div>
                        <div><div class="text-[11px] font-bold text-gray-400 uppercase">{{ __('Phone') }}</div><div class="font-semibold text-gray-800">{{ $this->panelUser->phone ?? '—' }}</div></div>
                        <div><div class="text-[11px] font-bold text-gray-400 uppercase">{{ __('Registered') }}</div><div class="font-semibold text-gray-800">{{ $this->panelUser->created_at->format('d M Y') }}</div></div>
                        <div><div class="text-[11px] font-bold text-gray-400 uppercase">{{ __('Last Active') }}</div><div class="font-semibold text-gray-800">{{ $this->panelUser->last_active_at?->diffForHumans() ?? __('Never') }}</div></div>
                        <div><div class="text-[11px] font-bold text-gray-400 uppercase">{{ __('Total Inquiries') }}</div><div class="font-semibold text-gray-800">{{ $this->panelUser->submitted_inquiries_count }}</div></div>
                    </div>
                @else
                    <div class="flex flex-col gap-4">
                        @forelse ($this->panelActivity as $log)
                            <div class="flex gap-3">
                                <div class="w-2 h-2 rounded-full bg-brand mt-1.5 flex-shrink-0"></div>
                                <div>
                                    <div class="text-[11px] font-bold text-gray-400">{{ $log->created_at->format('d M Y') }}</div>
                                    <div class="text-sm font-semibold text-gray-900">{{ str($log->action)->headline() }}: {{ $log->inquiry?->title }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">{{ __('No activity yet.') }}</p>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
