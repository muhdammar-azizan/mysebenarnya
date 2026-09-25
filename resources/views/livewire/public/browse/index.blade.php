<?php

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.public')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'All';

    #[Url]
    public string $category = 'All';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function getRowsProperty()
    {
        return Inquiry::where('status', '!=', InquiryStatus::Discarded)
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->status !== 'All', fn ($q) => $q->where('status', $this->status))
            ->when($this->category !== 'All', fn ($q) => $q->where('category', $this->category))
            ->latest()
            ->paginate(9);
    }

    public function getCategoriesProperty(): array
    {
        return InquiryCategory::cases();
    }

    public function getStatusesProperty(): array
    {
        return array_filter(InquiryStatus::cases(), fn ($case) => $case !== InquiryStatus::Discarded);
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Browse Public Inquiries') }}</h1>
    <p class="text-gray-500 text-sm mt-1 mb-6">{{ __('See what the community has flagged for verification. Submitter details are kept private.') }}</p>

    <div class="flex flex-wrap gap-3 mb-6">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search public inquiries...') }}"
            class="flex-1 min-w-[220px] rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Statuses') }}</option>
            @foreach ($this->statuses as $case)
                <option value="{{ $case->value }}">{{ $case->value }}</option>
            @endforeach
        </select>
        <select wire:model.live="category" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Categories') }}</option>
            @foreach ($this->categories as $case)
                <option value="{{ $case->value }}">{{ $case->value }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-6">
        @forelse ($this->rows as $inquiry)
            <a wire:key="card-{{ $inquiry->id }}" href="{{ route('browse.show', $inquiry) }}" wire:navigate
                class="block bg-white border border-gray-100 rounded-xl p-4 hover:shadow-md transition-shadow">
                <div class="text-[11px] font-bold text-brand mb-2">{{ $inquiry->category?->value }}</div>
                <div class="font-bold text-sm text-gray-900 mb-3 line-clamp-2 min-h-[2.5rem]">{{ $inquiry->title }}</div>
                <div class="flex items-center justify-between gap-2 pt-3 border-t border-gray-50">
                    <span class="text-xs text-gray-400">{{ $inquiry->created_at->format('d M Y') }}</span>
                    <x-inquiry-status-badge :status="$inquiry->status" />
                </div>
            </a>
        @empty
            <div class="col-span-3 bg-white border border-gray-100 rounded-xl p-12 text-center text-gray-400">
                {{ __('No inquiries found. Try adjusting your search or filters.') }}
            </div>
        @endforelse
    </div>

    {{ $this->rows->links() }}
</div>
