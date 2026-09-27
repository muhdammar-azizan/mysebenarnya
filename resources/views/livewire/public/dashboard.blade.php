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

    public ?int $panelInquiryId = null;

    public string $panelTab = 'details';

    public function updating(): void
    {
        $this->resetPage();
    }

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
            ? Inquiry::with(['evidence', 'agency'])->find($this->panelInquiryId)
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

    public function setStatusFilter(string $status): void
    {
        $this->status = $status;
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

<div
    x-data="{ slide: 0, slides: 3, timer: null, start() { clearInterval(this.timer); this.timer = setInterval(() => this.slide = (this.slide + 1) % this.slides, 5000); }, jump() { this.$nextTick(() => document.getElementById('public-feed-anchor')?.scrollIntoView({ behavior: 'smooth' })); } }"
    x-init="start()"
>
    <div class="relative rounded-2xl overflow-hidden h-64 mb-8 bg-gray-900">
        <div x-show="slide === 0" x-transition.opacity.duration.500ms class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/40 to-transparent flex flex-col justify-center px-9">
            <span class="inline-block w-fit bg-white/15 text-white text-xs font-bold tracking-wide px-2.5 py-1 rounded-full mb-3">{{ __('REPORT MISINFORMATION') }}</span>
            <h1 class="font-display font-extrabold text-2xl text-white mb-2 max-w-md">{{ __('See Something Suspicious? Report It.') }}</h1>
            <p class="text-white/85 text-sm max-w-md mb-4">{{ __("Help stop fake news before it spreads. Submit anything you're unsure about for official verification.") }}</p>
            <a href="{{ route('inquiries.create') }}" wire:navigate class="w-fit bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Submit an Inquiry') }}</a>
        </div>
        <div x-show="slide === 1" x-transition.opacity.duration.500ms class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/40 to-transparent flex flex-col justify-center px-9">
            <span class="inline-block w-fit bg-white/15 text-white text-xs font-bold tracking-wide px-2.5 py-1 rounded-full mb-3">{{ __('VERIFIED BY OFFICIAL AGENCIES') }}</span>
            <h1 class="font-display font-extrabold text-2xl text-white mb-2 max-w-md">{{ __('Real Answers, Checked by the Right Authority') }}</h1>
            <p class="text-white/85 text-sm max-w-md mb-4">{{ __('Every inquiry is reviewed by the relevant government ministry or agency — not guesswork.') }}</p>
            <button type="button" @click="setStatusFilter('{{ InquiryStatus::VerifiedTrue->value }}'); jump()" class="w-fit bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('See Verified Answers') }}</button>
        </div>
        <div x-show="slide === 2" x-transition.opacity.duration.500ms class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/40 to-transparent flex flex-col justify-center px-9">
            <span class="inline-block w-fit bg-white/15 text-white text-xs font-bold tracking-wide px-2.5 py-1 rounded-full mb-3">{{ __('STAY INFORMED') }}</span>
            <h1 class="font-display font-extrabold text-2xl text-white mb-2 max-w-md">{{ __('Browse What Others Have Flagged') }}</h1>
            <p class="text-white/85 text-sm max-w-md mb-4">{{ __('Explore inquiries submitted by the community and see what has been verified so far.') }}</p>
            <button type="button" @click="jump()" class="w-fit bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Browse Below') }}</button>
        </div>

        <div class="absolute bottom-4 left-0 right-0 flex justify-center gap-1.5">
            <template x-for="i in 3">
                <button @click="slide = i - 1; start()" :class="slide === i - 1 ? 'w-5 bg-brand' : 'w-2 bg-white/40'" class="h-2 rounded-full transition-all"></button>
            </template>
        </div>
    </div>

    <h1 id="public-feed-anchor" class="font-display font-extrabold text-2xl text-gray-900 scroll-mt-4">{{ __('Public Inquiries') }}</h1>
    <p class="text-gray-500 text-sm mt-1.5 mb-5">{{ __('See what the community has flagged for verification. Submitter details are kept private.') }}</p>

    <div class="flex flex-wrap gap-3 mb-6">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search public inquiries...') }}"
            class="flex-1 min-w-[220px] rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Statuses') }}</option>
            @foreach ($this->statuses as $case)
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

    <div class="grid grid-cols-3 gap-4 mb-6">
        @forelse ($this->rows as $inquiry)
            <button type="button" wire:key="card-{{ $inquiry->id }}" wire:click="viewPanel({{ $inquiry->id }})"
                class="block text-left bg-white border border-gray-100 rounded-xl p-4 hover:shadow-md transition-shadow">
                <div class="text-[11px] font-bold text-brand mb-2">{{ $inquiry->category?->value }}</div>
                <div class="font-bold text-sm text-gray-900 mb-3 line-clamp-2 min-h-[2.5rem]">{{ $inquiry->title }}</div>
                <div class="flex items-center justify-between gap-2 pt-3 border-t border-gray-50">
                    <span class="text-xs text-gray-400">{{ $inquiry->created_at->format('d M Y') }}</span>
                    <x-inquiry-status-badge :status="$inquiry->status" />
                </div>
            </button>
        @empty
            <div class="col-span-3 bg-white border border-gray-100 rounded-xl p-12 text-center text-gray-400">
                {{ __('No inquiries found. Try adjusting your search or filters.') }}
            </div>
        @endforelse
    </div>

    {{ $this->rows->links() }}

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
        </div>
    @endif
</div>
