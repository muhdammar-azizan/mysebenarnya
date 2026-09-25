<?php

use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.mcmc')] class extends Component
{
    public function getStatsProperty(): array
    {
        return [
            'total' => Inquiry::count(),
            'pending' => Inquiry::where('status', InquiryStatus::Submitted)->count(),
            'investigation' => Inquiry::where('status', InquiryStatus::UnderInvestigation)->count(),
            'resolved' => Inquiry::whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])->count(),
            'reassign' => Inquiry::where('status', InquiryStatus::Rejected)->count(),
            'agencies' => Agency::count(),
        ];
    }

    public function getTrendProperty(): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        return $months->map(function ($month) {
            $count = Inquiry::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count();

            return ['label' => $month->format('M'), 'count' => $count];
        })->all();
    }

    public function getRecentActionsProperty()
    {
        return InquiryActivityLog::with(['inquiry', 'user'])
            ->whereHas('user', fn ($q) => $q->where('role', 'mcmc_staff'))
            ->latest()
            ->limit(8)
            ->get();
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-6">{{ __('MCMC Dashboard') }}</h1>

    <div class="grid grid-cols-4 gap-4 mb-7">
        <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('Total Inquiries') }}</div>
            <div class="text-3xl font-extrabold text-gray-900">{{ $this->stats['total'] }}</div>
        </div>
        <div class="bg-amber-50 border border-amber-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-amber-700 mb-1">{{ __('Pending Triage') }}</div>
            <div class="text-3xl font-extrabold text-amber-700">{{ $this->stats['pending'] }}</div>
        </div>
        <div class="bg-blue-50 border border-blue-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-blue-700 mb-1">{{ __('Under Investigation') }}</div>
            <div class="text-3xl font-extrabold text-blue-700">{{ $this->stats['investigation'] }}</div>
        </div>
        <div class="bg-green-50 border border-green-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-green-700 mb-1">{{ __('Resolved') }}</div>
            <div class="text-3xl font-extrabold text-green-700">{{ $this->stats['resolved'] }}</div>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-7">
        <a href="{{ route('mcmc.triage.index') }}" wire:navigate class="bg-amber-50 border border-amber-100 rounded-xl p-5 hover:shadow-sm">
            <div class="text-2xl font-extrabold text-amber-700">{{ $this->stats['pending'] }}</div>
            <div class="text-sm font-bold text-gray-700 mt-1">{{ __('Pending Triage') }}</div>
        </a>
        <a href="{{ route('mcmc.triage.index', ['tab' => 'reassign']) }}" wire:navigate class="bg-blue-50 border border-blue-100 rounded-xl p-5 hover:shadow-sm">
            <div class="text-2xl font-extrabold text-blue-700">{{ $this->stats['reassign'] }}</div>
            <div class="text-sm font-bold text-gray-700 mt-1">{{ __('Needs Reassignment') }}</div>
        </a>
        <a href="{{ route('mcmc.agencies.index') }}" wire:navigate class="bg-green-50 border border-green-100 rounded-xl p-5 hover:shadow-sm">
            <div class="text-2xl font-extrabold text-green-700">{{ $this->stats['agencies'] }}</div>
            <div class="text-sm font-bold text-gray-700 mt-1">{{ __('Registered Agencies') }}</div>
        </a>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-6 mb-7">
        <div class="font-bold text-gray-900 mb-4">{{ __('Monthly Trend') }}</div>
        <div class="flex items-end gap-4 h-32">
            @php $max = max(1, collect($this->trend)->max('count')); @endphp
            @foreach ($this->trend as $point)
                <div class="flex-1 flex flex-col items-center gap-1.5">
                    <div class="text-xs font-bold text-gray-500">{{ $point['count'] }}</div>
                    <div class="w-full bg-brand rounded-t" style="height: {{ max(4, ($point['count'] / $max) * 90) }}px"></div>
                    <div class="text-[11px] text-gray-400 font-semibold">{{ $point['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-6">
        <div class="font-bold text-gray-900 mb-4">{{ __('Recent Actions') }}</div>
        <div class="flex flex-col gap-4">
            @forelse ($this->recentActions as $log)
                <div class="flex items-center justify-between gap-3 pb-4 border-b border-gray-50 last:border-0 last:pb-0">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-900 truncate">{{ $log->inquiry?->title }}</div>
                        <div class="text-xs text-gray-400 mt-0.5">{{ $log->user?->name }} &middot; {{ $log->created_at->diffForHumans() }}</div>
                    </div>
                    <span class="flex-shrink-0 px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">{{ str($log->action)->headline() }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400">{{ __('No recent actions yet.') }}</p>
            @endforelse
        </div>
    </div>
</div>
