<?php

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.public')] class extends Component
{
    public function getStatsProperty(): array
    {
        return [
            'total' => Inquiry::count(),
            'verified' => Inquiry::where('status', InquiryStatus::VerifiedTrue)->count(),
            'fake' => Inquiry::where('status', InquiryStatus::IdentifiedFake)->count(),
        ];
    }

    public function getTeaserInquiriesProperty()
    {
        return Inquiry::where('status', '!=', InquiryStatus::Discarded)
            ->latest()
            ->limit(6)
            ->get();
    }
}; ?>

<div
    x-data="{ slide: 0, slides: 3, timer: null, start() { clearInterval(this.timer); this.timer = setInterval(() => this.slide = (this.slide + 1) % this.slides, 5000); } }"
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
            <a href="{{ route('browse.index') }}?status={{ InquiryStatus::VerifiedTrue->value }}" wire:navigate class="w-fit bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('See Verified Answers') }}</a>
        </div>
        <div x-show="slide === 2" x-transition.opacity.duration.500ms class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/40 to-transparent flex flex-col justify-center px-9">
            <span class="inline-block w-fit bg-white/15 text-white text-xs font-bold tracking-wide px-2.5 py-1 rounded-full mb-3">{{ __('STAY INFORMED') }}</span>
            <h1 class="font-display font-extrabold text-2xl text-white mb-2 max-w-md">{{ __('Browse What Others Have Flagged') }}</h1>
            <p class="text-white/85 text-sm max-w-md mb-4">{{ __('Explore inquiries submitted by the community and see what has been verified so far.') }}</p>
            <a href="{{ route('browse.index') }}" wire:navigate class="w-fit bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Browse Below') }}</a>
        </div>

        <div class="absolute bottom-4 left-0 right-0 flex justify-center gap-1.5">
            <template x-for="i in 3">
                <button @click="slide = i - 1; start()" :class="slide === i - 1 ? 'w-5 bg-brand' : 'w-2 bg-white/40'" class="h-2 rounded-full transition-all"></button>
            </template>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-5 mb-8">
        <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('Total Public Inquiries') }}</div>
            <div class="text-3xl font-extrabold text-gray-900">{{ $this->stats['total'] }}</div>
        </div>
        <div class="bg-green-50 border border-green-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-green-700 mb-1">{{ __('Verified True') }}</div>
            <div class="text-3xl font-extrabold text-green-700">{{ $this->stats['verified'] }}</div>
        </div>
        <div class="bg-brand-light border border-red-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-brand mb-1">{{ __('Identified Fake') }}</div>
            <div class="text-3xl font-extrabold text-brand">{{ $this->stats['fake'] }}</div>
        </div>
    </div>

    <div class="flex items-center justify-between mb-4">
        <h2 class="font-display font-extrabold text-xl text-gray-900">{{ __('Recently Submitted') }}</h2>
        <a href="{{ route('browse.index') }}" wire:navigate class="text-sm font-semibold text-brand hover:underline">{{ __('Browse all →') }}</a>
    </div>

    <div class="grid grid-cols-3 gap-4">
        @foreach ($this->teaserInquiries as $inquiry)
            <a href="{{ route('browse.show', $inquiry) }}" wire:navigate class="block bg-white border border-gray-100 rounded-xl p-4 hover:shadow-md transition-shadow">
                <div class="text-[11px] font-bold text-brand mb-2">{{ $inquiry->category?->value }}</div>
                <div class="font-bold text-sm text-gray-900 mb-3 line-clamp-2">{{ $inquiry->title }}</div>
                <div class="flex items-center justify-between text-xs text-gray-400 pt-2 border-t border-gray-50">
                    <span>{{ $inquiry->created_at->format('d M Y') }}</span>
                    <x-inquiry-status-badge :status="$inquiry->status" />
                </div>
            </a>
        @endforeach
    </div>
</div>
