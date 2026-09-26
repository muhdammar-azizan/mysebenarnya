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
