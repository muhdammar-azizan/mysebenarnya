<?php

use App\Models\Agency;
use App\Models\ClarificationConsult;
use App\Models\ClarificationThread;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.mcmc')] class extends Component
{
    public ClarificationThread $thread;

    public string $replyText = '';

    public bool $inviteModalOpen = false;

    public ?int $inviteAgencyId = null;

    public string $inviteQuestion = '';

    public bool $closeModalOpen = false;

    public string $closeNote = '';

    public ?int $endConsultId = null;

    public function mount(ClarificationThread $thread): void
    {
        $this->authorize('view', $thread);

        $this->thread = $thread->load(['inquiry.agency', 'messages.user', 'messages.consultAgency', 'consults.consultedAgency', 'opener']);

        $this->thread->markRead('mcmc');
    }

    public function sendReply(): void
    {
        $this->authorize('reply', $this->thread);

        $this->validate(['replyText' => 'required|string|min:10']);

        try {
            $this->thread->reply(Auth::user(), $this->replyText);
        } catch (RuntimeException $e) {
            $this->addError('replyText', $e->getMessage());

            return;
        }

        $this->replyText = '';
        $this->thread->refresh();
        session()->flash('status', __('Response sent to agency.'));
    }

    public function getAvailableAgenciesProperty()
    {
        $busy = $this->thread->activeConsults->pluck('consulted_agency_id')->push($this->thread->inquiry->agency_id);

        return Agency::whereNotIn('id', $busy)->orderBy('name')->get();
    }

    public function openInviteModal(): void
    {
        $this->authorize('inviteConsult', $this->thread);
        $this->reset(['inviteAgencyId', 'inviteQuestion']);
        $this->inviteModalOpen = true;
    }

    public function confirmInvite(): void
    {
        $this->authorize('inviteConsult', $this->thread);

        $this->validate([
            'inviteAgencyId' => 'required|exists:agencies,id',
            'inviteQuestion' => 'required|string|min:15',
        ]);

        $agency = Agency::findOrFail($this->inviteAgencyId);

        try {
            $this->thread->inviteConsult(Auth::user(), $agency, $this->inviteQuestion);
        } catch (RuntimeException $e) {
            $this->addError('inviteAgencyId', $e->getMessage());

            return;
        }

        $this->inviteModalOpen = false;
        $this->thread->refresh();
        session()->flash('status', __(':agency invited to consult on this request.', ['agency' => $agency->name]));
    }

    public function confirmClose(): void
    {
        $this->authorize('close', $this->thread);

        $this->validate(['closeNote' => 'required|string|min:5']);

        $this->thread->close(Auth::user(), 'Closed by '.Auth::user()->name.' (MCMC): '.$this->closeNote, 'mcmc');

        $this->closeModalOpen = false;
        $this->thread->refresh();
        session()->flash('status', __('Clarification request closed.'));
    }

    public function confirmEndConsult(): void
    {
        $consult = ClarificationConsult::findOrFail($this->endConsultId);
        $this->authorize('end', $consult);

        $consult->end(Auth::user());

        $this->endConsultId = null;
        $this->thread->refresh();
    }
}; ?>

