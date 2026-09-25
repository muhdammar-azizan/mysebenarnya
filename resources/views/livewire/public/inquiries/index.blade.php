<?php

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Illuminate\Support\Facades\Auth;
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

    public function filterByStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function getStatsProperty(): array
    {
        $base = Inquiry::where('submitted_by', Auth::id());

        return [
            'total' => (clone $base)->count(),
            'investigation' => (clone $base)->where('status', InquiryStatus::UnderInvestigation)->count(),
            'verified' => (clone $base)->where('status', InquiryStatus::VerifiedTrue)->count(),
            'fake' => (clone $base)->where('status', InquiryStatus::IdentifiedFake)->count(),
        ];
    }

    public function getRowsProperty()
    {
        return Inquiry::where('submitted_by', Auth::id())
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->status !== 'All', fn ($q) => $q->where('status', $this->status))
            ->when($this->category !== 'All', fn ($q) => $q->where('category', $this->category))
            ->latest()
            ->paginate(8);
    }

    public function getCategoriesProperty(): array
    {
        return InquiryCategory::cases();
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('My Inquiries') }}</h1>
    <p class="text-gray-500 text-sm mt-1 mb-6">{{ __('Track and manage your submitted inquiries.') }}</p>

    <div class="grid grid-cols-4 gap-4 mb-7">
        <button wire:click="filterByStatus('All')" class="text-left bg-gray-50 border border-gray-100 rounded-xl p-4 hover:shadow-sm {{ $status === 'All' ? 'ring-2 ring-gray-300' : '' }}">
            <div class="text-xs font-semibold text-gray-500 mb-2">{{ __('Total Inquiries') }}</div>
            <div class="text-2xl font-extrabold text-gray-800">{{ $this->stats['total'] }}</div>
        </button>
        <button wire:click="filterByStatus('{{ InquiryStatus::UnderInvestigation->value }}')" class="text-left bg-amber-50 border border-amber-100 rounded-xl p-4 hover:shadow-sm {{ $status === InquiryStatus::UnderInvestigation->value ? 'ring-2 ring-amber-300' : '' }}">
            <div class="text-xs font-semibold text-amber-700 mb-2">{{ __('Under Investigation') }}</div>
            <div class="text-2xl font-extrabold text-amber-700">{{ $this->stats['investigation'] }}</div>
        </button>
        <button wire:click="filterByStatus('{{ InquiryStatus::VerifiedTrue->value }}')" class="text-left bg-green-50 border border-green-100 rounded-xl p-4 hover:shadow-sm {{ $status === InquiryStatus::VerifiedTrue->value ? 'ring-2 ring-green-300' : '' }}">
            <div class="text-xs font-semibold text-green-700 mb-2">{{ __('Verified True') }}</div>
            <div class="text-2xl font-extrabold text-green-700">{{ $this->stats['verified'] }}</div>
        </button>
        <button wire:click="filterByStatus('{{ InquiryStatus::IdentifiedFake->value }}')" class="text-left bg-brand-light border border-red-100 rounded-xl p-4 hover:shadow-sm {{ $status === InquiryStatus::IdentifiedFake->value ? 'ring-2 ring-red-300' : '' }}">
            <div class="text-xs font-semibold text-brand mb-2">{{ __('Identified Fake') }}</div>
            <div class="text-2xl font-extrabold text-brand">{{ $this->stats['fake'] }}</div>
        </button>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3">
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
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">
                    <th class="px-5 py-3">{{ __('Title') }}</th>
                    <th class="px-5 py-3">{{ __('Category') }}</th>
                    <th class="px-5 py-3">{{ __('Status') }}</th>
                    <th class="px-5 py-3">{{ __('Date Submitted') }}</th>
                    <th class="px-5 py-3">{{ __('Assigned Agency') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $inquiry)
                    <tr wire:key="row-{{ $inquiry->id }}" onclick="window.location='{{ route('inquiries.show', $inquiry) }}'" class="border-t border-gray-50 hover:bg-gray-50 cursor-pointer">
                        <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $inquiry->title }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $inquiry->category?->value }}</td>
                        <td class="px-5 py-3.5"><x-inquiry-status-badge :status="$inquiry->status" /></td>
                        <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap">{{ $inquiry->created_at->format('d M Y') }}</td>
                        <td class="px-5 py-3.5 text-gray-600">
                            @if ($inquiry->agency)
                                {{ $inquiry->agency->name }}
                            @else
                                <span class="italic text-gray-400">{{ __('Pending Assignment') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">{{ __("You haven't submitted any inquiries yet.") }}</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="p-4 border-t border-gray-100">
            {{ $this->rows->links() }}
        </div>
    </div>
</div>
