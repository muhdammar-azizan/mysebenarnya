<?php

use App\Enums\ClarificationStatus;
use App\Models\ClarificationThread;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.mcmc')] class extends Component
{
    #[Url]
    public string $filter = 'active';

    public function getRowsProperty()
    {
        return ClarificationThread::with(['inquiry.agency', 'messages'])
            ->when($this->filter === 'active', fn ($q) => $q->where('status', '!=', ClarificationStatus::Closed))
            ->when($this->filter !== 'active' && $this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
            ->orderByDesc('updated_at')
            ->get();
    }

    public function getOpenCountProperty(): int
    {
        return ClarificationThread::where('status', ClarificationStatus::Open)->count();
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('Clarification Requests') }}</h1>
    <p class="text-gray-500 text-sm mb-6">{{ __('Questions from agencies about their assigned cases.') }}</p>

    <div class="flex gap-2 mb-6">
        @foreach ([
            'active' => __('Active'),
            'open' => __('Awaiting Response') . ' (' . $this->openCount . ')',
            'answered' => __('Responded'),
            'closed' => __('Resolved'),
            'all' => __('All'),
        ] as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')" class="px-3.5 py-1.5 rounded-full text-xs font-bold {{ $filter === $key ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-500' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden divide-y divide-gray-50">
        @forelse ($this->rows as $thread)
            <a href="{{ route('mcmc.clarifications.show', $thread) }}" wire:navigate class="flex items-center justify-between gap-3 px-5 py-4 hover:bg-gray-50">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-900 truncate">{{ $thread->inquiry->title }}</span>
                        @if ($thread->priority?->value === 'Urgent' && $thread->isActive())
                            <span class="text-[11px] font-bold text-brand">{{ __('URGENT') }}</span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">{{ $thread->inquiry->agency?->name }} &middot; {{ $thread->topic?->value }} &middot; {{ $thread->updated_at->diffForHumans() }}</div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    @if ($thread->unread_by_mcmc)
                        <span class="w-2 h-2 rounded-full bg-brand"></span>
                    @endif
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ match($thread->status->value) { 'open' => 'bg-purple-100 text-purple-600', 'answered' => 'bg-blue-100 text-blue-600', default => 'bg-green-100 text-green-700' } }}">
                        {{ $thread->status->label() }}
                    </span>
                </div>
            </a>
        @empty
            <div class="px-5 py-14 text-center text-gray-400 text-sm">{{ __('No clarification requests match this filter.') }}</div>
        @endforelse
    </div>
</div>
