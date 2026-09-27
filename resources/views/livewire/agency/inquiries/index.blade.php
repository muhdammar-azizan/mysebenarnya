<?php

use App\Enums\ConsultStatus;
use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\ClarificationConsult;
use App\Models\Inquiry;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.agency')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'assigned';

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'All';

    #[Url]
    public string $category = 'All';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function getRowsProperty()
    {
        $agencyId = Auth::user()->agency_id;

        return Inquiry::where('agency_id', $agencyId)
            ->with('activityLogs')
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->status === 'awaiting', fn ($q) => $q->where('status', InquiryStatus::UnderInvestigation)->whereNull('jurisdiction_accepted_at'))
            ->when($this->status === 'resolved', fn ($q) => $q->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake]))
            ->when($this->status !== 'All' && $this->status !== 'awaiting' && $this->status !== 'resolved', fn ($q) => $q->where('status', $this->status))
            ->when($this->category !== 'All', fn ($q) => $q->where('category', $this->category))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate(10);
    }

    public function notesFromMcmc(Inquiry $inquiry): ?string
    {
        return $inquiry->activityLogs
            ->whereIn('action', ['assigned', 'reassigned'])
            ->sortByDesc('created_at')
            ->first()
            ?->notes;
    }

    public function getAssignedCountProperty(): int
    {
        return Inquiry::where('agency_id', Auth::user()->agency_id)->count();
    }

    public function getCategoriesProperty(): array
    {
        return InquiryCategory::cases();
    }

    public function getConsultRowsProperty()
    {
        return ClarificationConsult::with(['thread.inquiry', 'thread.inquiry.agency'])
            ->where('consulted_agency_id', Auth::user()->agency_id)
            ->latest()
            ->get();
    }

    public function getConsultPendingCountProperty(): int
    {
        return ClarificationConsult::where('consulted_agency_id', Auth::user()->agency_id)
            ->where('status', ConsultStatus::Pending)
            ->count();
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('Assigned Inquiries') }}</h1>
    <p class="text-gray-500 text-sm mb-5">{{ __('Review jurisdiction, investigate, and record your verdict.') }}</p>

    <div class="flex gap-1 border-b border-gray-100 mb-6">
        <button wire:click="$set('tab', 'assigned')" class="px-4 py-2.5 text-sm font-bold flex items-center gap-1.5 {{ $tab === 'assigned' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">
            {{ __('Assigned to :agency', ['agency' => auth()->user()->agency->name]) }}
            <span class="bg-gray-100 text-gray-500 text-[10.5px] font-bold px-2 py-0.5 rounded-full">{{ $this->assignedCount }}</span>
        </button>
        <button wire:click="$set('tab', 'consult')" class="px-4 py-2.5 text-sm font-bold flex items-center gap-1.5 {{ $tab === 'consult' ? 'text-teal-700 border-b-2 border-teal-700' : 'text-gray-400' }}">
            {{ __('Consultations') }}
            @if ($this->consultPendingCount > 0)
                <span class="bg-teal-700 text-white text-[10.5px] font-bold px-2 py-0.5 rounded-full">{{ $this->consultPendingCount }}</span>
            @endif
        </button>
    </div>

    @if ($tab === 'consult')
        <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden divide-y divide-gray-50">
            @forelse ($this->consultRows as $consult)
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
    @else
    <div class="flex flex-wrap gap-3 mb-6">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search by title...') }}"
            class="flex-1 min-w-[200px] rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Assigned') }}</option>
            <option value="awaiting">{{ __('Awaiting Jurisdiction Review') }}</option>
            <option value="{{ InquiryStatus::UnderInvestigation->value }}">{{ __('Under Investigation') }}</option>
            <option value="resolved">{{ __('Resolved (Verified or Fake)') }}</option>
            <option value="{{ InquiryStatus::VerifiedTrue->value }}">{{ __('Verified as True') }}</option>
            <option value="{{ InquiryStatus::IdentifiedFake->value }}">{{ __('Identified as Fake') }}</option>
            <option value="{{ InquiryStatus::Rejected->value }}">{{ __('Rejected by us') }}</option>
        </select>
        <select wire:model.live="category" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Categories') }}</option>
            @foreach ($this->categories as $case)
                <option value="{{ $case->value }}">{{ $case->value }}</option>
            @endforeach
        </select>
        <div class="flex items-center gap-2">
            <label class="text-xs text-gray-500">{{ __('From') }}</label>
            <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
            <label class="text-xs text-gray-500">{{ __('To') }}</label>
            <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <table class="w-full text-sm table-fixed">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">
                    <th class="px-5 py-3 w-[34%]">{{ __('Title') }}</th>
                    <th class="px-5 py-3 w-[20%]">{{ __('Status') }}</th>
                    <th class="px-5 py-3">{{ __('Notes from MCMC') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $inquiry)
                    @php $note = $this->notesFromMcmc($inquiry); @endphp
                    <tr wire:key="row-{{ $inquiry->id }}" onclick="window.location='{{ route('agency.inquiries.show', $inquiry) }}'" class="border-t border-gray-50 hover:bg-gray-50 cursor-pointer">
                        <td class="px-5 py-3.5">
                            <div class="font-semibold text-gray-900">{{ $inquiry->title }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $inquiry->category?->value }} &middot; {{ __('Assigned') }} {{ $inquiry->reviewed_at?->format('d M Y') ?? $inquiry->updated_at->format('d M Y') }}</div>
                            @if ($inquiry->hasOpenClarification())
                                <div class="inline-flex items-center gap-1.5 mt-1.5 bg-purple-50 text-purple-700 text-[10.5px] font-bold px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>{{ __('Clarification Pending') }}
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <x-inquiry-status-badge :status="$inquiry->status" />
                            @if ($inquiry->isAwaitingJurisdiction())
                                <span class="ml-1 inline-block px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-500">{{ __('Awaiting Review') }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-500 text-xs truncate" title="{{ $note }}">{{ $note ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-10 text-center text-gray-400">{{ __('No assigned inquiries found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t border-gray-100">{{ $this->rows->links() }}</div>
    </div>
    @endif
</div>
