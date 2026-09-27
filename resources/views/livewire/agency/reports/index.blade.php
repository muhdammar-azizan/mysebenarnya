<?php

use App\Concerns\GeneratesReports;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.agency')] class extends Component
{
    use GeneratesReports;

    #[Url]
    public string $selectedMonth = '';

    public bool $exportOpen = false;

    public bool $exportJustCompleted = false;

    public string $exportFormat = 'pdf';

    public array $exportSections = ['summary' => true];

    public array $exportHistory = [];

    public function openExport(): void
    {
        $this->exportOpen = true;
        $this->exportJustCompleted = false;
    }

    public function closeExport(): void
    {
        $this->exportOpen = false;
        $this->exportJustCompleted = false;
    }

    public function exportAnother(): void
    {
        $this->exportJustCompleted = false;
    }

    public function toggleSection(string $key): void
    {
        $this->exportSections[$key] = ! ($this->exportSections[$key] ?? false);
    }

    public function toggleAllSections(): void
    {
        $allOn = ! in_array(false, $this->exportSections, true);

        foreach ($this->exportSections as $key => $value) {
            $this->exportSections[$key] = ! $allOn;
        }
    }

    public function getExportSectionListProperty(): array
    {
        return [
            ['key' => 'summary', 'label' => __('Performance Summary'), 'desc' => __('Key metrics and monthly resolution trend'), 'count' => $this->totalResolved],
        ];
    }

    public function getExportFiltersLabelProperty(): string
    {
        return $this->periodLabel();
    }

    public function getExportFilenameProperty(): string
    {
        return 'SEBENARNYA_'.str(Auth::user()->agency->code)->slug().'-Report_'.now()->format('Ymd').'.'.($this->exportFormat === 'excel' ? 'xlsx' : 'pdf');
    }

    protected function agencyId(): int
    {
        return Auth::user()->agency_id;
    }

    protected function rangeStart()
    {
        return now()->subMonths(5)->startOfMonth();
    }

    protected function rangeEnd()
    {
        return now()->endOfMonth();
    }

    public function selectMonth(string $key): void
    {
        $this->selectedMonth = $this->selectedMonth === $key ? '' : $key;
    }

    public function getMonthlyBucketsProperty(): array
    {
        return collect(range(5, 0))->map(function ($i) {
            $month = now()->subMonths($i)->startOfMonth();
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $count = Inquiry::where('agency_id', $this->agencyId())
                ->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
                ->whereBetween('resolved_at', [$start, $end])
                ->count();

            return [
                'key' => $month->format('Y-m'),
                'label' => $month->format('M'),
                'start' => $start,
                'end' => $end,
                'count' => $count,
            ];
        })->all();
    }

    public function getTotalResolvedProperty(): int
    {
        return array_sum(array_column($this->monthlyBuckets, 'count'));
    }

    public function getAvgResolutionDaysProperty(): ?float
    {
        $resolved = Inquiry::where('agency_id', $this->agencyId())
            ->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
            ->whereBetween('resolved_at', [$this->rangeStart(), $this->rangeEnd()])
            ->whereNotNull('reviewed_at')
            ->get();

        return $resolved->isNotEmpty()
            ? round($resolved->avg(fn ($i) => $i->reviewed_at->diffInDays($i->resolved_at)), 1)
            : null;
    }

    public function getAccuracyRateProperty(): ?int
    {
        $accepted = InquiryActivityLog::where('action', 'jurisdiction_accepted')
            ->whereHas('user', fn ($q) => $q->where('agency_id', $this->agencyId()))
            ->count();

        $rejected = InquiryActivityLog::where('action', 'jurisdiction_rejected')
            ->whereHas('user', fn ($q) => $q->where('agency_id', $this->agencyId()))
            ->count();

        $decided = $accepted + $rejected;

        return $decided > 0 ? (int) round(($accepted / $decided) * 100) : null;
    }

    public function getSelectedMonthLabelProperty(): ?string
    {
        $bucket = collect($this->monthlyBuckets)->firstWhere('key', $this->selectedMonth);

        return $bucket ? $bucket['start']->format('F Y') : null;
    }

    public function getSelectedMonthInquiriesProperty()
    {
        $bucket = collect($this->monthlyBuckets)->firstWhere('key', $this->selectedMonth);

        if (! $bucket) {
            return collect();
        }

        return Inquiry::where('agency_id', $this->agencyId())
            ->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
            ->whereBetween('resolved_at', [$bucket['start'], $bucket['end']])
            ->latest('resolved_at')
            ->get();
    }

    public function getCategoryBreakdownProperty()
    {
        $rows = Inquiry::where('agency_id', $this->agencyId())
            ->whereBetween('created_at', [$this->rangeStart(), $this->rangeEnd()])
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $max = max(1, $rows->max('total') ?? 1);

        return $rows->map(fn ($r) => [
            'label' => $r->category?->value,
            'count' => $r->total,
            'pct' => (int) round(($r->total / $max) * 100),
        ]);
    }

    protected function periodLabel(): string
    {
        return __('Last 6 Months (:from to :to)', ['from' => $this->rangeStart()->format('M Y'), 'to' => $this->rangeEnd()->format('M Y')]);
    }

    protected function reportSections(): array
    {
        return [[
            'name' => Auth::user()->agency->name.' — Performance Report',
            'kpis' => [
                ['label' => 'Total Resolved (6mo)', 'value' => $this->totalResolved],
                ['label' => 'Avg. Resolution Time', 'value' => $this->avgResolutionDays !== null ? $this->avgResolutionDays.' days' : '—'],
                ['label' => 'Accuracy Rate', 'value' => $this->accuracyRate !== null ? $this->accuracyRate.'%' : '—'],
                ['label' => 'Months Covered', 'value' => count($this->monthlyBuckets)],
            ],
            'tables' => [
                [
                    'heading' => 'Monthly Resolution Trend',
                    'columns' => ['Month', 'Resolved', 'Verified True', 'Identified Fake'],
                    'rows' => collect($this->monthlyBuckets)->map(function ($b) {
                        $verified = Inquiry::where('agency_id', $this->agencyId())->where('status', InquiryStatus::VerifiedTrue)->whereBetween('resolved_at', [$b['start'], $b['end']])->count();
                        $fake = Inquiry::where('agency_id', $this->agencyId())->where('status', InquiryStatus::IdentifiedFake)->whereBetween('resolved_at', [$b['start'], $b['end']])->count();

                        return [$b['start']->format('M Y'), $b['count'], $verified, $fake];
                    })->all(),
                ],
                [
                    'heading' => 'Breakdown by Category',
                    'columns' => ['Category', 'Count'],
                    'rows' => $this->categoryBreakdown->map(fn ($r) => [$r['label'], $r['count']])->all(),
                ],
            ],
        ]];
    }

    public function generateExport()
    {
        if (empty(array_filter($this->exportSections))) {
            $this->addError('exportSections', __('Select at least one section to export.'));

            return;
        }

        $filenameBase = 'SEBENARNYA_'.str(Auth::user()->agency->code)->slug().'-Report';

        if ($this->exportFormat === 'excel') {
            $tables = $this->reportSections()[0]['tables'];
            $response = $this->downloadExcel($filenameBase, [
                ['title' => 'Monthly Resolution Trend', 'headings' => $tables[0]['columns'], 'rows' => $tables[0]['rows']],
                ['title' => 'Breakdown by Category', 'headings' => $tables[1]['columns'], 'rows' => $tables[1]['rows']],
            ]);
        } else {
            $response = $this->downloadPdf($filenameBase, Auth::user()->agency->name.' Performance Report', $this->periodLabel(), $this->reportSections());
        }

        $this->exportHistory[] = ['filename' => $this->exportFilename, 'meta' => now()->format('d M, H:i')];
        $this->exportJustCompleted = true;

        return $response;
    }
}; ?>

