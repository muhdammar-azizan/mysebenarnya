<?php

use App\Concerns\GeneratesReports;
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

    protected function monthlySeries(\Closure $queryFactory, string $column = 'created_at'): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        return $months->map(function ($month) use ($queryFactory, $column) {
            $count = $queryFactory()->whereBetween($column, [$month, $month->copy()->endOfMonth()])->count();

            return ['label' => $month->format('M Y'), 'count' => $count];
        })->all();
    }

    public function getInquiryTrendProperty(): array
    {
        return $this->monthlySeries(fn () => Inquiry::query());
    }

    public function getStatusBreakdownProperty(): array
    {
        return collect(InquiryStatus::cases())->map(fn ($case) => [
            'label' => $case->value,
            'count' => Inquiry::where('status', $case)->count(),
        ])->all();
    }

    public function getCategoryBreakdownProperty()
    {
        return Inquiry::selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();
    }

    public function getAgencyPerformanceProperty()
    {
        return Agency::withCount([
            'inquiries',
            'inquiries as resolved_count' => fn ($q) => $q->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake]),
        ])->orderByDesc('inquiries_count')->get();
    }

    public function getUserTrendProperty(): array
    {
        return $this->monthlySeries(fn () => User::where('role', UserRole::Public));
    }

    public function getUserStatsProperty(): array
    {
        return [
            'total' => User::where('role', UserRole::Public)->count(),
            'verified' => User::where('role', UserRole::Public)->whereNotNull('email_verified_at')->count(),
        ];
    }

    protected function inquiryOverviewSections(): array
    {
        return [[
            'name' => 'Inquiry Overview',
            'kpis' => [
                ['label' => 'Total Inquiries', 'value' => Inquiry::count()],
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
                ],
            ],
        ]];
    }

    public function exportInquiriesPdf()
    {
        return $this->downloadPdf('SEBENARNYA_Inquiry-Overview', 'Inquiry Overview Report', 'Last 6 months', $this->inquiryOverviewSections());
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

            return [$agency->name, $agency->inquiries_count, $agency->resolved_count, $rate.'%'];
        })->all();
    }

    public function exportAgenciesPdf()
    {
        $sections = [[
            'name' => 'Agency Performance',
            'tables' => [[
                'columns' => ['Agency', 'Assigned', 'Resolved', 'Resolution Rate'],
                'rows' => $this->agencyPerformanceRows(),
            ]],
        ]];

        return $this->downloadPdf('SEBENARNYA_Agency-Performance', 'Agency Performance Report', 'All records', $sections);
    }

    public function exportAgenciesExcel()
    {
        return $this->downloadExcel('SEBENARNYA_Agency-Performance', [
            ['title' => 'Agency Performance', 'headings' => ['Agency', 'Assigned', 'Resolved', 'Resolution Rate'], 'rows' => $this->agencyPerformanceRows()],
        ]);
    }

    protected function userGrowthSections(): array
    {
        return [[
            'name' => 'User Growth',
            'kpis' => [
                ['label' => 'Total Public Users', 'value' => $this->userStats['total']],
                ['label' => 'Verified', 'value' => $this->userStats['verified']],
            ],
            'tables' => [[
                'heading' => 'Monthly Registrations',
                'columns' => ['Month', 'New Registrations'],
                'rows' => collect($this->userTrend)->map(fn ($r) => [$r['label'], $r['count']])->all(),
            ]],
        ]];
    }

    public function exportUsersPdf()
    {
        return $this->downloadPdf('SEBENARNYA_User-Growth', 'User Growth Report', 'Last 6 months', $this->userGrowthSections());
    }

    public function exportUsersExcel()
    {
        $table = $this->userGrowthSections()[0]['tables'][0];

        return $this->downloadExcel('SEBENARNYA_User-Growth', [
            ['title' => 'Monthly Registrations', 'headings' => $table['columns'], 'rows' => $table['rows']],
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
        <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">
                        <th class="px-5 py-3">{{ __('Agency') }}</th>
                        <th class="px-5 py-3">{{ __('Assigned') }}</th>
                        <th class="px-5 py-3">{{ __('Resolved') }}</th>
                        <th class="px-5 py-3">{{ __('Resolution Rate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->agencyPerformance as $agency)
                        @php $rate = $agency->inquiries_count > 0 ? round(($agency->resolved_count / $agency->inquiries_count) * 100) : 0; @endphp
                        <tr class="border-t border-gray-50">
                            <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $agency->name }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $agency->inquiries_count }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $agency->resolved_count }}</td>
                            <td class="px-5 py-3.5">
                                <span class="font-bold {{ $rate >= 70 ? 'text-green-600' : ($rate >= 40 ? 'text-amber-600' : 'text-brand') }}">{{ $rate }}%</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
                <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('Total Public Users') }}</div>
                <div class="text-3xl font-extrabold text-gray-900">{{ $this->userStats['total'] }}</div>
            </div>
            <div class="bg-green-50 border border-green-100 rounded-xl p-5">
                <div class="text-xs font-semibold text-green-700 mb-1">{{ __('Verified') }}</div>
                <div class="text-3xl font-extrabold text-green-700">{{ $this->userStats['verified'] }}</div>
            </div>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-6">
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
    @endif
</div>
