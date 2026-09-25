<?php

use App\Concerns\GeneratesReports;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.agency')] class extends Component
{
    use GeneratesReports;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public function clearDateFilter(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    protected function inquiriesInRange()
    {
        return Inquiry::where('agency_id', Auth::user()->agency_id)
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo));
    }

    protected function periodLabel(): string
    {
        if ($this->dateFrom || $this->dateTo) {
            return ($this->dateFrom ?: 'earliest').' to '.($this->dateTo ?: 'now');
        }

        return 'All records';
    }

    public function getSummaryProperty(): array
    {
        $base = $this->inquiriesInRange();

        $avgResolutionDays = (clone $base)
            ->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
            ->whereNotNull('resolved_at')
            ->whereNotNull('reviewed_at')
            ->get()
            ->avg(fn ($i) => $i->reviewed_at->diffInDays($i->resolved_at));

        return [
            'total' => (clone $base)->count(),
            'verified' => (clone $base)->where('status', InquiryStatus::VerifiedTrue)->count(),
            'fake' => (clone $base)->where('status', InquiryStatus::IdentifiedFake)->count(),
            'rejected' => (clone $base)->where('status', InquiryStatus::Rejected)->count(),
            'pending' => (clone $base)->where('status', InquiryStatus::UnderInvestigation)->count(),
            'delayed' => (clone $base)->where('status', InquiryStatus::UnderInvestigation)->where('reviewed_at', '<=', now()->subDays(7))->count(),
            'avg_resolution_days' => $avgResolutionDays ? round($avgResolutionDays, 1) : null,
        ];
    }

    public function getCategoryBreakdownProperty()
    {
        return (clone $this->inquiriesInRange())
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();
    }

    public function getResolvedRecordsProperty()
    {
        return (clone $this->inquiriesInRange())
            ->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
            ->latest('resolved_at')
            ->limit(10)
            ->get();
    }

    protected function reportSections(): array
    {
        return [[
            'name' => Auth::user()->agency->name.' — Performance Report',
            'kpis' => [
                ['label' => 'Case Records', 'value' => $this->summary['total']],
                ['label' => 'Verified True', 'value' => $this->summary['verified']],
                ['label' => 'Identified Fake', 'value' => $this->summary['fake']],
                ['label' => 'Rejected by Us', 'value' => $this->summary['rejected']],
                ['label' => 'Pending', 'value' => $this->summary['pending']],
                ['label' => 'Delayed (7d+)', 'value' => $this->summary['delayed']],
                ['label' => 'Avg. Resolution Time', 'value' => $this->summary['avg_resolution_days'] !== null ? $this->summary['avg_resolution_days'].' days' : '—'],
            ],
            'tables' => [
                [
                    'heading' => 'Category Breakdown',
                    'columns' => ['Category', 'Count'],
                    'rows' => $this->categoryBreakdown->map(fn ($r) => [$r->category?->value, $r->total])->all(),
                ],
                [
                    'heading' => 'Recently Resolved',
                    'columns' => ['Title', 'Status', 'Resolved On'],
                    'rows' => $this->resolvedRecords->map(fn ($i) => [$i->title, $i->status->value, $i->resolved_at?->format('d M Y')])->all(),
                ],
            ],
        ]];
    }

    public function exportPdf()
    {
        return $this->downloadPdf('SEBENARNYA_'.str(Auth::user()->agency->code)->slug().'-Report', Auth::user()->agency->name.' Performance Report', $this->periodLabel(), $this->reportSections());
    }

    public function exportExcel()
    {
        $tables = $this->reportSections()[0]['tables'];

        return $this->downloadExcel('SEBENARNYA_'.str(Auth::user()->agency->code)->slug().'-Report', [
            ['title' => 'Category Breakdown', 'headings' => $tables[0]['columns'], 'rows' => $tables[0]['rows']],
            ['title' => 'Recently Resolved', 'headings' => $tables[1]['columns'], 'rows' => $tables[1]['rows']],
        ]);
    }
}; ?>

<div>
    <div class="flex items-start justify-between gap-4 mb-1">
        <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Reports') }}</h1>
        <div class="flex gap-2 flex-shrink-0">
            <button wire:click="exportPdf" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-xs px-3.5 py-2 rounded-lg">📄 {{ __('Export PDF') }}</button>
            <button wire:click="exportExcel" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-xs px-3.5 py-2 rounded-lg">📊 {{ __('Export Excel') }}</button>
        </div>
    </div>
    <p class="text-gray-500 text-sm mb-4">{{ __("Your agency's performance summary.") }}</p>

    <div class="flex flex-wrap items-center gap-2 mb-6">
        <span class="text-xs font-semibold text-gray-500">{{ __('Filter by date') }}:</span>
        <label class="text-xs text-gray-500">{{ __('From') }}</label>
        <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <label class="text-xs text-gray-500">{{ __('To') }}</label>
        <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        @if ($dateFrom || $dateTo)
            <button wire:click="clearDateFilter" class="text-xs font-semibold text-brand">{{ __('Clear') }}</button>
        @endif
    </div>

    <div class="grid grid-cols-4 gap-4 mb-4">
        <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('Case Records') }}</div>
            <div class="text-3xl font-extrabold text-gray-900">{{ $this->summary['total'] }}</div>
        </div>
        <div class="bg-green-50 border border-green-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-green-700 mb-1">{{ __('Verified True') }}</div>
            <div class="text-3xl font-extrabold text-green-700">{{ $this->summary['verified'] }}</div>
        </div>
        <div class="bg-brand-light border border-red-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-brand mb-1">{{ __('Identified Fake') }}</div>
            <div class="text-3xl font-extrabold text-brand">{{ $this->summary['fake'] }}</div>
        </div>
        <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('Rejected by Us') }}</div>
            <div class="text-3xl font-extrabold text-gray-700">{{ $this->summary['rejected'] }}</div>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-7">
        <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('Pending') }}</div>
            <div class="text-3xl font-extrabold text-gray-900">{{ $this->summary['pending'] }}</div>
        </div>
        <div class="bg-amber-50 border border-amber-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-amber-700 mb-1">{{ __('Delayed (7d+)') }}</div>
            <div class="text-3xl font-extrabold text-amber-700">{{ $this->summary['delayed'] }}</div>
        </div>
        <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('Avg. Resolution Time') }}</div>
            <div class="text-3xl font-extrabold text-gray-900">{{ $this->summary['avg_resolution_days'] !== null ? $this->summary['avg_resolution_days'].' '.__('days') : '—' }}</div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6">
        <div class="bg-white border border-gray-100 rounded-2xl p-6">
            <div class="font-bold text-gray-900 mb-4">{{ __('Category Breakdown') }}</div>
            <div class="flex flex-col gap-3">
                @foreach ($this->categoryBreakdown as $row)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">{{ $row->category?->value }}</span>
                        <span class="font-bold text-gray-700">{{ $row->total }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl p-6">
            <div class="font-bold text-gray-900 mb-4">{{ __('Recently Resolved') }}</div>
            <div class="flex flex-col gap-3">
                @forelse ($this->resolvedRecords as $inquiry)
                    <div class="flex items-center justify-between gap-3 pb-3 border-b border-gray-50 last:border-0 last:pb-0">
                        <span class="text-sm font-semibold text-gray-900 truncate">{{ $inquiry->title }}</span>
                        <x-inquiry-status-badge :status="$inquiry->status" />
                    </div>
                @empty
                    <p class="text-sm text-gray-400">{{ __('No resolved records yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
