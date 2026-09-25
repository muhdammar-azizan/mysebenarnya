<?php

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
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

    #[Url]
    public string $status = 'All';

    #[Url]
    public string $category = 'All';

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
        $agencyId = Auth::user()->agency_id;

        return Inquiry::where('agency_id', $agencyId)
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->status === 'awaiting', fn ($q) => $q->where('status', InquiryStatus::UnderInvestigation)->whereNull('jurisdiction_accepted_at'))
            ->when($this->status !== 'All' && $this->status !== 'awaiting', fn ($q) => $q->where('status', $this->status))
            ->when($this->category !== 'All', fn ($q) => $q->where('category', $this->category))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate(10);
    }

    public function getCategoriesProperty(): array
    {
        return InquiryCategory::cases();
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('Assigned Inquiries') }}</h1>
    <p class="text-gray-500 text-sm mb-6">{{ __('Review jurisdiction, investigate, and record your verdict.') }}</p>

    <div class="flex flex-wrap gap-3 mb-6">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search by title...') }}"
            class="flex-1 min-w-[200px] rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Assigned') }}</option>
            <option value="awaiting">{{ __('Awaiting Jurisdiction Review') }}</option>
            <option value="{{ InquiryStatus::UnderInvestigation->value }}">{{ __('Under Investigation') }}</option>
            <option value="{{ InquiryStatus::VerifiedTrue->value }}">{{ __('Verified True') }}</option>
            <option value="{{ InquiryStatus::IdentifiedFake->value }}">{{ __('Identified Fake') }}</option>
            <option value="{{ InquiryStatus::Rejected->value }}">{{ __('Rejected by us') }}</option>
        </select>
        <select wire:model.live="category" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Categories') }}</option>
            @foreach ($this->categories as $case)
                <option value="{{ $case->value }}">{{ $case->value }}</option>
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
                    <th class="px-5 py-3">{{ __('Category') }}</th>
                    <th class="px-5 py-3">{{ __('Status') }}</th>
                    <th class="px-5 py-3">{{ __('Date Assigned') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $inquiry)
                    <tr wire:key="row-{{ $inquiry->id }}" onclick="window.location='{{ route('agency.inquiries.show', $inquiry) }}'" class="border-t border-gray-50 hover:bg-gray-50 cursor-pointer">
                        <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $inquiry->title }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $inquiry->category?->value }}</td>
                        <td class="px-5 py-3.5">
                            <x-inquiry-status-badge :status="$inquiry->status" />
                            @if ($inquiry->isAwaitingJurisdiction())
                                <span class="ml-1 inline-block px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-500">{{ __('Awaiting Review') }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap">{{ $inquiry->updated_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-gray-400">{{ __('No assigned inquiries found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t border-gray-100">{{ $this->rows->links() }}</div>
    </div>
</div>
