<?php

use App\Enums\ClarificationPriority;
use App\Enums\ClarificationTopic;
use App\Enums\InquiryStatus;
use App\Models\ClarificationThread;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\InquiryEvidence;
use App\Notifications\InquiryStatusChanged;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.agency')] class extends Component
{
    use WithFileUploads;

    public Inquiry $inquiry;

    public string $tab = 'details';

    public bool $rejectModalOpen = false;

    public string $rejectReason = '';

    public string $verdict = 'keep';

    public string $investigationNotes = '';

    public array $newEvidence = [];

    public bool $finalizeModalOpen = false;

    public bool $clarifyModalOpen = false;

    public string $clarifyTopic = '';

    public string $clarifyPriority = 'Normal';

    public string $clarifyText = '';

    public array $replyText = [];

    public ?int $closingThreadId = null;

    public string $closeNote = '';

    public function mount(Inquiry $inquiry): void
    {
        $this->authorize('view', $inquiry);

        $this->inquiry = $inquiry->load(['submitter', 'evidence', 'activityLogs.user', 'agency']);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;

        if ($tab === 'clarify') {
            $this->clarificationThreads->each(fn ($thread) => $thread->markRead('agency'));
        }
    }

    public function getClarificationThreadsProperty()
    {
        return $this->inquiry->clarificationThreads()->with(['messages.user', 'messages.consultAgency', 'consults.consultedAgency'])->latest()->get();
    }

    public function getTopicsProperty(): array
    {
        return ClarificationTopic::cases();
    }

    public function openClarifyModal(): void
    {
        $this->authorize('requestClarification', $this->inquiry);
        $this->reset(['clarifyTopic', 'clarifyText']);
        $this->clarifyPriority = 'Normal';
        $this->clarifyModalOpen = true;
    }

    public function submitClarify(): void
    {
        $this->authorize('requestClarification', $this->inquiry);

        $this->validate([
            'clarifyTopic' => 'required|string',
            'clarifyText' => 'required|string|min:20',
        ]);

        try {
            ClarificationThread::open(
                inquiry: $this->inquiry,
                openedBy: Auth::user(),
                topic: ClarificationTopic::from($this->clarifyTopic),
                priority: ClarificationPriority::from($this->clarifyPriority),
                text: $this->clarifyText,
            );
        } catch (RuntimeException $e) {
            $this->addError('clarifyText', $e->getMessage());

            return;
        }

        $this->clarifyModalOpen = false;
        $this->tab = 'clarify';
        session()->flash('status', __('Clarification request sent to MCMC.'));
    }

    public function sendReply(int $threadId): void
    {
        $thread = \App\Models\ClarificationThread::findOrFail($threadId);
        $this->authorize('reply', $thread);

        $text = trim($this->replyText[$threadId] ?? '');

        if (mb_strlen($text) < 5) {
            $this->addError('replyText.'.$threadId, __('Please write at least 5 characters.'));

            return;
        }

        try {
            $thread->reply(Auth::user(), $text);
        } catch (RuntimeException $e) {
            $this->addError('replyText.'.$threadId, $e->getMessage());

            return;
        }

        $this->replyText[$threadId] = '';
    }

    public function openCloseModal(int $threadId): void
    {
        $this->closingThreadId = $threadId;
        $this->closeNote = '';
    }

    public function confirmCloseThread(): void
    {
        $thread = \App\Models\ClarificationThread::findOrFail($this->closingThreadId);
        $this->authorize('close', $thread);

        $this->validate(['closeNote' => 'required|string|min:5']);

        $thread->close(Auth::user(), 'Marked as resolved by '.Auth::user()->name.': '.$this->closeNote, 'agency');

        $this->closingThreadId = null;
        $this->closeNote = '';
        session()->flash('status', __('Clarification request resolved.'));
    }

    public function acceptJurisdiction(): void
    {
        $this->authorize('reviewJurisdiction', $this->inquiry);

        $this->inquiry->update(['jurisdiction_accepted_at' => now()]);

        InquiryActivityLog::create([
            'inquiry_id' => $this->inquiry->id,
            'user_id' => Auth::id(),
            'action' => 'jurisdiction_accepted',
            'notes' => 'Accepted by '.Auth::user()->name.' — within '.$this->inquiry->agency->name.' jurisdiction.',
        ]);

        $this->inquiry->refresh();
        session()->flash('status', __('Jurisdiction accepted. You may now begin your investigation.'));
    }

    public function confirmReject(): void
    {
        $this->authorize('reviewJurisdiction', $this->inquiry);

        $this->validate(['rejectReason' => 'required|string|min:5']);

        $this->inquiry->update([
            'status' => InquiryStatus::Rejected,
            'resolution_notes' => $this->rejectReason,
        ]);

        InquiryActivityLog::create([
            'inquiry_id' => $this->inquiry->id,
            'user_id' => Auth::id(),
            'action' => 'jurisdiction_rejected',
            'from_status' => InquiryStatus::UnderInvestigation->value,
            'to_status' => InquiryStatus::Rejected->value,
            'notes' => $this->rejectReason,
        ]);

        $this->inquiry->submitter?->notify(new InquiryStatusChanged($this->inquiry, InquiryStatus::UnderInvestigation->value, InquiryStatus::Rejected->value));
        $this->inquiry->reviewer?->notify(new InquiryStatusChanged($this->inquiry, InquiryStatus::UnderInvestigation->value, InquiryStatus::Rejected->value));

        $this->rejectModalOpen = false;
        session()->flash('status', __('Inquiry rejected and returned to MCMC for reassignment.'));
        $this->redirect(route('agency.inquiries.index'), navigate: true);
    }

    protected function storeEvidence(): void
    {
        foreach ($this->newEvidence as $file) {
            $path = $file->store('evidence', 'public');

            InquiryEvidence::create([
                'inquiry_id' => $this->inquiry->id,
                'uploaded_by' => Auth::id(),
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        $this->newEvidence = [];
    }

    public function saveDraft(): void
    {
        $this->authorize('updateInvestigation', $this->inquiry);

        $this->storeEvidence();

        InquiryActivityLog::create([
            'inquiry_id' => $this->inquiry->id,
            'user_id' => Auth::id(),
            'action' => 'investigation_updated',
            'notes' => $this->investigationNotes ?: 'Progress notes updated — still under investigation.',
        ]);

        $this->investigationNotes = '';
        $this->inquiry->refresh();
        session()->flash('status', __('Draft notes saved. Inquiry remains under investigation.'));
    }

    public function openFinalizeModal(): void
    {
        $this->validate(['investigationNotes' => 'required|string|min:10'], [], ['investigationNotes' => __('findings')]);
        $this->finalizeModalOpen = true;
    }

    public function confirmFinalize(): void
    {
        $this->authorize('updateInvestigation', $this->inquiry);

        $this->validate([
            'verdict' => 'required|in:verified,fake',
            'investigationNotes' => 'required|string|min:10',
        ]);

        $this->storeEvidence();

        $finalStatus = $this->verdict === 'verified' ? InquiryStatus::VerifiedTrue : InquiryStatus::IdentifiedFake;

        $this->inquiry->update([
            'status' => $finalStatus,
            'resolution_notes' => $this->investigationNotes,
            'resolved_at' => now(),
        ]);

        InquiryActivityLog::create([
            'inquiry_id' => $this->inquiry->id,
            'user_id' => Auth::id(),
            'action' => 'verdict_finalized',
            'from_status' => InquiryStatus::UnderInvestigation->value,
            'to_status' => $finalStatus->value,
            'notes' => $this->investigationNotes,
        ]);

        $this->inquiry->submitter?->notify(new InquiryStatusChanged($this->inquiry, InquiryStatus::UnderInvestigation->value, $finalStatus->value));
        $this->inquiry->reviewer?->notify(new InquiryStatusChanged($this->inquiry, InquiryStatus::UnderInvestigation->value, $finalStatus->value));

        $this->finalizeModalOpen = false;
        session()->flash('status', __('Inquiry marked as :status.', ['status' => $finalStatus->label()]));
        $this->redirect(route('agency.inquiries.index'), navigate: true);
    }
}; ?>

<div>
    <a href="{{ route('agency.inquiries.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-gray-500 hover:text-brand font-semibold text-sm mb-5">&larr; {{ __('Back to Assigned Inquiries') }}</a>

    @if (session('status'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 font-semibold text-sm mb-5">✓ {{ session('status') }}</div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-5">
        <div class="flex items-center gap-3.5 flex-wrap">
            <h1 class="font-display font-extrabold text-xl text-gray-900">{{ $inquiry->title }}</h1>
            <x-inquiry-status-badge :status="$inquiry->status" />
            @if ($inquiry->isAwaitingJurisdiction())
                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-500">{{ __('Awaiting Jurisdiction Review') }}</span>
            @endif
        </div>
        <div class="flex gap-8 flex-wrap mt-4">
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Category') }}</div>
                <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->category?->value }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Submitted By') }}</div>
                <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->submitter?->name }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Prior Submissions') }}</div>
                <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->submitter?->submittedInquiries()->count() - 1 }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Date Assigned') }}</div>
                <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->updated_at->format('d M Y') }}</div>
            </div>
        </div>
    </div>

    <div class="flex border-b border-gray-100 mb-5">
        @foreach (['details' => __('Details'), 'evidence' => __('Evidence'), 'clarify' => __('Clarification'), 'activity' => __('Activity Log')] as $key => $label)
            <button wire:click="setTab('{{ $key }}')" class="px-5 py-3 text-sm font-bold {{ $tab === $key ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">
                {{ $label }}
                @if ($key === 'clarify' && $this->clarificationThreads->where('unread_by_agency', true)->count())
                    <span class="ml-1 inline-block w-2 h-2 rounded-full bg-brand align-middle"></span>
                @endif
            </button>
        @endforeach
    </div>

    @if ($tab === 'details')
        <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-6">
            <div class="font-bold text-gray-900 mb-2">{{ __('Description') }}</div>
            <p class="text-sm text-gray-600 leading-relaxed">{{ $inquiry->description }}</p>
            @if ($inquiry->source_url)
                <a href="{{ $inquiry->source_url }}" target="_blank" class="inline-block mt-3 text-sm text-brand hover:underline">{{ $inquiry->source_url }}</a>
            @endif
        </div>

        @can('reviewJurisdiction', $inquiry)
            <div class="bg-white border border-gray-100 rounded-2xl p-6">
                <div class="font-bold text-gray-900 mb-2">{{ __('Jurisdiction Review') }}</div>
                <p class="text-sm text-gray-500 mb-4">{{ __('Confirm whether this inquiry falls within your agency\'s jurisdiction.') }}</p>
                <div class="flex gap-3">
                    <button wire:click="acceptJurisdiction" class="bg-green-600 hover:bg-green-700 text-white font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Accept Jurisdiction') }}</button>
                    <button wire:click="$set('rejectModalOpen', true)" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Reject — Outside Jurisdiction') }}</button>
                </div>
            </div>
        @elseif ($inquiry->resolution_notes && in_array($inquiry->status, [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake, InquiryStatus::Rejected]))
            <div class="bg-white border border-gray-100 rounded-2xl p-6">
                <div class="font-bold text-gray-900 mb-2">{{ __('Resolution Notes') }}</div>
                <p class="text-sm text-gray-600 leading-relaxed">{{ $inquiry->resolution_notes }}</p>
            </div>
        @endcan

        @can('updateInvestigation', $inquiry)
            <div class="bg-white border border-gray-100 rounded-2xl p-6 mt-6">
                <div class="font-bold text-gray-900 mb-4">{{ __('Investigation') }}</div>

                <div class="grid grid-cols-3 gap-3 mb-5">
                    @foreach ([
                        'verified' => [__('Verified as True'), __('Confirmed as genuine, accurate news.'), 'border-green-500 bg-green-50'],
                        'fake' => [__('Identified as Fake'), __('Determined to be false or misleading information.'), 'border-brand bg-brand-light'],
                        'keep' => [__('Keep as Under Investigation'), __('Still reviewing, not ready for a final verdict yet.'), 'border-amber-500 bg-amber-50'],
                    ] as $key => [$label, $desc, $activeClasses])
                        <button type="button" wire:click="$set('verdict', '{{ $key }}')"
                            class="text-left border-2 rounded-xl p-4 {{ $verdict === $key ? $activeClasses : 'border-gray-100' }}">
                            <div class="font-bold text-sm text-gray-900">{{ $label }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ $desc }}</div>
                        </button>
                    @endforeach
                </div>

                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Findings / Notes') }}</label>
                <textarea wire:model="investigationNotes" rows="4" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand mb-2"></textarea>
                <x-input-error :messages="$errors->get('investigationNotes')" class="mb-3" />

                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Attach Supporting Evidence (optional)') }}</label>
                <input type="file" wire:model="newEvidence" multiple class="block text-sm mb-5" />

                <div class="flex gap-3">
                    @if ($verdict === 'keep')
                        <button wire:click="saveDraft" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-6 py-3 rounded-lg">{{ __('Save Draft') }}</button>
                    @else
                        <button wire:click="openFinalizeModal" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-6 py-3 rounded-lg">{{ __('Submit Verdict') }}</button>
                    @endif
                </div>
            </div>
        @endcan
    @elseif ($tab === 'evidence')
        <div class="bg-white border border-gray-100 rounded-2xl p-6">
            @forelse ($inquiry->evidence as $file)
                <a href="{{ asset('storage/'.$file->file_path) }}" target="_blank" class="flex items-center gap-2 bg-gray-50 hover:bg-gray-100 rounded-lg px-3.5 py-2.5 text-sm font-semibold text-gray-700 mb-2 w-fit">📎 {{ $file->file_name }}</a>
            @empty
                <p class="text-sm text-gray-400">{{ __('No evidence files attached.') }}</p>
            @endforelse
        </div>
    @elseif ($tab === 'clarify')
        @can('requestClarification', $inquiry)
            <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-6">
                <div class="font-bold text-gray-900 mb-2">{{ __('Need more information?') }}</div>
                <p class="text-sm text-gray-500 mb-4">{{ __('Ask MCMC a clarifying question about this case.') }}</p>
                <button wire:click="openClarifyModal" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Request Clarification') }}</button>
            </div>
        @endcan

        <div class="flex flex-col gap-5">
            @forelse ($this->clarificationThreads as $thread)
                <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <div class="font-bold text-gray-900">{{ $thread->topic?->value ?? $thread->subject }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $thread->created_at->format('d M Y, H:i') }}
                                @if ($thread->priority?->value === 'Urgent')
                                    &middot; <span class="text-brand font-bold">{{ __('Urgent') }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ match($thread->status->value) { 'open' => 'bg-purple-100 text-purple-600', 'answered' => 'bg-blue-100 text-blue-600', default => 'bg-green-100 text-green-700' } }}">
                            {{ $thread->status->label() }}
                        </span>
                    </div>

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

                    @if ($thread->isActive())
                        <div class="px-6 py-4 border-t border-gray-100">
                            <textarea wire:model="replyText.{{ $thread->id }}" rows="2" placeholder="{{ __('Write a follow-up...') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand mb-2"></textarea>
                            <x-input-error :messages="$errors->get('replyText.'.$thread->id)" class="mb-2" />
                            <div class="flex gap-2">
                                <button wire:click="sendReply({{ $thread->id }})" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2 rounded-lg">{{ __('Send') }}</button>
                                <button wire:click="openCloseModal({{ $thread->id }})" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2 rounded-lg">{{ __('Mark as Resolved') }}</button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="bg-white border border-gray-100 rounded-2xl p-10 text-center text-gray-400">
                    {{ __('No clarification requests for this inquiry yet.') }}
                </div>
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

    @if ($rejectModalOpen)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="font-bold text-gray-900 mb-2">{{ __('Reject this inquiry?') }}</h3>
                <p class="text-sm text-gray-500 mb-4">{{ __('It will be returned to MCMC for reassignment to another agency.') }}</p>
                <textarea wire:model="rejectReason" rows="3" placeholder="{{ __('Reason (required)') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand mb-2"></textarea>
                <x-input-error :messages="$errors->get('rejectReason')" class="mb-3" />
                <div class="flex gap-3">
                    <button wire:click="confirmReject" class="flex-1 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Reject') }}</button>
                    <button wire:click="$set('rejectModalOpen', false)" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if ($finalizeModalOpen)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="font-bold text-gray-900 mb-2">{{ __('Confirm your verdict') }}</h3>
                <p class="text-sm text-gray-500 mb-4">
                    {{ __('This will mark the inquiry as :status and cannot be undone.', ['status' => $verdict === 'verified' ? __('Verified as True') : __('Identified as Fake')]) }}
                </p>
                <div class="flex gap-3">
                    <button wire:click="confirmFinalize" class="flex-1 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Confirm') }}</button>
                    <button wire:click="$set('finalizeModalOpen', false)" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if ($clarifyModalOpen)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="font-bold text-gray-900 mb-4">{{ __('Request Clarification from MCMC') }}</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Topic') }}</label>
                        <select wire:model="clarifyTopic" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                            <option value="">{{ __('Select a topic...') }}</option>
                            @foreach ($this->topics as $case)
                                <option value="{{ $case->value }}">{{ $case->value }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('clarifyTopic')" class="mt-1.5" />
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Priority') }}</label>
                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('clarifyPriority', 'Normal')" class="flex-1 border-2 rounded-lg p-3 text-left {{ $clarifyPriority === 'Normal' ? 'border-purple-500 bg-purple-50' : 'border-gray-100' }}">
                                <div class="text-sm font-bold text-gray-900">{{ __('Normal') }}</div>
                                <div class="text-xs text-gray-500">{{ __('Response expected within 2 working days') }}</div>
                            </button>
                            <button type="button" wire:click="$set('clarifyPriority', 'Urgent')" class="flex-1 border-2 rounded-lg p-3 text-left {{ $clarifyPriority === 'Urgent' ? 'border-brand bg-brand-light' : 'border-gray-100' }}">
                                <div class="text-sm font-bold text-gray-900">{{ __('Urgent') }}</div>
                                <div class="text-xs text-gray-500">{{ __('Time-sensitive / high public risk') }}</div>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Your Question') }}</label>
                        <textarea wire:model="clarifyText" rows="4" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand"></textarea>
                        <x-input-error :messages="$errors->get('clarifyText')" class="mt-1.5" />
                    </div>
                </div>
                <div class="flex gap-3 mt-5">
                    <button wire:click="submitClarify" class="flex-1 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Send Request') }}</button>
                    <button wire:click="$set('clarifyModalOpen', false)" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if ($closingThreadId)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="font-bold text-gray-900 mb-2">{{ __('Mark this clarification as resolved?') }}</h3>
                <textarea wire:model="closeNote" rows="3" placeholder="{{ __('Brief note (required)') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand mb-2"></textarea>
                <x-input-error :messages="$errors->get('closeNote')" class="mb-3" />
                <div class="flex gap-3">
                    <button wire:click="confirmCloseThread" class="flex-1 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Confirm') }}</button>
                    <button wire:click="$set('closingThreadId', null)" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
