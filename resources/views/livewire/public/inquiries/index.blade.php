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

    public ?int $panelInquiryId = null;

    public string $panelTab = 'details';

    public function viewPanel(int $inquiryId): void
    {
        $this->panelInquiryId = $inquiryId;
        $this->panelTab = 'details';
    }

    public function closePanel(): void
    {
        $this->panelInquiryId = null;
    }

    public function getPanelInquiryProperty(): ?Inquiry
    {
        return $this->panelInquiryId
            ? Inquiry::with(['evidence', 'agency'])->where('submitted_by', Auth::id())->find($this->panelInquiryId)
            : null;
    }

    public function statusHistoryFor(Inquiry $inquiry): array
    {
        $assigned = $inquiry->agency_id !== null && $inquiry->reviewed_at !== null;

        $terminal = in_array($inquiry->status, [
            InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake, InquiryStatus::Rejected, InquiryStatus::Discarded,
        ], true);

        $outcomeNote = match ($inquiry->status) {
            InquiryStatus::VerifiedTrue => __('Confirmed accurate').($inquiry->resolution_notes ? ' — '.$inquiry->resolution_notes : ''),
            InquiryStatus::IdentifiedFake => __('Identified as false or misleading').($inquiry->resolution_notes ? ' — '.$inquiry->resolution_notes : ''),
            InquiryStatus::Rejected => __('Rejected').($inquiry->resolution_notes ? ' — '.$inquiry->resolution_notes : ''),
            InquiryStatus::Discarded => __('Discarded as non-serious'),
            default => __('Pending investigation result'),
        };

        return [
            ['label' => __('Submitted'), 'date' => $inquiry->created_at->format('d M Y'), 'note' => __('Inquiry received'), 'done' => true],
            ['label' => __('Under Investigation'), 'date' => $assigned ? $inquiry->reviewed_at->format('d M Y') : '', 'note' => $assigned ? __('Assigned to :agency for review', ['agency' => $inquiry->agency?->name]) : __('Awaiting review assignment'), 'done' => $assigned],
            ['label' => __('Outcome'), 'date' => $terminal ? ($inquiry->resolved_at?->format('d M Y') ?? $inquiry->updated_at->format('d M Y')) : '', 'note' => $outcomeNote, 'done' => $terminal],
        ];
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
            <div class="text-xs font-semibold text-green-700 mb-2">{{ __('Verified as True') }}</div>
            <div class="text-2xl font-extrabold text-green-700">{{ $this->stats['verified'] }}</div>
        </button>
        <button wire:click="filterByStatus('{{ InquiryStatus::IdentifiedFake->value }}')" class="text-left bg-brand-light border border-red-100 rounded-xl p-4 hover:shadow-sm {{ $status === InquiryStatus::IdentifiedFake->value ? 'ring-2 ring-red-300' : '' }}">
            <div class="text-xs font-semibold text-brand mb-2">{{ __('Identified as Fake') }}</div>
            <div class="text-2xl font-extrabold text-brand">{{ $this->stats['fake'] }}</div>
        </button>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-7 flex items-center justify-between flex-wrap gap-4">
        <div>
            <div class="font-bold text-gray-900">{{ __('Ready to verify something new?') }}</div>
            <div class="text-gray-500 text-sm mt-1">{{ __("Submit a news item you'd like us to investigate.") }}</div>
        </div>
        <a href="{{ route('inquiries.create') }}" wire:navigate class="inline-flex items-center gap-2 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg flex-shrink-0">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>
            {{ __('Submit a New Inquiry') }}
        </a>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3">
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search by title...') }}"
                class="flex-1 min-w-[200px] rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
            <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                <option value="All">{{ __('All Statuses') }}</option>
                @foreach (InquiryStatus::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
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
                    <tr wire:key="row-{{ $inquiry->id }}" wire:click="viewPanel({{ $inquiry->id }})" class="border-t border-gray-50 hover:bg-gray-50 cursor-pointer">
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

    @if ($this->panelInquiry)
        <div wire:click="closePanel" class="fixed inset-0 bg-black/35 z-40"></div>
        <div class="fixed top-0 right-0 bottom-0 w-96 bg-white shadow-2xl z-50 flex flex-col">
            <div class="p-5 border-b border-gray-100 flex items-start justify-between gap-3">
                <div class="font-display font-bold text-base text-gray-900">{{ $this->panelInquiry->title }}</div>
                <button wire:click="closePanel" class="text-gray-400 hover:text-brand text-lg leading-none flex-shrink-0">✕</button>
            </div>
            <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2 flex-wrap">
                <x-inquiry-status-badge :status="$this->panelInquiry->status" />
                <span class="text-xs text-gray-400">{{ $this->panelInquiry->category?->value }} &middot; {{ $this->panelInquiry->created_at->format('d M Y') }}</span>
            </div>
            <div class="px-5 py-3 border-b border-gray-100">
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide mb-1.5">{{ __('Assigned Agency') }}</div>
                @if ($this->panelInquiry->agency)
                    <span class="text-sm font-semibold text-gray-700">{{ $this->panelInquiry->agency->name }}</span>
                @else
                    <span class="text-xs font-bold text-gray-500 bg-gray-100 px-2.5 py-1 rounded-full">{{ __('Awaiting Assignment') }}</span>
                @endif
            </div>
            <div class="flex border-b border-gray-100 flex-shrink-0">
                <button wire:click="$set('panelTab', 'details')" class="flex-1 text-center py-2.5 text-xs font-bold {{ $panelTab === 'details' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Details') }}</button>
                <button wire:click="$set('panelTab', 'evidence')" class="flex-1 text-center py-2.5 text-xs font-bold {{ $panelTab === 'evidence' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Evidence') }}</button>
                <button wire:click="$set('panelTab', 'activity')" class="flex-1 text-center py-2.5 text-xs font-bold {{ $panelTab === 'activity' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Activity') }}</button>
            </div>
            <div class="flex-1 overflow-auto p-5">
                @if ($panelTab === 'details')
                    <div class="text-sm text-gray-600 leading-relaxed">{{ $this->panelInquiry->description }}</div>
                @elseif ($panelTab === 'evidence')
                    <div class="flex flex-col gap-2">
                        @forelse ($this->panelInquiry->evidence as $file)
                            <div class="flex items-center gap-2.5 bg-gray-50 border border-gray-100 rounded-lg px-3.5 py-2.5 text-xs font-semibold text-gray-700">📎 {{ $file->file_name }}</div>
                        @empty
                            <p class="text-sm text-gray-400">{{ __('No evidence files attached.') }}</p>
                        @endforelse
                    </div>
                @else
                    <div class="flex flex-col gap-0">
                        @foreach ($this->statusHistoryFor($this->panelInquiry) as $i => $step)
                            <div class="flex gap-3.5">
                                <div class="flex flex-col items-center">
                                    <div class="w-3.5 h-3.5 rounded-full flex-shrink-0 {{ $step['done'] ? 'bg-brand' : 'bg-gray-200' }}"></div>
                                    @if ($i < 2)
                                        <div class="w-0.5 flex-1 min-h-[32px] {{ $step['done'] ? 'bg-brand' : 'bg-gray-200' }} mt-0.5"></div>
                                    @endif
                                </div>
                                <div class="pb-5">
                                    <div class="text-sm font-bold {{ $step['done'] ? 'text-gray-900' : 'text-gray-400' }}">{{ $step['label'] }}</div>
                                    @if ($step['date'])
                                        <div class="text-xs text-gray-400 mt-0.5">{{ $step['date'] }}</div>
                                    @endif
                                    <div class="text-xs text-gray-500 mt-1">{{ $step['note'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="p-4 border-t border-gray-100">
                <a href="{{ route('inquiries.show', $this->panelInquiry) }}" wire:navigate class="block w-full text-center bg-brand hover:bg-brand-dark text-white font-bold text-sm py-2.5 rounded-lg">{{ __('View Full Details') }}</a>
            </div>
        </div>
    @endif
</div>
