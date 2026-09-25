<?php

use App\Models\ClarificationConsult;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.agency')] class extends Component
{
    public ClarificationConsult $consult;

    public string $replyText = '';

    public function mount(ClarificationConsult $consult): void
    {
        $this->authorize('view', $consult);

        $this->consult = $consult->load(['thread.inquiry.submitter', 'thread.messages.user', 'thread.messages.consultAgency', 'consultedAgency']);

        $this->consult->markRead();
    }

    public function sendReply(): void
    {
        $this->authorize('reply', $this->consult);

        $this->validate(['replyText' => 'required|string|min:15']);

        try {
            $this->consult->reply(Auth::user(), $this->replyText);
        } catch (RuntimeException $e) {
            $this->addError('replyText', $e->getMessage());

            return;
        }

        $this->replyText = '';
        $this->consult->refresh();
        session()->flash('status', __('Your advice has been sent to MCMC.'));
    }

    public function getMyMessagesProperty()
    {
        return $this->consult->thread->messages->where('consult_agency_id', $this->consult->consulted_agency_id);
    }
}; ?>

<div>
    <a href="{{ route('agency.consultations.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-gray-500 hover:text-brand font-semibold text-sm mb-5">&larr; {{ __('Back to Consultations') }}</a>

    @if (session('status'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 font-semibold text-sm mb-5">✓ {{ session('status') }}</div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-5">
        <div class="text-xs font-bold text-teal-600 uppercase tracking-wide mb-1">{{ __('Consulted by MCMC') }}</div>
        <h1 class="font-display font-extrabold text-xl text-gray-900 mb-2">{{ $consult->thread->inquiry->title }}</h1>
        <p class="text-sm text-gray-500">{{ __('Case owned by') }} <strong>{{ $consult->thread->inquiry->agency?->name }}</strong> &middot; {{ $consult->thread->inquiry->category?->value }}</p>

        <div class="mt-4 pt-4 border-t border-gray-100">
            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide mb-1">{{ __('Case Description') }}</div>
            <p class="text-sm text-gray-600 leading-relaxed">{{ $consult->thread->inquiry->description }}</p>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-100">
            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide mb-1">{{ __("MCMC's Question for You") }}</div>
            <p class="text-sm text-gray-700 font-medium">{{ $consult->question }}</p>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 font-bold text-gray-900">{{ __('Your Advice Thread') }}</div>
        <div class="p-6 flex flex-col gap-4">
            @forelse ($this->myMessages as $message)
                <div class="flex gap-3 {{ $message->user_id === auth()->id() ? 'flex-row-reverse text-right' : '' }}">
                    <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold bg-teal-100 text-teal-700">
                        {{ collect(explode(' ', $message->user->name))->map(fn ($w) => $w[0] ?? '')->take(2)->implode('') }}
                    </div>
                    <div class="max-w-md">
                        <div class="text-xs font-bold text-gray-500 mb-1">{{ $message->user->name }}</div>
                        <div class="bg-gray-50 rounded-xl px-4 py-2.5 text-sm text-gray-700">{{ $message->message }}</div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400">{{ __('No advice sent yet.') }}</p>
            @endforelse
        </div>

        @can('reply', $consult)
            <div class="px-6 py-4 border-t border-gray-100">
                <textarea wire:model="replyText" rows="3" placeholder="{{ __('Write your advice for MCMC...') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand mb-2"></textarea>
                <x-input-error :messages="$errors->get('replyText')" class="mb-2" />
                <button wire:click="sendReply" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Send Advice') }}</button>
            </div>
        @else
            <div class="px-6 py-4 border-t border-gray-100 text-sm text-gray-400 italic">
                {{ __('This consultation has ended. No further advice can be sent.') }}
            </div>
        @endcan
    </div>
</div>
