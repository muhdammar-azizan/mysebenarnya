<?php

use App\Enums\ClarificationStatus;
use App\Enums\ConsultStatus;
use App\Enums\InquiryStatus;
use App\Models\ClarificationConsult;
use App\Models\ClarificationThread;
use App\Models\Inquiry;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.agency')] class extends Component
{
    #[Url]
    public string $quickFilter = 'all';

    public ?int $panelInquiryId = null;

    public string $panelTab = 'details';

    protected function agencyId(): int
    {
        return Auth::user()->agency_id;
    }

    public function jumpToQuickFilter(string $key): void
    {
        $this->quickFilter = $key;
    }

    public function viewPanel(int $inquiryId): void
    {
        $this->panelInquiryId = $inquiryId;
        $this->panelTab = 'details';
    }

    public function closePanel(): void
    {
        $this->panelInquiryId = null;
    }

    public function getStatsProperty(): array
    {
        $base = Inquiry::where('agency_id', $this->agencyId());

        return [
            'total' => (clone $base)->count(),
            'investigation' => (clone $base)->where('status', InquiryStatus::UnderInvestigation)->count(),
            'resolved_month' => (clone $base)->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
                ->whereMonth('resolved_at', now()->month)->whereYear('resolved_at', now()->year)->count(),
            'awaiting_review' => (clone $base)->where('status', InquiryStatus::UnderInvestigation)->whereNull('jurisdiction_accepted_at')->count(),
        ];
    }

    public function getBreakdownProperty(): array
    {
        $verified = Inquiry::where('agency_id', $this->agencyId())->where('status', InquiryStatus::VerifiedTrue)->count();
        $fake = Inquiry::where('agency_id', $this->agencyId())->where('status', InquiryStatus::IdentifiedFake)->count();
        $rejected = Inquiry::where('agency_id', $this->agencyId())->where('status', InquiryStatus::Rejected)->count();
        $total = max(1, $verified + $fake + $rejected);

        return [
            'total_cases' => $verified + $fake + $rejected,
            'rows' => [
                ['label' => __('Verified True'), 'count' => $verified, 'pct' => (int) round($verified / $total * 100), 'color' => '#1D7A3E'],
                ['label' => __('Identified Fake'), 'count' => $fake, 'pct' => (int) round($fake / $total * 100), 'color' => '#C41230'],
                ['label' => __('Rejected by us'), 'count' => $rejected, 'pct' => (int) round($rejected / $total * 100), 'color' => '#8A8F98'],
            ],
        ];
    }

    public function getDonutGradientProperty(): string
    {
        $cursor = 0;
        $parts = [];

        foreach ($this->breakdown['rows'] as $row) {
            if ($row['pct'] <= 0) {
                continue;
            }
            $from = $cursor;
            $cursor += $row['pct'];
            $parts[] = "{$row['color']} {$from}% {$cursor}%";
        }

        if (empty($parts)) {
            return '#F0F1F3';
        }

        return 'conic-gradient('.implode(', ', $parts).')';
    }

    protected function resolvedInquiries($agencyId)
    {
        return Inquiry::where('agency_id', $agencyId)
            ->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
            ->whereNotNull('resolved_at')
            ->whereNotNull('reviewed_at');
    }

    public function getResolutionComparisonProperty(): ?array
    {
        $mine = $this->resolvedInquiries($this->agencyId())->get();

        if ($mine->isEmpty()) {
            return null;
        }

        $myAvg = round($mine->avg(fn ($i) => $i->reviewed_at->diffInDays($i->resolved_at)), 1);

        $allResolved = Inquiry::whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
            ->whereNotNull('resolved_at')
            ->whereNotNull('reviewed_at')
            ->get();

        $platformAvg = $allResolved->isNotEmpty()
            ? round($allResolved->avg(fn ($i) => $i->reviewed_at->diffInDays($i->resolved_at)), 1)
            : null;

        return [
            'mine' => $myAvg,
            'platform' => $platformAvg,
            'faster' => $platformAvg !== null ? $myAvg < $platformAvg : null,
        ];
    }

    public function getClarifyAlertsProperty(): array
    {
        $agencyId = $this->agencyId();
        $alerts = [];

        $pendingConsults = ClarificationConsult::where('consulted_agency_id', $agencyId)
            ->where('status', ConsultStatus::Pending)
            ->count();

        if ($pendingConsults > 0) {
            $alerts[] = [
                'title' => trans_choice('MCMC requested your advice on :count case owned by another agency|MCMC requested your advice on :count cases owned by other agencies', $pendingConsults, ['count' => $pendingConsults]),
                'sub' => __('Give your advice so MCMC can continue its review.'),
                'cta' => __('Give advice'),
                'bg' => 'bg-teal-50', 'border' => 'border-teal-200', 'iconBg' => 'bg-teal-700', 'titleColor' => 'text-teal-800',
                'url' => route('agency.inquiries.index', ['tab' => 'consult']),
            ];
        }

        $answered = ClarificationThread::whereHas('inquiry', fn ($q) => $q->where('agency_id', $agencyId))
            ->where('status', ClarificationStatus::Answered)
            ->where('unread_by_agency', true)
            ->count();

        if ($answered > 0) {
            $alerts[] = [
                'title' => trans_choice('MCMC responded to :count clarification request|MCMC responded to :count clarification requests', $answered, ['count' => $answered]),
                'sub' => __('Review and continue your investigation.'),
                'cta' => __('Review replies'),
                'bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'iconBg' => 'bg-blue-700', 'titleColor' => 'text-blue-800',
                'url' => route('agency.inquiries.index'),
            ];
        }

        $waiting = ClarificationThread::whereHas('inquiry', fn ($q) => $q->where('agency_id', $agencyId))
            ->where('status', ClarificationStatus::Open)
            ->count();

        if ($waiting > 0) {
            $alerts[] = [
                'title' => trans_choice(':count inquiry awaiting MCMC clarification|:count inquiries awaiting MCMC clarification', $waiting, ['count' => $waiting]),
                'sub' => __('These cases stay with your agency until MCMC responds.'),
                'cta' => __('View'),
                'bg' => 'bg-purple-50', 'border' => 'border-purple-200', 'iconBg' => 'bg-purple-700', 'titleColor' => 'text-purple-800',
                'url' => route('agency.inquiries.index'),
            ];
        }

        return $alerts;
    }

    public function getQuickFilterLabelProperty(): string
    {
        return match ($this->quickFilter) {
            'investigation' => __('Under Investigation'),
            'resolved' => __('Resolved This Month'),
            'awaiting' => __('Awaiting Your Review'),
            default => __('All Assigned'),
        };
    }

    public function getRecentRowsProperty()
    {
        $query = Inquiry::where('agency_id', $this->agencyId())->with('activityLogs');

        match ($this->quickFilter) {
            'investigation' => $query->where('status', InquiryStatus::UnderInvestigation),
            'resolved' => $query->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
                ->whereMonth('resolved_at', now()->month)->whereYear('resolved_at', now()->year),
            'awaiting' => $query->where('status', InquiryStatus::UnderInvestigation)->whereNull('jurisdiction_accepted_at'),
            default => null,
        };

        return $query->latest()->limit(4)->get();
    }

    public function getViewFullListParamsProperty(): array
    {
        return match ($this->quickFilter) {
            'investigation' => ['status' => InquiryStatus::UnderInvestigation->value],
            'resolved' => ['status' => 'resolved', 'dateFrom' => now()->startOfMonth()->format('Y-m-d')],
            'awaiting' => ['status' => 'awaiting'],
            default => [],
        };
    }

    public function notesFromMcmc(Inquiry $inquiry): ?string
    {
        return $inquiry->activityLogs
            ->whereIn('action', ['assigned', 'reassigned'])
            ->sortByDesc('created_at')
            ->first()
            ?->notes;
    }

    public function getPanelInquiryProperty(): ?Inquiry
    {
        return $this->panelInquiryId
            ? Inquiry::with(['evidence', 'activityLogs'])->find($this->panelInquiryId)
            : null;
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('Agency Dashboard') }}</h1>
    <p class="text-gray-500 text-sm mb-5">{{ __('Manage inquiries assigned to :agency for verification.', ['agency' => auth()->user()->agency->name]) }}</p>

    @if (count($this->clarifyAlerts))
        <div class="flex flex-col gap-2 mb-5">
            @foreach ($this->clarifyAlerts as $alert)
                <a href="{{ $alert['url'] }}" wire:navigate class="flex items-center gap-3.5 {{ $alert['bg'] }} border {{ $alert['border'] }} rounded-lg px-4 py-3 hover:shadow-md transition-shadow">
                    <div class="w-9 h-9 rounded-full {{ $alert['iconBg'] }} text-white flex items-center justify-center flex-shrink-0">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 5h16v11H9l-5 4V5z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold {{ $alert['titleColor'] }}">{{ $alert['title'] }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ $alert['sub'] }}</div>
                    </div>
                    <div class="text-sm font-bold {{ $alert['titleColor'] }} whitespace-nowrap">{{ $alert['cta'] }} →</div>
                </a>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-4 gap-4 mb-6">
        <button type="button" wire:click="jumpToQuickFilter('all')" class="text-left bg-blue-50 border border-blue-100 rounded-xl p-5 hover:shadow-sm {{ $quickFilter === 'all' ? 'ring-2 ring-blue-300' : '' }}">
            <div class="text-xs font-semibold text-blue-700 mb-1">{{ __('Total Assigned') }}</div>
            <div class="text-3xl font-extrabold text-blue-700">{{ $this->stats['total'] }}</div>
        </button>
        <button type="button" wire:click="jumpToQuickFilter('investigation')" class="text-left bg-amber-50 border border-amber-100 rounded-xl p-5 hover:shadow-sm {{ $quickFilter === 'investigation' ? 'ring-2 ring-amber-300' : '' }}">
            <div class="text-xs font-semibold text-amber-700 mb-1">{{ __('Under Investigation') }}</div>
            <div class="text-3xl font-extrabold text-amber-700">{{ $this->stats['investigation'] }}</div>
        </button>
        <button type="button" wire:click="jumpToQuickFilter('resolved')" class="text-left bg-green-50 border border-green-100 rounded-xl p-5 hover:shadow-sm {{ $quickFilter === 'resolved' ? 'ring-2 ring-green-300' : '' }}">
            <div class="text-xs font-semibold text-green-700 mb-1">{{ __('Resolved This Month') }}</div>
            <div class="text-3xl font-extrabold text-green-700">{{ $this->stats['resolved_month'] }}</div>
        </button>
        <button type="button" wire:click="jumpToQuickFilter('awaiting')" class="text-left bg-brand-light border border-red-100 rounded-xl p-5 hover:shadow-sm {{ $quickFilter === 'awaiting' ? 'ring-2 ring-red-300' : '' }}">
            <div class="text-xs font-semibold text-brand mb-1">{{ __('Awaiting Your Review') }}</div>
            <div class="text-3xl font-extrabold text-brand">{{ $this->stats['awaiting_review'] }}</div>
        </button>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-6">
        <div class="flex items-center gap-2 mb-4">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 19V10M10 19V4M16 19v-7M22 19H2" stroke="#C41230" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <div class="font-bold text-gray-900">{{ __('Performance Summary') }}</div>
        </div>
        <div class="grid grid-cols-[150px_1fr] gap-8 items-center">
            <div class="relative w-[150px] h-[150px] rounded-full flex-shrink-0" style="background: {{ $this->donutGradient }};">
                <div class="absolute inset-0 m-auto w-[92px] h-[92px] rounded-full bg-white flex flex-col items-center justify-center">
                    <div class="font-display font-extrabold text-2xl text-gray-900 leading-none">{{ $this->breakdown['total_cases'] }}</div>
                    <div class="text-[10.5px] text-gray-400 font-semibold mt-0.5">{{ __('Total Cases') }}</div>
                </div>
            </div>
            <div>
                <div class="font-bold text-xs text-gray-600 mb-3">{{ __('Your Resolution Breakdown') }}</div>
                <div class="flex flex-col gap-2.5 mb-4">
                    @foreach ($this->breakdown['rows'] as $row)
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm flex-shrink-0" style="background: {{ $row['color'] }};"></span>{{ $row['label'] }}</div>
                            <div class="text-gray-600 font-semibold">{{ $row['count'] }} {{ __('cases') }} <span class="text-gray-400 font-medium">&middot; {{ $row['pct'] }}%</span></div>
                        </div>
                    @endforeach
                </div>
                <div class="h-px bg-gray-100 mb-3.5"></div>
                <div class="flex gap-5 flex-wrap items-end">
                    <div>
                        <div class="text-[11.5px] text-gray-400 font-semibold mb-0.5">{{ __('AVG. RESOLUTION TIME') }}</div>
                        <div class="font-display font-extrabold text-lg text-gray-900">
                            {{ $this->resolutionComparison ? $this->resolutionComparison['mine'].' '.__('days') : '—' }}
                        </div>
                    </div>
                    @if ($this->resolutionComparison && $this->resolutionComparison['faster'] !== null)
                        <div class="inline-flex items-center gap-1.5 {{ $this->resolutionComparison['faster'] ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }} text-xs font-bold px-3 py-1.5 rounded-full">
                            @if ($this->resolutionComparison['faster'])
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12l7-7 7 7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ __('Faster than platform average') }}
                            @else
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M12 19V5M5 12l7 7 7-7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ __('Slower than platform average') }}
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <div class="font-bold text-gray-900 mb-0.5">{{ __('Recently Assigned Inquiries') }}</div>
                <div class="text-xs text-gray-400">{{ __('Agency Dashboard') }} <span class="mx-1">&rsaquo;</span> {{ $this->quickFilterLabel }}</div>
            </div>
            <a href="{{ route('agency.inquiries.index', $this->viewFullListParams) }}" wire:navigate class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold text-xs px-3.5 py-2 rounded-lg whitespace-nowrap">{{ __('View Full List →') }}</a>
        </div>
        <div class="grid grid-cols-[1.8fr_1fr_1fr_1.2fr_1.6fr_1fr] gap-2 px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide border-b border-gray-100">
            <div>{{ __('Title') }}</div><div>{{ __('Category') }}</div><div>{{ __('Date Assigned') }}</div><div>{{ __('Status') }}</div><div>{{ __('Notes from MCMC') }}</div><div></div>
        </div>
        @forelse ($this->recentRows as $inquiry)
            @php $note = $this->notesFromMcmc($inquiry); @endphp
            <div wire:key="dash-row-{{ $inquiry->id }}" wire:click="viewPanel({{ $inquiry->id }})" class="grid grid-cols-[1.8fr_1fr_1fr_1.2fr_1.6fr_1fr] gap-2 px-5 py-3.5 text-sm border-b border-gray-50 last:border-0 items-center cursor-pointer hover:bg-gray-50">
                <div class="font-semibold text-gray-900 truncate">{{ $inquiry->title }}</div>
                <div class="text-gray-600 text-xs truncate">{{ $inquiry->category?->value }}</div>
                <div class="text-gray-400 text-xs whitespace-nowrap">{{ $inquiry->reviewed_at?->format('d M Y') ?? $inquiry->updated_at->format('d M Y') }}</div>
                <div><x-inquiry-status-badge :status="$inquiry->status" /></div>
                <div class="text-gray-500 text-xs truncate" title="{{ $note }}">{{ $note ?: '—' }}</div>
                <div>
                    <a href="{{ route('agency.inquiries.show', $inquiry) }}" wire:navigate wire:click.stop class="inline-block w-full text-center bg-brand hover:bg-brand-dark text-white font-semibold text-xs px-3 py-1.5 rounded-md">{{ __('Update Status') }}</a>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-gray-400 text-sm">{{ __('No inquiries in this category.') }}</div>
        @endforelse
    </div>

    @if ($this->panelInquiry)
        <div wire:click="closePanel" class="fixed inset-0 bg-black/35 z-40"></div>
        <div class="fixed top-0 right-0 bottom-0 w-96 bg-white shadow-2xl z-50 flex flex-col">
            <div class="p-5 border-b border-gray-100 flex items-start justify-between gap-3">
                <div class="font-display font-bold text-base text-gray-900">{{ $this->panelInquiry->title }}</div>
                <button wire:click="closePanel" class="text-gray-400 hover:text-brand text-lg leading-none flex-shrink-0">✕</button>
            </div>
            <div class="px-5 py-3 border-b border-gray-100 text-xs text-gray-500">
                {{ $this->panelInquiry->category?->value }} · <x-inquiry-status-badge :status="$this->panelInquiry->status" />
            </div>
            <div class="flex border-b border-gray-100 flex-shrink-0">
                <button wire:click="$set('panelTab', 'details')" class="flex-1 text-center py-2.5 text-xs font-bold {{ $panelTab === 'details' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Details') }}</button>
                <button wire:click="$set('panelTab', 'evidence')" class="flex-1 text-center py-2.5 text-xs font-bold {{ $panelTab === 'evidence' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Evidence') }}</button>
                <button wire:click="$set('panelTab', 'activity')" class="flex-1 text-center py-2.5 text-xs font-bold {{ $panelTab === 'activity' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Activity Log') }}</button>
            </div>
            <div class="flex-1 overflow-auto p-5">
                @if ($panelTab === 'details')
                    <div class="text-sm text-gray-600 leading-relaxed">{{ $this->panelInquiry->description }}</div>
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
            <div class="p-4 border-t border-gray-100">
                <a href="{{ route('agency.inquiries.show', $this->panelInquiry) }}" wire:navigate class="block w-full text-center bg-brand hover:bg-brand-dark text-white font-bold text-sm py-2.5 rounded-lg">{{ __('Open Full Inquiry') }}</a>
            </div>
        </div>
    @endif
</div>
