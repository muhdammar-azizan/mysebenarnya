<?php

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\Inquiry;
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

    #[Url]
    public string $category = 'All';

    #[Url]
    public string $agency = 'All';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function getRowsProperty()
    {
        return Inquiry::with(['submitter', 'agency'])
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->status !== 'All', fn ($q) => $q->where('status', $this->status))
            ->when($this->category !== 'All', fn ($q) => $q->where('category', $this->category))
            ->when($this->agency !== 'All', fn ($q) => $q->where('agency_id', $this->agency))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate(12);
    }

    public function getCategoriesProperty(): array
    {
        return InquiryCategory::cases();
    }

    public function getAgenciesProperty()
    {
        return Agency::orderBy('name')->get();
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('All Inquiries') }}</h1>
    <p class="text-gray-500 text-sm mb-6">{{ __('Full registry across all statuses and agencies.') }}</p>

    <div class="flex flex-wrap gap-3 mb-6">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search by title...') }}"
            class="flex-1 min-w-[200px] rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Statuses') }}</option>
            @foreach (InquiryStatus::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->value }}</option>
            @endforeach
        </select>
        <select wire:model.live="category" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Categories') }}</option>
            @foreach ($this->categories as $case)
                <option value="{{ $case->value }}">{{ $case->value }}</option>
            @endforeach
        </select>
        <select wire:model.live="agency" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Agencies') }}</option>
            @foreach ($this->agencies as $a)
                <option value="{{ $a->id }}">{{ $a->name }}</option>
            @endforeach
        </select>
        <div class="flex items-center gap-2">
            <label class="text-xs text-gray-500">{{ __('From') }}</label>
            <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
            <label class="text-xs text-gray-500">{{ __('To') }}</label>
            <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">
                    <th class="px-5 py-3">{{ __('Title') }}</th>
                    <th class="px-5 py-3">{{ __('Submitted By') }}</th>
                    <th class="px-5 py-3">{{ __('Category') }}</th>
                    <th class="px-5 py-3">{{ __('Status') }}</th>
                    <th class="px-5 py-3">{{ __('Agency') }}</th>
                    <th class="px-5 py-3">{{ __('Date') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $inquiry)
                    <tr wire:key="row-{{ $inquiry->id }}" onclick="window.location='{{ route('mcmc.inquiries.show', $inquiry) }}'" class="border-t border-gray-50 hover:bg-gray-50 cursor-pointer">
                        <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $inquiry->title }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $inquiry->submitter?->name }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $inquiry->category?->value }}</td>
                        <td class="px-5 py-3.5"><x-inquiry-status-badge :status="$inquiry->status" /></td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $inquiry->agency?->name ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap">{{ $inquiry->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">{{ __('No inquiries found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t border-gray-100">{{ $this->rows->links() }}</div>
    </div>
</div>
