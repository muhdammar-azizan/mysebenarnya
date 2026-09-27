<?php

use App\Concerns\GeneratesReports;
use App\Enums\ClarificationStatus;
use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.mcmc')] class extends Component
{
    use GeneratesReports;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = 'All';

    #[Url]
    public string $status = 'All';

    public ?int $panelInquiryId = null;

    public string $panelTab = 'details';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function openPanel(int $id): void
    {
        $this->panelInquiryId = $id;
        $this->panelTab = 'details';
    }

    public function closePanel(): void
    {
        $this->panelInquiryId = null;
    }

    protected function baseQuery()
    {
        return Inquiry::query()
            ->where('status', '!=', InquiryStatus::Discarded)
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->category !== 'All', fn ($q) => $q->where('category', $this->category))
            ->when($this->status !== 'All', fn ($q) => match ($this->status) {
                'Awaiting Review' => $q->where('status', InquiryStatus::Submitted),
                'Needs Reassignment' => $q->where('status', InquiryStatus::Rejected),
                'Awaiting Agency Review' => $q->where('status', InquiryStatus::UnderInvestigation)->whereNull('jurisdiction_accepted_at'),
                'Under Investigation' => $q->where('status', InquiryStatus::UnderInvestigation)
                    ->whereNotNull('jurisdiction_accepted_at')
                    ->whereDoesntHave('clarificationThreads', fn ($qq) => $qq->where('status', ClarificationStatus::Open)),
                'Awaiting Clarification' => $q->where('status', InquiryStatus::UnderInvestigation)
                    ->whereHas('clarificationThreads', fn ($qq) => $qq->where('status', ClarificationStatus::Open)),
                'Verified True' => $q->where('status', InquiryStatus::VerifiedTrue),
                'Identified Fake' => $q->where('status', InquiryStatus::IdentifiedFake),
                default => $q,
            });
    }

    public function getRowsProperty()
    {
        return $this->baseQuery()->with('agency')->latest()->paginate(12);
    }

    public function getCategoriesProperty(): array
    {
        return InquiryCategory::cases();
    }

    public function rowStatus(Inquiry $inquiry): array
    {
        if ($inquiry->status === InquiryStatus::UnderInvestigation) {
            if ($inquiry->hasOpenClarification()) {
                return ['label' => __('Awaiting Clarification'), 'bg' => 'bg-purple-100', 'color' => 'text-purple-700'];
            }
            if ($inquiry->isAwaitingJurisdiction()) {
                return ['label' => __('Awaiting Agency Review'), 'bg' => 'bg-blue-100', 'color' => 'text-blue-700'];
            }

            return ['label' => __('Under Investigation'), 'bg' => 'bg-amber-100', 'color' => 'text-amber-700'];
        }

        return match ($inquiry->status) {
            InquiryStatus::Submitted => ['label' => __('Awaiting Review'), 'bg' => 'bg-gray-100', 'color' => 'text-gray-600'],
            InquiryStatus::Rejected => ['label' => __('Needs Reassignment'), 'bg' => 'bg-blue-100', 'color' => 'text-blue-700'],
            InquiryStatus::VerifiedTrue => ['label' => __('Verified True'), 'bg' => 'bg-green-100', 'color' => 'text-green-700'],
            InquiryStatus::IdentifiedFake => ['label' => __('Identified Fake'), 'bg' => 'bg-brand-light', 'color' => 'text-brand'],
            default => ['label' => $inquiry->status->label(), 'bg' => 'bg-gray-100', 'color' => 'text-gray-500'],
        };
    }

    public function answeredClarificationChip(Inquiry $inquiry): ?string
    {
        if ($inquiry->status !== InquiryStatus::UnderInvestigation || $inquiry->hasOpenClarification()) {
            return null;
        }

        $hasAnswered = $inquiry->clarificationThreads()->where('status', ClarificationStatus::Answered)->exists();

        return $hasAnswered ? __('Clarification Answered') : null;
    }

    public function getPanelInquiryProperty(): ?Inquiry
    {
        return $this->panelInquiryId
            ? Inquiry::with(['agency', 'evidence', 'activityLogs.user', 'clarificationThreads'])->find($this->panelInquiryId)
            : null;
    }

    public function getPanelActiveClarificationProperty(): ?\App\Models\ClarificationThread
    {
        return $this->panelInquiry?->clarificationThreads->sortByDesc('created_at')->first();
    }

    protected function exportFiltersLabel(): string
    {
        $parts = [];
        $parts[] = $this->status !== 'All' ? $this->status : __('All Statuses');
        $parts[] = $this->category !== 'All' ? $this->category : __('All Categories');
        if ($this->search) {
            $parts[] = __('Search: ":q"', ['q' => $this->search]);
        }

        return implode(' · ', $parts);
    }

    public function exportPdf()
    {
        $rows = $this->baseQuery()->latest()->get()->map(fn ($i) => [
            $i->title,
            $i->category?->value,
            $this->rowStatus($i)['label'],
        ])->all();

        return $this->downloadPdf('SEBENARNYA_All-Inquiries', 'All Inquiries', $this->exportFiltersLabel(), [[
            'name' => 'All Inquiries',
            'tables' => [[
                'heading' => 'Inquiry Register',
                'columns' => ['Title', 'Category', 'Status'],
                'rows' => $rows,
            ]],
        ]]);
    }
}; ?>

