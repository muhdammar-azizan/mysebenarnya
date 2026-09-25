<?php

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.agency')] class extends Component
{
    public function getSummaryProperty(): array
    {
        $agencyId = Auth::user()->agency_id;
        $base = Inquiry::where('agency_id', $agencyId);

        return [
            'total' => (clone $base)->count(),
            'verified' => (clone $base)->where('status', InquiryStatus::VerifiedTrue)->count(),
            'fake' => (clone $base)->where('status', InquiryStatus::IdentifiedFake)->count(),
            'rejected' => (clone $base)->where('status', InquiryStatus::Rejected)->count(),
        ];
    }

    public function getCategoryBreakdownProperty()
    {
        return Inquiry::where('agency_id', Auth::user()->agency_id)
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();
    }

    public function getResolvedRecordsProperty()
    {
        return Inquiry::where('agency_id', Auth::user()->agency_id)
            ->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
            ->latest('resolved_at')
            ->limit(10)
            ->get();
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('Reports') }}</h1>
    <p class="text-gray-500 text-sm mb-6">{{ __("Your agency's performance summary.") }}</p>

    <div class="grid grid-cols-4 gap-4 mb-7">
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
