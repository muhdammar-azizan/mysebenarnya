<?php

use App\Concerns\GeneratesReports;
use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.mcmc')] class extends Component
{
    use GeneratesReports;

    /**
     * An agency whose average resolution time exceeds this is flagged in the
     * "Needs Attention" banner. There is no configurable SLA yet, so this is
     * a fixed, reasonable constant rather than an invented number.
     */
    protected const SLOW_THRESHOLD_DAYS = 5.0;

    protected const CATEGORY_COLORS = [
        'Health & Medical Claims' => '#C41230',
        'Financial Scams & Banking' => '#1D5FBF',
        'Electoral & Political Content' => '#7B4FBF',
        'Consumer Rights & Pricing' => '#C4670A',
        'Criminal & Fraud Referrals' => '#8A8F98',
        'Disaster & Emergency Aid' => '#1D7A3E',
        'Technology & Digital Safety' => '#0F9B8E',
        'Other' => '#4B4F58',
    ];

    #[Url]
    public string $tab = 'user';

    #[Url]
    public string $dateRange = '6m';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $userTypeFilter = 'all';

    #[Url]
    public string $inquiryCategoryFilter = 'All';

    #[Url]
    public string $agencyFilter = 'All';

    public bool $exportOpen = false;

    public bool $exportJustCompleted = false;

    public string $exportFormat = 'pdf';

    public array $exportSections = ['user' => true, 'inquiry' => true, 'agency' => true];

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
            ['key' => 'user', 'label' => __('User Reports'), 'desc' => __('Registrations, cumulative growth, user records'), 'count' => $this->userStatCards[0]['value']],
            ['key' => 'inquiry', 'label' => __('Inquiry Reports'), 'desc' => __('Monthly status breakdown, categories, inquiry records'), 'count' => $this->inquiryStatCards[0]['value']],
            ['key' => 'agency', 'label' => __('Agency Performance Reports'), 'desc' => __('Resolution time, workload, rejection rate'), 'count' => $this->agencyPerformance->count()],
        ];
    }

    public function getExportFiltersLabelProperty(): string
    {
        return $this->periodLabel();
    }

    public function getExportFilenameProperty(): string
    {
        return 'SEBENARNYA_MCMC-Report_'.now()->format('Ymd').'.'.($this->exportFormat === 'excel' ? 'xlsx' : 'pdf');
    }

    public function clearCustomDates(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    public function getAgencyOptionsProperty()
    {
        return Agency::orderBy('name')->get();
    }

    public function getCategoryOptionsProperty(): array
    {
        return InquiryCategory::cases();
    }

    protected function rangeDates(): array
    {
        return match ($this->dateRange) {
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            '3m' => [now()->subMonths(2)->startOfMonth(), now()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfMonth()],
            'custom' => [
                $this->dateFrom ? Carbon::parse($this->dateFrom)->startOfDay() : now()->subMonths(5)->startOfMonth(),
                $this->dateTo ? Carbon::parse($this->dateTo)->endOfDay() : now()->endOfMonth(),
            ],
            default => [now()->subMonths(5)->startOfMonth(), now()->endOfMonth()],
        };
    }

    protected function monthlyBuckets(): Collection
    {
        [$start, $end] = $this->rangeDates();

        $buckets = collect();
        $cursor = $start->copy()->startOfMonth();
        $endMonth = $end->copy()->startOfMonth();

        while ($cursor->lte($endMonth)) {
            $buckets->push([
                'start' => $cursor->copy()->startOfMonth(),
                'end' => $cursor->copy()->endOfMonth(),
                'label' => $cursor->format('M Y'),
            ]);
            $cursor->addMonth();
        }

        return $buckets;
    }

    protected function periodLabel(): string
    {
        return match ($this->dateRange) {
            'month' => __('This Month (:month)', ['month' => now()->format('M Y')]),
            '3m' => __('Last 3 Months'),
            'year' => __('This Year (:year)', ['year' => now()->year]),
            'custom' => __('Custom Range (:from to :to)', ['from' => $this->dateFrom ?: 'earliest', 'to' => $this->dateTo ?: 'now']),
            default => __('Last 6 Months'),
        };
    }

    protected function userTypeFilterRole(): ?UserRole
    {
        return match ($this->userTypeFilter) {
            'public' => UserRole::Public,
            'mcmc_staff' => UserRole::McmcStaff,
            'agency_staff' => UserRole::AgencyStaff,
            default => null,
        };
    }

    public function getUserTypeOptionsProperty(): array
    {
        return [
            'all' => __('All User Types'),
            'public' => __('Public Users'),
            'mcmc_staff' => __('MCMC Staff'),
            'agency_staff' => __('Agency Staff'),
        ];
    }

    public function getUserDrilldownEnabledProperty(): bool
    {
        return in_array($this->userTypeFilter, ['all', 'public'], true);
    }

    public function getUserStatCardsProperty(): array
    {
        [$start, $end] = $this->rangeDates();

        return [
            ['label' => __('Total Registered Users'), 'value' => User::where('role', UserRole::Public)->count()],
            ['label' => __('New Users in Period'), 'value' => User::where('role', UserRole::Public)->whereBetween('created_at', [$start, $end])->count()],
            ['label' => __('Verified Email Users'), 'value' => User::where('role', UserRole::Public)->whereNotNull('email_verified_at')->count()],
            ['label' => __('Active (Last 30 Days)'), 'value' => User::where('role', UserRole::Public)->where('last_active_at', '>=', now()->subDays(30))->count()],
        ];
    }

    public function getUserTrendProperty(): array
    {
        $role = $this->userTypeFilterRole();

        return $this->monthlyBuckets()->map(function ($b) use ($role) {
            $count = User::query()
                ->when($role, fn ($q) => $q->where('role', $role))
                ->whereBetween('created_at', [$b['start'], $b['end']])
                ->count();

            return [...$b, 'count' => $count];
        })->all();
    }

    public function getUserTableProperty(): array
    {
        $role = $this->userTypeFilterRole();

        return $this->monthlyBuckets()->map(function ($b) use ($role) {
            $new = User::query()->when($role, fn ($q) => $q->where('role', $role))->whereBetween('created_at', [$b['start'], $b['end']])->count();
            $cumulative = User::query()->when($role, fn ($q) => $q->where('role', $role))->where('created_at', '<=', $b['end'])->count();

            return [...$b, 'new' => $new, 'cumulative' => $cumulative];
        })->all();
    }

    public function getInquiryStatCardsProperty(): array
    {
        [$start, $end] = $this->rangeDates();
        $base = fn () => Inquiry::whereBetween('created_at', [$start, $end]);

        return [
            ['label' => __('Total Inquiries'), 'value' => $base()->count()],
            ['label' => __('Verified as True'), 'value' => $base()->where('status', InquiryStatus::VerifiedTrue)->count()],
            ['label' => __('Identified as Fake'), 'value' => $base()->where('status', InquiryStatus::IdentifiedFake)->count()],
            ['label' => __('Pending / Under Investigation'), 'value' => $base()->whereIn('status', [InquiryStatus::Submitted, InquiryStatus::UnderInvestigation])->count()],
        ];
    }

    public function getInquiryStackedBarsProperty(): array
    {
        $buckets = $this->monthlyBuckets()->map(function ($b) {
            $counts = [
                'sub' => Inquiry::whereBetween('created_at', [$b['start'], $b['end']])->where('status', InquiryStatus::Submitted)->count(),
                'ui' => Inquiry::whereBetween('created_at', [$b['start'], $b['end']])->where('status', InquiryStatus::UnderInvestigation)->count(),
                'tru' => Inquiry::whereBetween('created_at', [$b['start'], $b['end']])->where('status', InquiryStatus::VerifiedTrue)->count(),
                'fake' => Inquiry::whereBetween('created_at', [$b['start'], $b['end']])->where('status', InquiryStatus::IdentifiedFake)->count(),
            ];

            return [...$b, ...$counts, 'total' => array_sum($counts)];
        });

        $maxTotal = max(1, $buckets->max('total'));

        return $buckets->map(function ($b) use ($maxTotal) {
            $totalHeight = $b['total'] > 0 ? max(6, round(($b['total'] / $maxTotal) * 140)) : 0;
            $scale = $b['total'] > 0 ? $totalHeight / $b['total'] : 0;

            return [
                ...$b,
                'totalHeight' => $totalHeight,
                'subH' => round($b['sub'] * $scale),
                'uiH' => round($b['ui'] * $scale),
                'trueH' => round($b['tru'] * $scale),
                'fakeH' => round($b['fake'] * $scale),
            ];
        })->all();
    }

    public function getCategoryDonutProperty(): array
    {
        [$start, $end] = $this->rangeDates();

        $counts = Inquiry::whereBetween('created_at', [$start, $end])
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $grandTotal = max(1, $counts->sum());

        return collect(InquiryCategory::cases())
            ->map(fn ($case) => [
                'label' => $case->value,
                'count' => $counts[$case->value] ?? 0,
                'pct' => (int) round((($counts[$case->value] ?? 0) / $grandTotal) * 100),
                'color' => self::CATEGORY_COLORS[$case->value] ?? '#8A8F98',
            ])
            ->filter(fn ($c) => $c['count'] > 0)
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    public function getDonutGradientProperty(): string
    {
        $slices = $this->categoryDonut;
        $cursor = 0;
        $parts = [];

        foreach ($slices as $slice) {
            $from = $cursor;
            $cursor += $slice['pct'];
            $parts[] = "{$slice['color']} {$from}% {$cursor}%";
        }

        if (empty($parts)) {
            return '#F0F1F3';
        }

        return 'conic-gradient('.implode(', ', $parts).')';
    }

    public function getInquiryTableProperty(): array
    {
        return $this->monthlyBuckets()->map(function ($b) {
            $base = fn () => Inquiry::whereBetween('created_at', [$b['start'], $b['end']]);

            return [
                ...$b,
                'total' => $base()->count(),
                'verified' => $base()->where('status', InquiryStatus::VerifiedTrue)->count(),
                'fake' => $base()->where('status', InquiryStatus::IdentifiedFake)->count(),
            ];
        })->all();
    }

    protected function agencyRejectionRate(Agency $agency): ?int
    {
        $rejected = InquiryActivityLog::where('action', 'jurisdiction_rejected')->whereHas('user', fn ($q) => $q->where('agency_id', $agency->id))->count();
        $accepted = InquiryActivityLog::where('action', 'jurisdiction_accepted')->whereHas('user', fn ($q) => $q->where('agency_id', $agency->id))->count();
        $decided = $rejected + $accepted;

        return $decided > 0 ? (int) round(($rejected / $decided) * 100) : null;
    }

    public function getAgencyPerformanceProperty()
    {
        return Agency::query()
            ->when($this->agencyFilter !== 'All', fn ($q) => $q->where('id', $this->agencyFilter))
            ->orderBy('name')
            ->get()
            ->map(function ($agency) {
                $assigned = Inquiry::where('agency_id', $agency->id)->count();
                $resolved = Inquiry::where('agency_id', $agency->id)->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])->count();
                $pending = Inquiry::where('agency_id', $agency->id)->where('status', InquiryStatus::UnderInvestigation)->count();

                $avgDays = Inquiry::where('agency_id', $agency->id)
                    ->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
                    ->whereNotNull('resolved_at')
                    ->whereNotNull('reviewed_at')
                    ->get()
                    ->avg(fn ($i) => $i->reviewed_at->diffInDays($i->resolved_at));

                $agency->assigned_count = $assigned;
                $agency->resolved_count = $resolved;
                $agency->pending_count = $pending;
                $agency->avg_resolution_days = $avgDays ? round($avgDays, 1) : null;
                $agency->rejection_rate = $this->agencyRejectionRate($agency);

                return $agency;
            });
    }

    public function getFastestAgencyProperty()
    {
        return $this->agencyPerformance->filter(fn ($a) => $a->avg_resolution_days !== null)->sortBy('avg_resolution_days')->first();
    }

    public function getSlowestAgencyProperty()
    {
        $slowest = $this->agencyPerformance->filter(fn ($a) => $a->avg_resolution_days !== null)->sortByDesc('avg_resolution_days')->first();

        return ($slowest && $slowest->avg_resolution_days > self::SLOW_THRESHOLD_DAYS) ? $slowest : null;
    }

    public function getResolutionBarsProperty(): array
    {
        $timed = $this->agencyPerformance->filter(fn ($a) => $a->avg_resolution_days !== null);
        $max = max(1, $timed->max('avg_resolution_days') ?? 1);

        return $timed->map(fn ($a) => [
            'name' => $a->name,
            'avgTime' => $a->avg_resolution_days.' '.__('days'),
            'barWidth' => round(($a->avg_resolution_days / $max) * 100).'%',
        ])->all();
    }

    public function getAgencyStatCardsProperty(): array
    {
        $timed = $this->agencyPerformance->filter(fn ($a) => $a->avg_resolution_days !== null);
        $ratedAgencies = $this->agencyPerformance->filter(fn ($a) => $a->rejection_rate !== null);

        return [
            ['label' => __('Agencies in Report'), 'value' => $this->agencyPerformance->count()],
            ['label' => __('Total Resolved'), 'value' => $this->agencyPerformance->sum('resolved_count')],
            ['label' => __('Avg. Resolution Time'), 'value' => $timed->isNotEmpty() ? round($timed->avg('avg_resolution_days'), 1).' '.__('days') : '—'],
            ['label' => __('Avg. Rejection Rate'), 'value' => $ratedAgencies->isNotEmpty() ? round($ratedAgencies->avg('rejection_rate')).'%' : '—'],
        ];
    }

    protected function inquiryOverviewSections(): array
    {
        return [[
            'name' => 'Inquiry Reports',
            'kpis' => $this->inquiryStatCards,
            'tables' => [
                [
                    'heading' => 'Monthly Status Breakdown',
                    'columns' => ['Month', 'Total Received', 'Verified True', 'Identified Fake'],
                    'rows' => collect($this->inquiryTable)->map(fn ($r) => [$r['label'], $r['total'], $r['verified'], $r['fake']])->all(),
                ],
                [
                    'heading' => 'Category Distribution',
                    'columns' => ['Category', 'Share of Inquiries (%)'],
                    'rows' => collect($this->categoryDonut)->map(fn ($c) => [$c['label'], $c['pct']])->all(),
                ],
            ],
        ]];
    }

    protected function agencyPerformanceRows(): array
    {
        return $this->agencyPerformance->map(fn ($a) => [
            $a->name,
            $a->assigned_count,
            $a->resolved_count,
            $a->pending_count,
            $a->avg_resolution_days !== null ? $a->avg_resolution_days.' days' : '—',
            $a->rejection_rate !== null ? $a->rejection_rate.'%' : '—',
        ])->all();
    }

    protected function agencyPerformanceColumns(): array
    {
        return ['Agency', 'Assigned', 'Resolved', 'Pending', 'Avg. Resolution Time', 'Rejection Rate'];
    }

    protected function agencyPerformanceSection(): array
    {
        return [
            'name' => 'Agency Performance Reports',
            'kpis' => $this->agencyStatCards,
            'tables' => [[
                'heading' => 'Agency Performance Summary',
                'columns' => $this->agencyPerformanceColumns(),
                'rows' => $this->agencyPerformanceRows(),
            ]],
        ];
    }

    protected function userGrowthSections(): array
    {
        return [[
            'name' => 'User Reports',
            'kpis' => $this->userStatCards,
            'tables' => [
                [
                    'heading' => 'Monthly Registrations — '.$this->userTypeOptions[$this->userTypeFilter],
                    'columns' => ['Month', 'New Registrations', 'Cumulative Users'],
                    'rows' => collect($this->userTable)->map(fn ($r) => [$r['label'], $r['new'], $r['cumulative']])->all(),
                ],
            ],
        ]];
    }

    public function generateExport()
    {
        $sections = [];

        if ($this->exportSections['user'] ?? false) {
            $sections[] = $this->userGrowthSections()[0];
        }
        if ($this->exportSections['inquiry'] ?? false) {
            $sections[] = $this->inquiryOverviewSections()[0];
        }
        if ($this->exportSections['agency'] ?? false) {
            $sections[] = $this->agencyPerformanceSection();
        }

        if (empty($sections)) {
            $this->addError('exportSections', __('Select at least one section to export.'));

            return;
        }

        if ($this->exportFormat === 'excel') {
            $sheets = [];
            foreach ($sections as $section) {
                foreach ($section['tables'] as $table) {
                    $sheets[] = ['title' => $table['heading'], 'headings' => $table['columns'], 'rows' => $table['rows']];
                }
            }
            $response = $this->downloadExcel('SEBENARNYA_MCMC-Report', $sheets);
        } else {
            $response = $this->downloadPdf('SEBENARNYA_MCMC-Report', 'MCMC Reports & Analytics', $this->periodLabel(), $sections);
        }

        $this->exportHistory[] = ['filename' => $this->exportFilename, 'meta' => now()->format('d M, H:i')];
        $this->exportJustCompleted = true;

        return $response;
    }
}; ?>

