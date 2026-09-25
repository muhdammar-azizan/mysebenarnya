<?php

use App\Enums\ConsultStatus;
use App\Models\ClarificationConsult;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.agency')] class extends Component
{
    public function getRowsProperty()
    {
        return ClarificationConsult::with(['thread.inquiry', 'consultedAgency'])
            ->where('consulted_agency_id', Auth::user()->agency_id)
            ->latest()
            ->get();
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('Consultations') }}</h1>
    <p class="text-gray-500 text-sm mb-6">{{ __('Cases where MCMC has asked your agency for advice.') }}</p>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden divide-y divide-gray-50">
        @forelse ($this->rows as $consult)
            <a href="{{ route('agency.consultations.show', $consult) }}" wire:navigate class="flex items-center justify-between gap-3 px-5 py-4 hover:bg-gray-50">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-gray-900 truncate">{{ $consult->thread->inquiry->title }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">{{ __('Owned by') }} {{ $consult->thread->inquiry->agency?->name }} &middot; {{ $consult->created_at->format('d M Y') }}</div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    @if ($consult->unread)
                        <span class="w-2 h-2 rounded-full bg-brand"></span>
                    @endif
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ match($consult->status) { ConsultStatus::Pending => 'bg-amber-100 text-amber-700', ConsultStatus::Responded => 'bg-teal-100 text-teal-700', ConsultStatus::Ended => 'bg-gray-100 text-gray-500' } }}">
                        {{ match($consult->status) { ConsultStatus::Pending => __('Awaiting Your Advice'), ConsultStatus::Responded => __('Advice Sent'), ConsultStatus::Ended => __('Ended') } }}
                    </span>
                </div>
            </a>
        @empty
            <div class="px-5 py-14 text-center text-gray-400 text-sm">{{ __('MCMC has not requested your advice on any case yet.') }}</div>
        @endforelse
    </div>
</div>
