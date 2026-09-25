<?php

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.agency')] class extends Component
{
    public function getStatsProperty(): array
    {
        $agencyId = Auth::user()->agency_id;
        $base = Inquiry::where('agency_id', $agencyId);

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
        $agencyId = Auth::user()->agency_id;
        $verified = Inquiry::where('agency_id', $agencyId)->where('status', InquiryStatus::VerifiedTrue)->count();
        $fake = Inquiry::where('agency_id', $agencyId)->where('status', InquiryStatus::IdentifiedFake)->count();
        $rejected = Inquiry::where('agency_id', $agencyId)->where('status', InquiryStatus::Rejected)->count();
        $total = max(1, $verified + $fake + $rejected);

        return [
            ['label' => __('Verified as True'), 'count' => $verified, 'pct' => round($verified / $total * 100), 'color' => 'bg-green-600'],
            ['label' => __('Identified as Fake'), 'count' => $fake, 'pct' => round($fake / $total * 100), 'color' => 'bg-brand'],
            ['label' => __('Rejected by us'), 'count' => $rejected, 'pct' => round($rejected / $total * 100), 'color' => 'bg-gray-400'],
        ];
    }

    public function getRecentProperty()
    {
        return Inquiry::where('agency_id', Auth::user()->agency_id)->latest()->limit(5)->get();
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-6">{{ __('Agency Dashboard') }}</h1>

    <div class="grid grid-cols-4 gap-4 mb-7">
        <a href="{{ route('agency.inquiries.index') }}" wire:navigate class="bg-blue-50 border border-blue-100 rounded-xl p-5 hover:shadow-sm">
            <div class="text-xs font-semibold text-blue-700 mb-1">{{ __('Total Assigned') }}</div>
            <div class="text-3xl font-extrabold text-blue-700">{{ $this->stats['total'] }}</div>
        </a>
        <a href="{{ route('agency.inquiries.index', ['status' => 'Under Investigation']) }}" wire:navigate class="bg-amber-50 border border-amber-100 rounded-xl p-5 hover:shadow-sm">
            <div class="text-xs font-semibold text-amber-700 mb-1">{{ __('Under Investigation') }}</div>
            <div class="text-3xl font-extrabold text-amber-700">{{ $this->stats['investigation'] }}</div>
        </a>
        <div class="bg-green-50 border border-green-100 rounded-xl p-5">
            <div class="text-xs font-semibold text-green-700 mb-1">{{ __('Resolved This Month') }}</div>
            <div class="text-3xl font-extrabold text-green-700">{{ $this->stats['resolved_month'] }}</div>
        </div>
        <a href="{{ route('agency.inquiries.index', ['status' => 'awaiting']) }}" wire:navigate class="bg-brand-light border border-red-100 rounded-xl p-5 hover:shadow-sm">
            <div class="text-xs font-semibold text-brand mb-1">{{ __('Awaiting Your Review') }}</div>
            <div class="text-3xl font-extrabold text-brand">{{ $this->stats['awaiting_review'] }}</div>
        </a>
    </div>

    <div class="grid grid-cols-2 gap-6">
        <div class="bg-white border border-gray-100 rounded-2xl p-6">
            <div class="font-bold text-gray-900 mb-4">{{ __('Resolution Breakdown') }}</div>
            <div class="flex flex-col gap-4">
                @foreach ($this->breakdown as $row)
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-semibold text-gray-700">{{ $row['label'] }}</span>
                            <span class="text-gray-500">{{ $row['count'] }} ({{ $row['pct'] }}%)</span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="{{ $row['color'] }} h-full rounded-full" style="width: {{ $row['pct'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl p-6">
            <div class="font-bold text-gray-900 mb-4">{{ __('Recently Assigned') }}</div>
            <div class="flex flex-col gap-3">
                @forelse ($this->recent as $inquiry)
                    <a href="{{ route('agency.inquiries.show', $inquiry) }}" wire:navigate class="flex items-center justify-between gap-3 pb-3 border-b border-gray-50 last:border-0 last:pb-0">
                        <span class="text-sm font-semibold text-gray-900 truncate">{{ $inquiry->title }}</span>
                        <x-inquiry-status-badge :status="$inquiry->status" />
                    </a>
                @empty
                    <p class="text-sm text-gray-400">{{ __('No inquiries assigned yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
