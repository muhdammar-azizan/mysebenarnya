<?php

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
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('Reports & Analytics') }}</h1>
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
