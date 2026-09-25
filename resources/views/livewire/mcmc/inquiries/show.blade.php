<?php

use App\Models\Inquiry;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.mcmc')] class extends Component
{
    public Inquiry $inquiry;

    public string $tab = 'details';

    public function mount(Inquiry $inquiry): void
    {
        $this->authorize('view', $inquiry);

        $this->inquiry = $inquiry->load(['submitter', 'evidence', 'activityLogs.user', 'agency', 'reviewer']);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }
}; ?>

<div>
    <a href="{{ route('mcmc.inquiries.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-gray-500 hover:text-brand font-semibold text-sm mb-5">&larr; {{ __('Back to All Inquiries') }}</a>

    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-5">
        <div class="flex items-center gap-3.5 flex-wrap justify-between">
            <div class="flex items-center gap-3.5 flex-wrap">
                <h1 class="font-display font-extrabold text-xl text-gray-900">{{ $inquiry->title }}</h1>
                <x-inquiry-status-badge :status="$inquiry->status" />
                @if ($inquiry->isAwaitingJurisdiction())
                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-500">{{ __('Awaiting Jurisdiction Review') }}</span>
                @endif
                @if ($inquiry->hasOpenClarification())
                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-600">{{ __('Awaiting Clarification') }}</span>
                @endif
            </div>
            <div class="flex gap-2">
                @can('triage', $inquiry)
                    <a href="{{ route('mcmc.triage.index', ['screen' => 'review', 'currentId' => $inquiry->id]) }}" wire:navigate class="text-sm font-bold text-brand hover:underline">{{ __('Review in Triage') }} →</a>
                @endcan
                @can('assign', $inquiry)
                    <a href="{{ route('mcmc.triage.index', ['screen' => 'assign', 'currentId' => $inquiry->id]) }}" wire:navigate class="text-sm font-bold text-brand hover:underline">{{ __('Assign / Reassign') }} →</a>
                @endcan
            </div>
        </div>
        <div class="flex gap-8 flex-wrap mt-4">
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Reference No.') }}</div>
                <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->reference_no }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Submitted By') }}</div>
                <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->submitter?->name }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Category') }}</div>
                <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->category?->value }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Assigned Agency') }}</div>
                <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->agency?->name ?? __('Not yet assigned') }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Reviewed By') }}</div>
                <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->reviewer?->name ?? '—' }}</div>
            </div>
        </div>
    </div>

    <div class="flex border-b border-gray-100 mb-5">
        @foreach (['details' => __('Details'), 'evidence' => __('Evidence'), 'activity' => __('Activity Log')] as $key => $label)
            <button wire:click="setTab('{{ $key }}')" class="px-5 py-3 text-sm font-bold {{ $tab === $key ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'details')
        <div class="bg-white border border-gray-100 rounded-2xl p-6">
            <div class="font-bold text-gray-900 mb-2">{{ __('Description') }}</div>
            <p class="text-sm text-gray-600 leading-relaxed">{{ $inquiry->description }}</p>
            @if ($inquiry->source_url)
                <a href="{{ $inquiry->source_url }}" target="_blank" class="inline-block mt-3 text-sm text-brand hover:underline">{{ $inquiry->source_url }}</a>
            @endif
            @if ($inquiry->resolution_notes)
                <div class="mt-5 pt-5 border-t border-gray-100">
                    <div class="font-bold text-gray-900 mb-2">{{ __('Resolution Notes') }}</div>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $inquiry->resolution_notes }}</p>
                </div>
            @endif
        </div>
    @elseif ($tab === 'evidence')
        <div class="bg-white border border-gray-100 rounded-2xl p-6">
            @forelse ($inquiry->evidence as $file)
                <a href="{{ asset('storage/'.$file->file_path) }}" target="_blank" class="flex items-center gap-2 bg-gray-50 hover:bg-gray-100 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-700 mb-2 w-fit">📎 {{ $file->file_name }}</a>
            @empty
                <p class="text-sm text-gray-400">{{ __('No evidence files attached.') }}</p>
            @endforelse
        </div>
    @else
        <div class="bg-white border border-gray-100 rounded-2xl p-6">
            <div class="flex flex-col gap-5">
                @foreach ($inquiry->activityLogs->sortByDesc('created_at') as $log)
                    <div class="flex gap-3">
                        <div class="w-2 h-2 rounded-full bg-brand mt-1.5 flex-shrink-0"></div>
                        <div>
                            <div class="text-[11px] font-bold text-gray-400">{{ $log->created_at->format('d M Y, H:i') }} &middot; {{ $log->user?->name }}</div>
                            <div class="text-sm font-bold text-gray-900">{{ str($log->action)->headline() }}</div>
                            @if ($log->notes)
                                <div class="text-sm text-gray-600 mt-0.5">{{ $log->notes }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