<div>
    <div class="flex items-start justify-between gap-4 mb-1">
        <div>
            <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('All Inquiries') }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ __('Complete record of every inquiry across all stages.') }}</p>
        </div>
        <button wire:click="exportPdf" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg whitespace-nowrap flex items-center gap-2">
            📄 {{ __('Export Report') }}
        </button>
    </div>

    <div class="flex gap-3 my-5">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search by title...') }}" class="flex-1 max-w-xs rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <select wire:model.live="category" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Categories') }}</option>
            @foreach ($this->categories as $case)
                <option value="{{ $case->value }}">{{ $case->value }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Statuses') }}</option>
            <option value="Awaiting Review">{{ __('Awaiting Review') }}</option>
            <option value="Needs Reassignment">{{ __('Needs Reassignment') }}</option>
            <option value="Awaiting Agency Review">{{ __('Awaiting Agency Review') }}</option>
            <option value="Under Investigation">{{ __('Under Investigation') }}</option>
            <option value="Awaiting Clarification">{{ __('Awaiting Clarification') }}</option>
            <option value="Verified True">{{ __('Verified True') }}</option>
            <option value="Identified Fake">{{ __('Identified Fake') }}</option>
        </select>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <div class="grid grid-cols-[2.4fr_1.3fr_1.5fr] gap-4 px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide border-b border-gray-100">
            <div>{{ __('Title') }}</div><div>{{ __('Category') }}</div><div>{{ __('Status') }}</div>
        </div>
        @forelse ($this->rows as $inquiry)
            @php $st = $this->rowStatus($inquiry); $chip = $this->answeredClarificationChip($inquiry); @endphp
            <div wire:key="row-{{ $inquiry->id }}" wire:click="openPanel({{ $inquiry->id }})" class="grid grid-cols-[2.4fr_1.3fr_1.5fr] gap-4 px-5 py-4 text-sm border-b border-gray-50 last:border-0 cursor-pointer hover:bg-gray-50 items-center">
                <div class="min-w-0">
                    <div class="font-semibold text-gray-900">{{ $inquiry->title }}</div>
                    @if ($inquiry->status === \App\Enums\InquiryStatus::UnderInvestigation && $inquiry->agency)
                        <div class="text-xs text-gray-400 mt-0.5">{{ __('Assigned to :agency · :date', ['agency' => $inquiry->agency->name, 'date' => $inquiry->reviewed_at?->format('d M Y') ?? '—']) }}</div>
                    @endif
                </div>
                <div class="text-gray-600">{{ $inquiry->category?->value }}</div>
                <div class="flex flex-col items-start gap-1.5">
                    <span class="inline-block min-w-[130px] text-left px-3 py-1 rounded-full text-xs font-bold {{ $st['bg'] }} {{ $st['color'] }}">{{ $st['label'] }}</span>
                    @if ($chip)
                        <span class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 text-[10.5px] font-bold px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>{{ $chip }}
                        </span>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-gray-400">{{ __('No inquiries match your filters.') }}</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $this->rows->links() }}</div>

    @if ($this->panelInquiry)
        <div wire:click="closePanel" class="fixed inset-0 bg-black/35 z-40"></div>
        <div class="fixed top-0 right-0 bottom-0 w-96 bg-white shadow-2xl z-50 flex flex-col">
            <div class="p-5 border-b border-gray-100 flex items-start justify-between gap-3">
                <div class="font-display font-bold text-base text-gray-900">{{ $this->panelInquiry->title }}</div>
                <button wire:click="closePanel" class="text-gray-400 hover:text-brand text-lg leading-none flex-shrink-0">✕</button>
            </div>
            <div class="px-5 py-3 border-b border-gray-100 text-xs text-gray-500">
                {{ $this->panelInquiry->category?->value }} · {{ __('Submitted :date', ['date' => $this->panelInquiry->created_at->format('d M Y')]) }}
            </div>

            @if ($this->panelInquiry->agency)
                <div class="px-5 py-4 border-b border-gray-100">
                    <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide mb-2">{{ __('Agency') }}</div>
                    <div class="w-7 h-7 rounded-full bg-brand text-white text-[10px] font-extrabold flex items-center justify-center">
                        {{ strtoupper(substr($this->panelInquiry->agency->code, 0, 3)) }}
                    </div>
                </div>
            @endif

            @if ($this->panelActiveClarification)
                @php $thread = $this->panelActiveClarification; @endphp
                <div class="px-5 py-3.5 border-b border-gray-100 bg-purple-50/60">
                    <div class="flex items-center justify-between gap-2 mb-1.5">
                        <div class="text-[11px] font-bold text-purple-700 uppercase tracking-wide">{{ __('Clarification Request') }}</div>
                        <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap {{ $thread->status === \App\Enums\ClarificationStatus::Open ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ $thread->status->label() }}
                        </span>
                    </div>
                    <div class="text-xs font-bold text-gray-800">#{{ $thread->id }} · {{ $thread->topic->value }}</div>
                    <div class="text-xs text-gray-500 mt-1 leading-relaxed">{{ \Illuminate\Support\Str::limit($thread->subject, 100) }}</div>
                </div>
            @endif

            <div class="flex border-b border-gray-100 flex-shrink-0">
                <button wire:click="$set('panelTab', 'details')" class="flex-1 text-center py-2.5 text-xs font-bold {{ $panelTab === 'details' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Details') }}</button>
                <button wire:click="$set('panelTab', 'evidence')" class="flex-1 text-center py-2.5 text-xs font-bold {{ $panelTab === 'evidence' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Evidence') }}</button>
                <button wire:click="$set('panelTab', 'activity')" class="flex-1 text-center py-2.5 text-xs font-bold {{ $panelTab === 'activity' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Activity') }}</button>
            </div>

            <div class="flex-1 overflow-auto p-5">
                @if ($panelTab === 'details')
                    <div class="text-sm text-gray-600 leading-relaxed">{{ $this->panelInquiry->description }}</div>
                    @if ($this->panelInquiry->source_url)
                        <div class="text-xs mt-3">{{ __('Source:') }} <a href="{{ $this->panelInquiry->source_url }}" target="_blank" class="text-brand font-semibold hover:underline">{{ $this->panelInquiry->source_url }}</a></div>
                    @endif
                @elseif ($panelTab === 'evidence')
                    <div class="flex flex-col gap-2">
                        @forelse ($this->panelInquiry->evidence as $file)
                            <div class="flex items-center gap-2.5 bg-gray-50 border border-gray-100 rounded-lg px-3.5 py-2.5 text-xs font-semibold text-gray-700">📎 {{ $file->file_name }}</div>
                        @empty
                            <p class="text-sm text-gray-400">{{ __('No evidence files attached.') }}</p>
                        @endforelse
                    </div>
                @else
                    <div class="flex flex-col gap-3.5">
                        @forelse ($this->panelInquiry->activityLogs->sortByDesc('created_at') as $log)
                            <div class="flex gap-2.5">
                                <div class="w-2 h-2 rounded-full bg-brand mt-1.5 flex-shrink-0"></div>
                                <div>
                                    <div class="text-[11px] font-bold text-gray-400">{{ $log->created_at->format('d M Y') }}</div>
                                    <div class="text-sm text-gray-900">{{ str($log->action)->headline() }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">{{ __('No activity yet.') }}</p>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