<div>
    <div class="flex items-start justify-between gap-4 mb-1">
        <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Reports & Analytics') }}</h1>
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
                :show-period="true"
                period-property="dateRange"
                :period-options="['month' => __('This Month'), '3m' => __('Last 3 Months'), '6m' => __('Last 6 Months'), 'year' => __('This Year')]"
                :prepared-by="auth()->user()->name.' (MCMC Staff)'"
                :intro-text="__('Export MCMC reports using the sections and period currently selected.')"
            />
        </div>
    </div>
    <p class="text-gray-500 text-sm mb-6">{{ __('Generate insights on user activity, inquiry trends, and agency performance.') }}</p>

    <div class="flex border-b border-gray-100 mb-6">
        <button wire:click="$set('tab', 'user')" class="px-5 py-3 text-sm font-bold {{ $tab === 'user' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('User Reports') }}</button>
        <button wire:click="$set('tab', 'inquiry')" class="px-5 py-3 text-sm font-bold {{ $tab === 'inquiry' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Inquiry Reports') }}</button>
        <button wire:click="$set('tab', 'agency')" class="px-5 py-3 text-sm font-bold {{ $tab === 'agency' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Agency Performance Reports') }}</button>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-6">
        <select wire:model.live="dateRange" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="month">{{ __('This Month') }}</option>
            <option value="3m">{{ __('Last 3 Months') }}</option>
            <option value="6m">{{ __('Last 6 Months') }}</option>
            <option value="year">{{ __('This Year') }}</option>
            <option value="custom">{{ __('Custom Range') }}</option>
        </select>

        @if ($dateRange === 'custom')
            <label class="text-xs text-gray-500">{{ __('From') }}</label>
            <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
            <label class="text-xs text-gray-500">{{ __('To') }}</label>
            <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        @endif

        @if ($tab === 'user')
            <select wire:model.live="userTypeFilter" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                @foreach ($this->userTypeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        @elseif ($tab === 'inquiry')
            <select wire:model.live="inquiryCategoryFilter" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                <option value="All">{{ __('All Categories') }}</option>
                @foreach ($this->categoryOptions as $case)
                    <option value="{{ $case->value }}">{{ $case->value }}</option>
                @endforeach
            </select>
        @else
            <select wire:model.live="agencyFilter" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                <option value="All">{{ __('All Agencies') }}</option>
                @foreach ($this->agencyOptions as $a)
                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    @if ($tab === 'user')
        <div class="grid grid-cols-4 gap-4 mb-6">
            @foreach ($this->userStatCards as $card)
                <div class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm">
                    <div class="text-xs font-semibold text-gray-500 mb-2">{{ $card['label'] }}</div>
                    <div class="font-display font-extrabold text-2xl text-gray-900">{{ number_format($card['value']) }}</div>
                </div>
            @endforeach
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-6">
            <div class="font-bold text-gray-900 mb-4">{{ __('User Registrations Over Time') }}</div>
            <div class="flex items-end gap-4 h-32">
                @php $max = max(1, collect($this->userTrend)->max('count')); @endphp
                @foreach ($this->userTrend as $point)
                    <div class="flex-1 flex flex-col items-center gap-1.5">
                        <div class="text-xs font-bold text-gray-500">{{ $point['count'] }}</div>
                        <div class="w-full bg-brand rounded-t" style="height: {{ max(4, ($point['count'] / $max) * 90) }}px"></div>
                        <div class="text-[11px] text-gray-400 font-semibold">{{ $point['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                <div class="grid grid-cols-3 gap-2 text-xs font-bold text-gray-500 uppercase tracking-wide flex-1">
                    <div>{{ __('Month') }}</div><div>{{ __('New Registrations') }}</div><div>{{ __('Total Cumulative Users') }}</div>
                </div>
                @if ($this->userDrilldownEnabled)
                    <a href="{{ route('mcmc.users.index', ['dateFrom' => $this->rangeDates()[0]->format('Y-m-d'), 'dateTo' => $this->rangeDates()[1]->format('Y-m-d')]) }}" wire:navigate
                        class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold text-xs px-3.5 py-2 rounded-lg whitespace-nowrap">{{ __('View User List →') }}</a>
                @endif
            </div>
            @foreach ($this->userTable as $row)
                @if ($this->userDrilldownEnabled)
                    <a href="{{ route('mcmc.users.index', ['dateFrom' => $row['start']->format('Y-m-d'), 'dateTo' => $row['end']->format('Y-m-d')]) }}" wire:navigate
                        class="grid grid-cols-3 gap-2 px-5 py-3 text-sm border-b border-gray-50 last:border-0 hover:bg-gray-50">
                        <div class="font-semibold text-gray-900">{{ $row['label'] }}</div><div class="text-gray-600">{{ $row['new'] }}</div><div class="text-gray-600">{{ $row['cumulative'] }}</div>
                    </a>
                @else
                    <div class="grid grid-cols-3 gap-2 px-5 py-3 text-sm border-b border-gray-50 last:border-0">
                        <div class="font-semibold text-gray-900">{{ $row['label'] }}</div><div class="text-gray-600">{{ $row['new'] }}</div><div class="text-gray-600">{{ $row['cumulative'] }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    @elseif ($tab === 'inquiry')
        <div class="grid grid-cols-4 gap-4 mb-6">
            @foreach ($this->inquiryStatCards as $card)
                <div class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm">
                    <div class="text-xs font-semibold text-gray-500 mb-2">{{ $card['label'] }}</div>
                    <div class="font-display font-extrabold text-2xl text-gray-900">{{ number_format($card['value']) }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-[1.6fr_1fr] gap-4 mb-6 items-stretch">
            <div class="bg-white border border-gray-100 rounded-2xl p-6">
                <div class="font-bold text-gray-900 mb-4">{{ __('Monthly Inquiry Statistics') }}</div>
                <div class="flex items-end justify-center gap-5" style="height: 150px;">
                    @foreach ($this->inquiryStackedBars as $m)
                        <div class="w-11 flex flex-col items-center gap-1.5 h-full justify-end">
                            <div class="w-full max-w-[30px] flex flex-col-reverse rounded-t overflow-hidden" style="height: {{ $m['totalHeight'] }}px;" title="{{ $m['label'] }}: Submitted {{ $m['sub'] }}, Under Investigation {{ $m['ui'] }}, Verified True {{ $m['tru'] }}, Identified Fake {{ $m['fake'] }}">
                                <div style="height: {{ $m['subH'] }}px; background: #8A8F98;"></div>
                                <div style="height: {{ $m['uiH'] }}px; background: #C4670A;"></div>
                                <div style="height: {{ $m['trueH'] }}px; background: #1D7A3E;"></div>
                                <div style="height: {{ $m['fakeH'] }}px; background: #C41230;"></div>
                            </div>
                            <div class="text-[11px] text-gray-400">{{ $m['label'] }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="flex gap-4 mt-3.5 text-[11px] text-gray-600 flex-wrap justify-center">
                    <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-sm" style="background:#8A8F98;"></div>{{ __('Submitted') }}</div>
                    <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-sm" style="background:#C4670A;"></div>{{ __('Under Investigation') }}</div>
                    <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-sm" style="background:#1D7A3E;"></div>{{ __('Verified True') }}</div>
                    <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-sm" style="background:#C41230;"></div>{{ __('Identified Fake') }}</div>
                </div>
            </div>

            <div class="bg-white border border-gray-100 rounded-2xl p-6">
                <div class="font-bold text-gray-900 mb-4">{{ __('Inquiries by Category') }}</div>
                <div class="w-[150px] h-[150px] rounded-full mx-auto mb-4" style="background: {{ $this->donutGradient }};"></div>
                <div class="flex flex-col gap-1.5">
                    @forelse ($this->categoryDonut as $c)
                        <div class="flex items-center justify-between text-xs text-gray-600">
                            <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-sm flex-shrink-0" style="background: {{ $c['color'] }};"></div>{{ $c['label'] }}</div>
                            <div class="font-semibold">{{ $c['pct'] }}%</div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">{{ __('No inquiries in this period.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
            <div class="grid grid-cols-4 gap-2 px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide border-b border-gray-100">
                <div>{{ __('Month') }}</div><div>{{ __('Total Received') }}</div><div>{{ __('Verified True') }}</div><div>{{ __('Identified Fake') }}</div>
            </div>
            @foreach ($this->inquiryTable as $row)
                <a href="{{ route('mcmc.inquiries.index', array_filter(['dateFrom' => $row['start']->format('Y-m-d'), 'dateTo' => $row['end']->format('Y-m-d'), 'category' => $inquiryCategoryFilter !== 'All' ? $inquiryCategoryFilter : null])) }}" wire:navigate
                    class="grid grid-cols-4 gap-2 px-5 py-3 text-sm border-b border-gray-50 last:border-0 hover:bg-gray-50">
                    <div class="font-semibold text-gray-900">{{ $row['label'] }}</div><div class="text-gray-600">{{ $row['total'] }}</div><div class="text-green-700">{{ $row['verified'] }}</div><div class="text-brand">{{ $row['fake'] }}</div>
                </a>
            @endforeach
        </div>
    @else
        <div class="grid grid-cols-4 gap-4 mb-6">
            @foreach ($this->agencyStatCards as $card)
                <div class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm">
                    <div class="text-xs font-semibold text-gray-500 mb-2">{{ $card['label'] }}</div>
                    <div class="font-display font-extrabold text-2xl text-gray-900">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-2 gap-4 mb-6">
            @if ($this->fastestAgency)
                <div class="bg-green-50 border border-green-200 rounded-xl px-5 py-4 text-sm text-green-800">
                    <strong>{{ __('Fastest:') }}</strong> {{ $this->fastestAgency->name }} — {{ $this->fastestAgency->avg_resolution_days }} {{ __('days avg') }}
                </div>
            @endif
            @if ($this->slowestAgency)
                <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 text-sm text-amber-800">
                    <strong>{{ __('Needs Attention:') }}</strong> {{ $this->slowestAgency->name }} — {{ $this->slowestAgency->avg_resolution_days }} {{ __('days avg, above target threshold') }}
                </div>
            @endif
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-6">
            <div class="font-bold text-gray-900 mb-4">{{ __('Resolution Time by Agency') }}</div>
            <div class="flex flex-col gap-3">
                @forelse ($this->resolutionBars as $bar)
                    <div class="flex items-center gap-3">
                        <div class="w-44 flex-shrink-0 text-xs text-gray-600">{{ $bar['name'] }}</div>
                        <div class="flex-1 bg-gray-100 rounded h-4 relative">
                            <div class="h-full bg-brand rounded" style="width: {{ $bar['barWidth'] }};"></div>
                        </div>
                        <div class="w-16 flex-shrink-0 text-xs font-semibold text-right">{{ $bar['avgTime'] }}</div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">{{ __('No resolved inquiries yet to measure resolution time.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">
                        <th class="px-5 py-3">{{ __('Agency Name') }}</th>
                        <th class="px-5 py-3">{{ __('Assigned') }}</th>
                        <th class="px-5 py-3">{{ __('Resolved') }}</th>
                        <th class="px-5 py-3">{{ __('Pending') }}</th>
                        <th class="px-5 py-3">{{ __('Avg. Resolution') }}</th>
                        <th class="px-5 py-3">{{ __('Rejection Rate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->agencyPerformance as $agency)
                        <tr class="border-t border-gray-50">
                            <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $agency->name }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $agency->assigned_count }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $agency->resolved_count }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $agency->pending_count }}</td>
                            <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap">{{ $agency->avg_resolution_days !== null ? $agency->avg_resolution_days.' '.__('days') : '—' }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $agency->rejection_rate !== null ? $agency->rejection_rate.'%' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">{{ __('No agencies match the selected filter.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
