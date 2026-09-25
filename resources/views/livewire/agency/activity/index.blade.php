<?php

use App\Models\InquiryActivityLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.agency')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function getRowsProperty()
    {
        return InquiryActivityLog::with(['inquiry', 'user'])
            ->whereHas('inquiry', fn ($q) => $q->where('agency_id', Auth::user()->agency_id))
            ->when($this->search, fn ($q) => $q->whereHas('inquiry', fn ($qq) => $qq->where('title', 'like', '%'.$this->search.'%')))
            ->latest()
            ->paginate(15);
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('Activity Log') }}</h1>
    <p class="text-gray-500 text-sm mb-6">{{ __('Every action recorded across your assigned inquiries.') }}</p>

    <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search by inquiry title...') }}"
        class="w-full max-w-md rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand mb-6" />

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden divide-y divide-gray-50">
        @forelse ($this->rows as $log)
            <div class="flex items-center justify-between gap-3 px-5 py-4">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-gray-900 truncate">{{ $log->inquiry?->title }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">{{ $log->user?->name }} &middot; {{ $log->created_at->format('d M Y, H:i') }}</div>
                    @if ($log->notes)
                        <div class="text-xs text-gray-500 mt-1">{{ str($log->notes)->limit(90) }}</div>
                    @endif
                </div>
                <span class="flex-shrink-0 px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">{{ str($log->action)->headline() }}</span>
            </div>
        @empty
            <p class="px-5 py-10 text-center text-gray-400">{{ __('No activity recorded yet.') }}</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $this->rows->links() }}</div>
</div>
