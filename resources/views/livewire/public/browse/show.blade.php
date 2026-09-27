<?php

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.public')] class extends Component
{
    public Inquiry $inquiry;

    public function mount(Inquiry $inquiry): void
    {
        $this->authorize('viewPublicly', $inquiry);

        $this->inquiry = $inquiry->load(['evidence', 'agency']);
    }

    public function getStatusHistoryProperty(): array
    {
        $inquiry = $this->inquiry;
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
}; ?>

<div>
    <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center gap-1.5 text-gray-500 hover:text-brand font-semibold text-sm mb-5">
        &larr; {{ __('Back to Browse') }}
    </a>

    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-5">
        <div class="flex items-center gap-3.5 flex-wrap">
            <h1 class="font-display font-extrabold text-xl text-gray-900">{{ $inquiry->title }}</h1>
            <x-inquiry-status-badge :status="$inquiry->status" />
        </div>
        <div class="flex gap-8 flex-wrap mt-4">
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Category') }}</div>
                <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->category?->value }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Date Submitted') }}</div>
                <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->created_at->format('d M Y') }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Assigned Agency') }}</div>
                <div class="text-sm font-semibold text-gray-700 mt-0.5">
                    @if ($inquiry->agency)
                        {{ $inquiry->agency->name }}
                    @else
                        <span class="italic text-gray-400 font-normal">{{ __('Awaiting Assignment') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-5">
        <div class="font-bold text-gray-900 mb-2">{{ __('Description') }}</div>
        <p class="text-sm text-gray-600 leading-relaxed">{{ $inquiry->description }}</p>
        @if ($inquiry->resolution_notes)
            <div class="mt-5 pt-5 border-t border-gray-100">
                <div class="font-bold text-gray-900 mb-2">{{ __('Resolution Notes') }}</div>
                <p class="text-sm text-gray-600 leading-relaxed">{{ $inquiry->resolution_notes }}</p>
            </div>
        @endif
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-5">
        <div class="font-bold text-gray-900 mb-3">{{ __('Evidence') }}</div>
        <div class="flex flex-wrap gap-2">
            @forelse ($inquiry->evidence as $file)
                <div class="inline-flex items-center gap-2 bg-gray-50 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-700">
                    📎 {{ $file->file_name }}
                </div>
            @empty
                <p class="text-sm text-gray-400">{{ __('No evidence files attached.') }}</p>
            @endforelse
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-6">
        <div class="font-bold text-gray-900 mb-4">{{ __('Status History') }}</div>
        <div class="flex flex-col gap-0">
            @foreach ($this->statusHistory as $i => $step)
                <div class="flex gap-3.5">
                    <div class="flex flex-col items-center">
                        <div class="w-3.5 h-3.5 rounded-full flex-shrink-0 {{ $step['done'] ? 'bg-brand' : 'bg-gray-200' }}"></div>
                        @if ($i < 2)
                            <div class="w-0.5 flex-1 min-h-[32px] {{ $step['done'] ? 'bg-brand' : 'bg-gray-200' }} mt-0.5"></div>
                        @endif
                    </div>
                    <div class="pb-6">
                        <div class="text-sm font-bold {{ $step['done'] ? 'text-gray-900' : 'text-gray-400' }}">{{ $step['label'] }}</div>
                        @if ($step['date'])
                            <div class="text-xs text-gray-400 mt-0.5">{{ $step['date'] }}</div>
                        @endif
                        <div class="text-sm text-gray-500 mt-1">{{ $step['note'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