<div>
    <a href="{{ route('mcmc.clarifications.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-gray-500 hover:text-brand font-semibold text-sm mb-5">&larr; {{ __('Back to Clarification Requests') }}</a>

    @if (session('status'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 font-semibold text-sm mb-5">✓ {{ session('status') }}</div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-5">
        <div class="flex items-center gap-3 flex-wrap justify-between">
            <div>
                <div class="text-xs font-bold text-purple-600 uppercase tracking-wide mb-1">{{ $thread->topic?->value }}</div>
                <h1 class="font-display font-extrabold text-xl text-gray-900">{{ $thread->inquiry->title }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ __('Requested by') }} {{ $thread->inquiry->agency?->name }} &middot; {{ $thread->created_at->format('d M Y, H:i') }}</p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ match($thread->status->value) { 'open' => 'bg-purple-100 text-purple-600', 'answered' => 'bg-blue-100 text-blue-600', default => 'bg-green-100 text-green-700' } }}">
                {{ $thread->status->label() }}
            </span>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-3 gap-4 text-sm">
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase">{{ __('Category') }}</div>
                <div class="font-semibold text-gray-700">{{ $thread->inquiry->category?->value }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase">{{ __('Priority') }}</div>
                <div class="font-semibold {{ $thread->priority?->value === 'Urgent' ? 'text-brand' : 'text-gray-700' }}">{{ $thread->priority?->value }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase">{{ __('Consulting') }}</div>
                <div class="font-semibold text-gray-700">
                    @forelse ($thread->activeConsults as $c)
                        {{ $c->consultedAgency->name }}@if (! $loop->last), @endif
                    @empty
                        —
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden mb-5">
        <div class="p-6 flex flex-col gap-4">
            @foreach ($thread->messages as $message)
                <div class="flex gap-3 {{ $message->user_id === auth()->id() ? 'flex-row-reverse text-right' : '' }}">
                    <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold {{ $message->is_system ? 'bg-gray-100 text-gray-500' : ($message->isFromConsultedAgency() ? 'bg-teal-100 text-teal-700' : 'bg-brand-light text-brand') }}">
                        {{ collect(explode(' ', $message->user->name))->map(fn ($w) => $w[0] ?? '')->take(2)->implode('') }}
                    </div>
                    <div class="max-w-md">
                        <div class="text-xs font-bold text-gray-500 mb-1">
                            {{ $message->user->name }}
                            @if ($message->isFromConsultedAgency())
                                &middot; {{ $message->consultAgency->name }} ({{ __('Consulted') }})
                            @endif
                        </div>
                        <div class="{{ $message->is_system ? 'italic text-gray-500 text-sm' : 'bg-gray-50 rounded-xl px-4 py-2.5 text-sm text-gray-700' }}">
                            {{ $message->message }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @can('reply', $thread)
            <div class="px-6 py-4 border-t border-gray-100">
                <textarea wire:model="replyText" rows="3" placeholder="{{ __('Write a response to the agency...') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand mb-2"></textarea>
                <x-input-error :messages="$errors->get('replyText')" class="mb-2" />
                <div class="flex gap-2">
                    <button wire:click="sendReply" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Send Response') }}</button>
                    <button wire:click="openInviteModal" class="border border-teal-300 text-teal-700 hover:bg-teal-50 font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Consult Another Agency') }}</button>
                    <button wire:click="$set('closeModalOpen', true)" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Close Request') }}</button>
                </div>
            </div>
        @endcan
    </div>

    @if ($thread->consults->isNotEmpty())
        <div class="bg-white border border-gray-100 rounded-2xl p-6">
            <div class="font-bold text-gray-900 mb-4">{{ __('Consultations') }}</div>
            <div class="flex flex-col gap-3">
                @foreach ($thread->consults as $consult)
                    <div class="flex items-center justify-between gap-3 pb-3 border-b border-gray-50 last:border-0 last:pb-0">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">{{ $consult->consultedAgency->name }}</div>
                            <div class="text-xs text-gray-400">{{ __('Invited') }} {{ $consult->created_at->diffForHumans() }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ match($consult->status->value) { 'pending' => 'bg-amber-100 text-amber-700', 'responded' => 'bg-teal-100 text-teal-700', default => 'bg-gray-100 text-gray-500' } }}">
                                {{ str($consult->status->value)->headline() }}
                            </span>
                            @can('end', $consult)
                                <button wire:click="$set('endConsultId', {{ $consult->id }})" class="text-xs font-bold text-gray-400 hover:text-brand">{{ __('End') }}</button>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($inviteModalOpen)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="font-bold text-gray-900 mb-4">{{ __('Consult Another Agency') }}</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Agency') }}</label>
                        <select wire:model="inviteAgencyId" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                            <option value="">{{ __('Select an agency...') }}</option>
                            @foreach ($this->availableAgencies as $agency)
                                <option value="{{ $agency->id }}">{{ $agency->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('inviteAgencyId')" class="mt-1.5" />
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Question for this agency') }}</label>
                        <textarea wire:model="inviteQuestion" rows="4" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand"></textarea>
                        <x-input-error :messages="$errors->get('inviteQuestion')" class="mt-1.5" />
                    </div>
                </div>
                <div class="flex gap-3 mt-5">
                    <button wire:click="confirmInvite" class="flex-1 bg-teal-600 hover:bg-teal-700 text-white font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Invite') }}</button>
                    <button wire:click="$set('inviteModalOpen', false)" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if ($closeModalOpen)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="font-bold text-gray-900 mb-2">{{ __('Close this clarification request?') }}</h3>
                <textarea wire:model="closeNote" rows="3" placeholder="{{ __('Reason (required)') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand mb-2"></textarea>
                <x-input-error :messages="$errors->get('closeNote')" class="mb-3" />
                <div class="flex gap-3">
                    <button wire:click="confirmClose" class="flex-1 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Close Request') }}</button>
                    <button wire:click="$set('closeModalOpen', false)" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if ($endConsultId)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-xl p-6 w-full max-w-sm text-center">
                <h3 class="font-bold text-gray-900 mb-4">{{ __('End this consultation?') }}</h3>
                <div class="flex gap-3">
                    <button wire:click="confirmEndConsult" class="flex-1 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('End') }}</button>
                    <button wire:click="$set('endConsultId', null)" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
