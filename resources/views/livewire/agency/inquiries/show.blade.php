<?php

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\InquiryEvidence;
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

    public function mount(Inquiry $inquiry): void
    {
        $this->authorize('view', $inquiry);

        $this->inquiry = $inquiry->load(['submitter', 'evidence', 'activityLogs.user', 'agency']);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
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

        $this->finalizeModalOpen = false;
        session()->flash('status', __('Inquiry marked as :status.', ['status' => $finalStatus->value]));
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
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Date Assigned') }}</div>
                <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->updated_at->format('d M Y') }}</div>
            </div>
        </div>
    </div>

    <div class="flex border-b border-gray-100 mb-5">
        @foreach (['details' => __('Details'), 'evidence' => __('Evidence'), 'activity' => __('Activity Log')] as $key => $label)
            <button wire:click="setTab('{{ $key }}')" class="px-5 py-3 text-sm font-bold {{ $tab === $key ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ $label }}</button>
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
</div>