<div>
    <div class="flex items-start justify-between gap-4 mb-1">
        <div>
            <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Reports') }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ __('Monthly resolution performance for :agency.', ['agency' => auth()->user()->agency->name]) }}</p>
        </div>
        <div class="flex-shrink-0">
            <x-export-modal
                :export-open="$exportOpen"
                :export-just-completed="$exportJustCompleted"
                :export-format="$exportFormat"
                :export-sections="$exportSections"
                :export-history="$exportHistory"
                :export-filters-label="$this->exportFiltersLabel"
                :export-filename="$this->exportFilename"
                :sections="$this->exportSectionList"
                :prepared-by="auth()->user()->name.' ('.auth()->user()->agency->name.')'"
                :intro-text="__('Export your agency performance report using the filters currently selected.')"
            />
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4 my-6">
        <div class="bg-blue-50 border border-blue-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('Total Resolved (6mo)') }}</div>
            <div class="text-3xl font-extrabold text-blue-700">{{ $this->totalResolved }}</div>
        </div>
        <div class="bg-green-50 border border-green-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('Avg. Resolution Time') }}</div>
            <div class="text-3xl font-extrabold text-green-700">{{ $this->avgResolutionDays !== null ? $this->avgResolutionDays.' '.__('days') : '—' }}</div>
        </div>
        <div class="bg-amber-50 border border-amber-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('Accuracy Rate') }}</div>
            <div class="text-3xl font-extrabold text-amber-700">{{ $this->accuracyRate !== null ? $this->accuracyRate.'%' : '—' }}</div>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-6">
        <div class="font-bold text-gray-900 mb-4">{{ __('Monthly Resolution Trend') }}</div>
        <div class="flex items-end justify-center gap-5" style="height: 150px;">
            @php $max = max(1, collect($this->monthlyBuckets)->max('count')); @endphp
            @foreach ($this->monthlyBuckets as $bucket)
                <button type="button" wire:click="selectMonth('{{ $bucket['key'] }}')" class="w-11 flex flex-col items-center gap-1.5 h-full justify-end">
                    <div class="text-xs font-bold {{ $this->selectedMonth === $bucket['key'] ? 'text-brand' : 'text-gray-500' }}">{{ $bucket['count'] }}</div>
                    <div class="w-full max-w-[34px] rounded-t transition-transform {{ $this->selectedMonth === $bucket['key'] ? 'bg-brand' : 'bg-brand/60 hover:bg-brand' }}" style="height: {{ max(4, ($bucket['count'] / $max) * 100) }}px"></div>
                    <div class="text-xs {{ $this->selectedMonth === $bucket['key'] ? 'font-bold text-brand' : 'text-gray-400' }}">{{ $bucket['label'] }}</div>
                </button>
            @endforeach
        </div>
    </div>

    @if ($this->selectedMonth && $this->selectedMonthLabel)
        <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div class="font-bold text-gray-900">{{ __('Resolved in :month', ['month' => $this->selectedMonthLabel]) }}</div>
                <button wire:click="selectMonth('{{ $this->selectedMonth }}')" class="text-xs font-semibold text-gray-400 hover:text-brand">{{ __('Close ✕') }}</button>
            </div>
            <div class="flex flex-col gap-2 max-h-72 overflow-y-auto">
                @forelse ($this->selectedMonthInquiries as $inquiry)
                    <a href="{{ route('agency.inquiries.show', $inquiry) }}" wire:navigate class="flex items-center justify-between gap-3 px-3.5 py-3 border border-gray-100 rounded-lg hover:bg-gray-50 hover:border-red-100">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-gray-900 truncate">{{ $inquiry->title }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $inquiry->category?->value }} &middot; {{ $inquiry->resolved_at?->format('d M Y') }}</div>
                        </div>
                        <x-inquiry-status-badge :status="$inquiry->status" />
                    </a>
                @empty
                    <p class="text-sm text-gray-400">{{ __('No cases resolved this month.') }}</p>
                @endforelse
            </div>
        </div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl p-6">
        <div class="font-bold text-gray-900 mb-4">{{ __('Breakdown by Category') }}</div>
        <div class="flex flex-col gap-3">
            @forelse ($this->categoryBreakdown as $row)
                <div>
                    <div class="flex items-center justify-between text-sm mb-1.5">
                        <span class="font-semibold text-gray-700">{{ $row['label'] }}</span>
                        <span class="text-gray-500">{{ $row['count'] }} {{ __('cases') }}</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="bg-brand h-full rounded-full" style="width: {{ $row['pct'] }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400">{{ __('No records in the last 6 months.') }}</p>
            @endforelse
        </div>
    </div>
</div>
