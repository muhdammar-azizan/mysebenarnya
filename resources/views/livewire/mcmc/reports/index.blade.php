<?php

use App\Concerns\GeneratesReports;
use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.mcmc')] class extends Component
{
    use GeneratesReports;

    #[Url]
    public string $tab = 'inquiries';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $agencyFilter = 'All';

    #[Url]
    public string $categoryFilter = 'All';

    public function clearDateFilter(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    public function clearAgencyFilters(): void
    {
        $this->agencyFilter = 'All';
        $this->categoryFilter = 'All';
    }

    public function getAgencyOptionsProperty()
    {
        return Agency::orderBy('name')->get();
    }

    public function getCategoryOptionsProperty(): array
    {
        return InquiryCategory::cases();
    }

    protected function monthlySeries(\Closure $queryFactory, string $column = 'created_at'): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        return $months->map(function ($month) use ($queryFactory, $column) {
            $count = $queryFactory()->whereBetween($column, [$month, $month->copy()->endOfMonth()])->count();

            return ['label' => $month->format('M Y'), 'count' => $count];
        })->all();
    }

    protected function inquiriesInRange()
    {
        return Inquiry::query()
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

    public function getInquiryTrendProperty(): array
    {
        return $this->monthlySeries(fn () => Inquiry::query());
    }

    public function getStatusBreakdownProperty(): array
    {
        return collect(InquiryStatus::cases())->map(fn ($case) => [
            'label' => $case->value,
            'count' => (clone $this->inquiriesInRange())->where('status', $case)->count(),
        ])->all();
    }

    public function getCategoryBreakdownProperty()
    {
        return (clone $this->inquiriesInRange())
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();
    }

    public function getAgencyPerformanceProperty()
    {
        $agencyIds = Agency::query()
            ->when($this->agencyFilter !== 'All', fn ($q) => $q->where('id', $this->agencyFilter))
            ->orderBy('name')
            ->pluck('id');

        return $agencyIds->map(function ($agencyId) {
            $agency = Agency::find($agencyId);
            $base = (clone $this->inquiriesInRange())
                ->where('agency_id', $agencyId)
                ->when($this->categoryFilter !== 'All', fn ($q) => $q->where('category', $this->categoryFilter));

            $total = (clone $base)->count();
            $resolved = (clone $base)->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])->count();
            $pending = (clone $base)->where('status', InquiryStatus::UnderInvestigation)->count();
            $delayed = (clone $base)->where('status', InquiryStatus::UnderInvestigation)
                ->where('reviewed_at', '<=', now()->subDays(7))
                ->count();

            $avgResolutionDays = (clone $base)
                ->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
                ->whereNotNull('resolved_at')
                ->whereNotNull('reviewed_at')
                ->get()
                ->avg(fn ($i) => $i->reviewed_at->diffInDays($i->resolved_at));

            $agency->inquiries_count = $total;
            $agency->resolved_count = $resolved;
            $agency->pending_count = $pending;
            $agency->delayed_count = $delayed;
            $agency->avg_resolution_days = $avgResolutionDays ? round($avgResolutionDays, 1) : null;

            return $agency;
        })->sortByDesc('inquiries_count')->values();
    }

    public function getAgencyDistributionChartProperty(): array
    {
        return $this->agencyPerformance->map(fn ($a) => ['label' => $a->name, 'value' => $a->inquiries_count])->all();
    }

    public function getAgencyResolutionChartProperty(): array
    {
        return $this->agencyPerformance->map(function ($a) {
            $rate = $a->inquiries_count > 0 ? round(($a->resolved_count / $a->inquiries_count) * 100) : 0;

            return ['label' => $a->name, 'value' => $rate];
        })->all();
    }

    protected function agencyPeriodLabel(): string
    {
        $parts = [$this->periodLabel()];

        if ($this->agencyFilter !== 'All') {
            $parts[] = 'Agency: '.(Agency::find($this->agencyFilter)?->name ?? '—');
        }

        if ($this->categoryFilter !== 'All') {
            $parts[] = 'Category: '.$this->categoryFilter;
        }

        return implode(' · ', $parts);
    }

    public function getUserTrendProperty(): array
    {
        return $this->monthlySeries(fn () => User::where('role', UserRole::Public));
    }

    protected function usersInRange()
    {
        return User::query()
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo));
    }

    public function getUserStatsProperty(): array
    {
        return [
            'total' => (clone $this->usersInRange())->where('role', UserRole::Public)->count(),
            'verified' => (clone $this->usersInRange())->where('role', UserRole::Public)->whereNotNull('email_verified_at')->count(),
            'mcmc' => (clone $this->usersInRange())->where('role', UserRole::McmcStaff)->count(),
            'agency' => (clone $this->usersInRange())->where('role', UserRole::AgencyStaff)->count(),
        ];
    }

    public function getUsersByAgencyProperty()
    {
        return Agency::withCount(['users' => function ($q) {
            $q->when($this->dateFrom, fn ($qq) => $qq->whereDate('created_at', '>=', $this->dateFrom))
                ->when($this->dateTo, fn ($qq) => $qq->whereDate('created_at', '<=', $this->dateTo));
        }])->orderByDesc('users_count')->get();
    }

    protected function inquiryOverviewSections(): array
    {
        return [[
            'name' => 'Inquiry Overview',
            'kpis' => [
                ['label' => 'Total Inquiries', 'value' => (clone $this->inquiriesInRange())->count()],
            ],
            'tables' => [
                [
                    'heading' => 'By Status',
                    'columns' => ['Status', 'Count'],
                    'rows' => collect($this->statusBreakdown)->map(fn ($r) => [$r['label'], $r['count']])->all(),
                ],
                [
                    'heading' => 'By Category',
                    'columns' => ['Category', 'Count'],
                    'rows' => $this->categoryBreakdown->map(fn ($r) => [$r->category?->value, $r->total])->all(),
                ],
                [
                    'heading' => 'Monthly Trend',
                    'columns' => ['Month', 'Inquiries'],
                    'rows' => collect($this->inquiryTrend)->map(fn ($r) => [$r['label'], $r['count']])->all(),
                    'chart' => collect($this->inquiryTrend)->map(fn ($r) => ['label' => $r['label'], 'value' => $r['count']])->all(),
                ],
            ],
        ]];
    }

    public function exportInquiriesPdf()
    {
        return $this->downloadPdf('SEBENARNYA_Inquiry-Overview', 'Inquiry Overview Report', $this->periodLabel(), $this->inquiryOverviewSections());
    }

    public function exportInquiriesExcel()
    {
        $sections = $this->inquiryOverviewSections()[0]['tables'];

        return $this->downloadExcel('SEBENARNYA_Inquiry-Overview', [
            ['title' => 'By Status', 'headings' => $sections[0]['columns'], 'rows' => $sections[0]['rows']],
            ['title' => 'By Category', 'headings' => $sections[1]['columns'], 'rows' => $sections[1]['rows']],
            ['title' => 'Monthly Trend', 'headings' => $sections[2]['columns'], 'rows' => $sections[2]['rows']],
        ]);
    }

    protected function agencyPerformanceRows(): array
    {
        return $this->agencyPerformance->map(function ($agency) {
            $rate = $agency->inquiries_count > 0 ? round(($agency->resolved_count / $agency->inquiries_count) * 100) : 0;

            return [
                $agency->name,
                $agency->inquiries_count,
                $agency->resolved_count,
                $rate.'%',
                $agency->pending_count,
                $agency->delayed_count,
                $agency->avg_resolution_days !== null ? $agency->avg_resolution_days.' days' : '—',
            ];
        })->all();
    }

    protected function agencyPerformanceColumns(): array
    {
        return ['Agency', 'Assigned', 'Resolved', 'Resolution Rate', 'Pending', 'Delayed (7d+)', 'Avg. Resolution Time'];
    }

    public function exportAgenciesPdf()
    {
        $sections = [[
            'name' => 'Agency Performance',
            'tables' => [[
                'heading' => 'Inquiries Assigned per Agency',
                'columns' => $this->agencyPerformanceColumns(),
                'rows' => $this->agencyPerformanceRows(),
                'chart' => $this->agencyDistributionChart,
            ]],
        ]];

        return $this->downloadPdf('SEBENARNYA_Agency-Performance', 'Agency Performance Report', $this->agencyPeriodLabel(), $sections);
    }

    public function exportAgenciesExcel()
    {
        return $this->downloadExcel('SEBENARNYA_Agency-Performance', [
            ['title' => 'Agency Performance', 'headings' => $this->agencyPerformanceColumns(), 'rows' => $this->agencyPerformanceRows()],
        ]);
    }

    protected function userGrowthSections(): array
    {
        return [[
            'name' => 'User Growth',
            'kpis' => [
                ['label' => 'Total Public Users', 'value' => $this->userStats['total']],
                ['label' => 'Verified', 'value' => $this->userStats['verified']],
                ['label' => 'MCMC Staff', 'value' => $this->userStats['mcmc']],
                ['label' => 'Agency Staff', 'value' => $this->userStats['agency']],
            ],
            'tables' => [
                [
                    'heading' => 'Monthly Registrations',
                    'columns' => ['Month', 'New Registrations'],
                    'rows' => collect($this->userTrend)->map(fn ($r) => [$r['label'], $r['count']])->all(),
                    'chart' => collect($this->userTrend)->map(fn ($r) => ['label' => $r['label'], 'value' => $r['count']])->all(),
                ],
                [
                    'heading' => 'By User Type',
                    'columns' => ['Type', 'Count'],
                    'rows' => [
                        ['Public User', $this->userStats['total']],
                        ['MCMC Staff', $this->userStats['mcmc']],
                        ['Agency Staff', $this->userStats['agency']],
                    ],
                ],
                [
                    'heading' => 'Users by Agency',
                    'columns' => ['Agency', 'Staff Count'],
                    'rows' => $this->usersByAgency->map(fn ($a) => [$a->name, $a->users_count])->all(),
                ],
            ],
        ]];
    }

    public function exportUsersPdf()
    {
        return $this->downloadPdf('SEBENARNYA_User-Growth', 'User Growth Report', $this->periodLabel(), $this->userGrowthSections());
    }

    public function exportUsersExcel()
    {
        $tables = $this->userGrowthSections()[0]['tables'];

        return $this->downloadExcel('SEBENARNYA_User-Growth', [
            ['title' => 'Monthly Registrations', 'headings' => $tables[0]['columns'], 'rows' => $tables[0]['rows']],
            ['title' => 'By User Type', 'headings' => $tables[1]['columns'], 'rows' => $tables[1]['rows']],
            ['title' => 'Users by Agency', 'headings' => $tables[2]['columns'], 'rows' => $tables[2]['rows']],
        ]);
    }
}; ?>

<div>
    <div class="flex items-start justify-between gap-4 mb-1">
        <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Reports & Analytics') }}</h1>
        <div class="flex gap-2 flex-shrink-0">
            <button wire:click="export{{ str($tab)->studly() }}Pdf" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-xs px-3.5 py-2 rounded-lg">📄 {{ __('Export PDF') }}</button>
            <button wire:click="export{{ str($tab)->studly() }}Excel" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-xs px-3.5 py-2 rounded-lg">📊 {{ __('Export Excel') }}</button>
        </div>
    </div>
    <p class="text-gray-500 text-sm mb-6">{{ __('Insights on inquiry trends, agency performance, and user growth.') }}</p>

    <div class="flex border-b border-gray-100 mb-6">
        <button wire:click="$set('tab', 'inquiries')" class="px-5 py-3 text-sm font-bold {{ $tab === 'inquiries' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Inquiry Overview') }}</button>
        <button wire:click="$set('tab', 'agencies')" class="px-5 py-3 text-sm font-bold {{ $tab === 'agencies' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Agency Performance') }}</button>
        <button wire:click="$set('tab', 'users')" class="px-5 py-3 text-sm font-bold {{ $tab === 'users' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('User Growth') }}</button>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-6">
        <span class="text-xs font-semibold text-gray-500">{{ __('Filter totals & tables by date') }}:</span>
        <label class="text-xs text-gray-500">{{ __('From') }}</label>
        <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <label class="text-xs text-gray-500">{{ __('To') }}</label>
        <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        @if ($dateFrom || $dateTo)
            <button wire:click="clearDateFilter" class="text-xs font-semibold text-brand">{{ __('Clear') }}</button>
        @endif
        <span class="text-[11px] text-gray-400">{{ __('(Monthly trend charts always show the rolling last 6 months.)') }}</span>
    </div>

    @if ($tab === 'inquiries')
        <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-6">
            <div class="font-bold text-gray-900 mb-4">{{ __('Monthly Inquiry Trend') }}</div>
            <div class="flex items-end gap-4 h-32">
                @php $max = max(1, collect($this->inquiryTrend)->max('count')); @endphp
                @foreach ($this->inquiryTrend as $point)
                    <div class="flex-1 flex flex-col items-center gap-1.5">
                        <div class="text-xs font-bold text-gray-500">{{ $point['count'] }}</div>
                        <div class="w-full bg-brand rounded-t" style="height: {{ max(4, ($point['count'] / $max) * 90) }}px"></div>
                        <div class="text-[11px] text-gray-400 font-semibold">{{ $point['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
            <div class="bg-white border border-gray-100 rounded-2xl p-6">
                <div class="font-bold text-gray-900 mb-4">{{ __('By Status') }}</div>
                <div class="flex flex-col gap-3">
                    @foreach ($this->statusBreakdown as $row)
                        <div class="flex items-center justify-between text-sm">
                            <x-inquiry-status-badge :status="$row['label']" />
                            <span class="font-bold text-gray-700">{{ $row['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="bg-white border border-gray-100 rounded-2xl p-6">
                <div class="font-bold text-gray-900 mb-4">{{ __('By Category') }}</div>
                <div class="flex flex-col gap-3">
                    @foreach ($this->categoryBreakdown as $row)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">{{ $row->category?->value }}</span>
                            <span class="font-bold text-gray-700">{{ $row->total }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @elseif ($tab === 'agencies')
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <span class="text-xs font-semibold text-gray-500">{{ __('Filter by') }}:</span>
            <select wire:model.live="agencyFilter" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                <option value="All">{{ __('All Agencies') }}</option>
                @foreach ($this->agencyOptions as $a)
                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="categoryFilter" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                <option value="All">{{ __('All Categories') }}</option>
                @foreach ($this->categoryOptions as $case)
                    <option value="{{ $case->value }}">{{ $case->value }}</option>
                @endforeach
            </select>
            @if ($agencyFilter !== 'All' || $categoryFilter !== 'All')
                <button wire:click="clearAgencyFilters" class="text-xs font-semibold text-brand">{{ __('Clear') }}</button>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
            <div class="bg-white border border-gray-100 rounded-2xl p-6">
                <div class="font-bold text-gray-900 mb-4">{{ __('Inquiries Assigned per Agency') }}</div>
                <div class="flex items-end gap-3 h-32 overflow-x-auto">
                    @php $max = max(1, collect($this->agencyDistributionChart)->max('value')); @endphp
                    @forelse ($this->agencyDistributionChart as $point)
                        <div class="flex-1 min-w-[48px] flex flex-col items-center gap-1.5">
                            <div class="text-xs font-bold text-gray-500">{{ $point['value'] }}</div>
                            <div class="w-full bg-brand rounded-t" style="height: {{ max(4, ($point['value'] / $max) * 90) }}px"></div>
                            <div class="text-[10px] text-gray-400 font-semibold text-center leading-tight">{{ $point['label'] }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">{{ __('No data for the selected filters.') }}</p>
                    @endforelse
                </div>
            </div>
            <div class="bg-white border border-gray-100 rounded-2xl p-6">
                <div class="font-bold text-gray-900 mb-4">{{ __('Resolution Rate per Agency') }}</div>
                <div class="flex items-end gap-3 h-32 overflow-x-auto">
                    @php $maxRate = max(1, collect($this->agencyResolutionChart)->max('value')); @endphp
                    @forelse ($this->agencyResolutionChart as $point)
                        <div class="flex-1 min-w-[48px] flex flex-col items-center gap-1.5">
                            <div class="text-xs font-bold text-gray-500">{{ $point['value'] }}%</div>
                            <div class="w-full bg-brand rounded-t" style="height: {{ max(4, ($point['value'] / $maxRate) * 90) }}px"></div>
                            <div class="text-[10px] text-gray-400 font-semibold text-center leading-tight">{{ $point['label'] }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">{{ __('No data for the selected filters.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">
                        <th class="px-5 py-3">{{ __('Agency') }}</th>
                        <th class="px-5 py-3">{{ __('Assigned') }}</th>
                        <th class="px-5 py-3">{{ __('Resolved') }}</th>
                        <th class="px-5 py-3">{{ __('Resolution Rate') }}</th>
                        <th class="px-5 py-3">{{ __('Pending') }}</th>
                        <th class="px-5 py-3">{{ __('Delayed (7d+)') }}</th>
                        <th class="px-5 py-3">{{ __('Avg. Resolution Time') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->agencyPerformance as $agency)
                        @php $rate = $agency->inquiries_count > 0 ? round(($agency->resolved_count / $agency->inquiries_count) * 100) : 0; @endphp
                        <tr class="border-t border-gray-50">
                            <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $agency->name }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $agency->inquiries_count }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $agency->resolved_count }}</td>
                            <td class="px-5 py-3.5">
                                <span class="font-bold {{ $rate >= 70 ? 'text-green-600' : ($rate >= 40 ? 'text-amber-600' : 'text-brand') }}">{{ $rate }}%</span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $agency->pending_count }}</td>
                            <td class="px-5 py-3.5">
                                @if ($agency->delayed_count > 0)
                                    <span class="font-bold text-brand">{{ $agency->delayed_count }}</span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap">{{ $agency->avg_resolution_days !== null ? $agency->avg_resolution_days.' '.__('days') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">{{ __('No agencies match the selected filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
                <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('Total Public Users') }}</div>
                <div class="text-3xl font-extrabold text-gray-900">{{ $this->userStats['total'] }}</div>
            </div>
            <div class="bg-green-50 border border-green-100 rounded-xl p-5">
                <div class="text-xs font-semibold text-green-700 mb-1">{{ __('Verified') }}</div>
                <div class="text-3xl font-extrabold text-green-700">{{ $this->userStats['verified'] }}</div>
            </div>
            <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
                <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('MCMC Staff') }}</div>
                <div class="text-3xl font-extrabold text-gray-900">{{ $this->userStats['mcmc'] }}</div>
            </div>
            <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
                <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('Agency Staff') }}</div>
                <div class="text-3xl font-extrabold text-gray-900">{{ $this->userStats['agency'] }}</div>
            </div>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-6">
            <div class="font-bold text-gray-900 mb-4">{{ __('Monthly Registrations') }}</div>
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
        <div class="bg-white border border-gray-100 rounded-2xl p-6">
            <div class="font-bold text-gray-900 mb-4">{{ __('Users by Agency') }}</div>
            <div class="flex flex-col gap-3">
                @forelse ($this->usersByAgency as $a)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">{{ $a->name }}</span>
                        <span class="font-bold text-gray-700">{{ $a->users_count }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">{{ __('No agency staff registered yet.') }}</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
