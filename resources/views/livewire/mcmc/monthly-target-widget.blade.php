<?php

use App\Models\InquiryActivityLog;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Team-wide monthly processing goal set by MCMC management. There is no
     * database-backed target configuration yet, so this is a fixed constant
     * rather than an invented number that changes on its own.
     */
    protected const TARGET = 250;

    protected const PROCESSED_ACTIONS = ['verdict_finalized', 'discarded', 'reassigned'];

    public bool $modalOpen = false;

    public function openModal(): void
    {
        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
    }

    public function getBreakdownProperty(): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $validated = InquiryActivityLog::where('action', 'verdict_finalized')->whereBetween('created_at', [$start, $end])->count();
        $discarded = InquiryActivityLog::where('action', 'discarded')->whereBetween('created_at', [$start, $end])->count();
        $reassigned = InquiryActivityLog::where('action', 'reassigned')->whereBetween('created_at', [$start, $end])->count();
        $total = $validated + $discarded + $reassigned;

        $prevStart = now()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = now()->subMonthNoOverflow()->endOfMonth();
        $prevTotal = InquiryActivityLog::whereIn('action', self::PROCESSED_ACTIONS)->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $pctChange = $prevTotal > 0 ? round((($total - $prevTotal) / $prevTotal) * 100) : ($total > 0 ? 100 : 0);

        $daysInMonth = now()->daysInMonth;
        $bucketSize = (int) ceil($daysInMonth / 4);
        $weeks = collect(range(0, 3))->map(function (int $w) use ($start, $end, $bucketSize) {
            $weekStart = $start->copy()->addDays($w * $bucketSize);
            $weekEnd = $weekStart->copy()->addDays($bucketSize - 1)->min($end);

            if ($weekStart->gt($end)) {
                return ['label' => 'W'.($w + 1), 'value' => 0];
            }

            $count = InquiryActivityLog::whereIn('action', self::PROCESSED_ACTIONS)
                ->whereBetween('created_at', [$weekStart, $weekEnd->endOfDay()])
                ->count();

            return ['label' => 'W'.($w + 1), 'value' => $count];
        });

        $maxWeek = max(1, $weeks->max('value'));
        $weeks = $weeks->map(fn ($w) => [...$w, 'barHeight' => round(($w['value'] / $maxWeek) * 100).'%'])->values()->all();

        return [
            'validated' => $validated,
            'discarded' => $discarded,
            'reassigned' => $reassigned,
            'total' => $total,
            'pctChange' => $pctChange,
            'weeks' => $weeks,
        ];
    }

    public function getTargetProperty(): int
    {
        return self::TARGET;
    }

    public function getPercentProperty(): int
    {
        return min(100, (int) round(($this->breakdown['total'] / self::TARGET) * 100));
    }
}; ?>

<div>
    <div wire:click="openModal" class="mx-2.5 mb-3.5 mt-1 p-3.5 border border-gray-100 rounded-lg cursor-pointer hover:bg-gray-50">
        <div class="flex items-center justify-between mb-2.5">
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded-md bg-brand-light flex items-center justify-center flex-shrink-0">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M4 19V10M10 19V4M16 19v-7M22 19H2" stroke="#C41230" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="text-xs font-bold text-gray-900">{{ __('Inquiries Processed This Month') }}</div>
            </div>
            <div class="text-gray-400 text-xs">&rsaquo;</div>
        </div>
        <div class="h-1.5 bg-gray-100 rounded overflow-hidden mb-1.5">
            <div class="h-full bg-brand rounded" style="width: {{ $this->percent }}%"></div>
        </div>
        <div class="text-[11.5px] text-gray-400">{{ $this->breakdown['total'] }} {{ __('of') }} {{ $this->target }} {{ __('target') }}</div>
    </div>

    @if ($modalOpen)
        <div wire:click="closeModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div wire:click.stop class="w-[480px] bg-white rounded-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-brand-light flex items-center justify-center flex-shrink-0">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 19V10M10 19V4M16 19v-7M22 19H2" stroke="#C41230" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <div>
                            <div class="font-display font-bold text-base text-gray-900">{{ __('Inquiries Processed This Month') }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ now()->format('F Y') }}</div>
                        </div>
                    </div>
                    <button wire:click="closeModal" class="w-7 h-7 rounded-lg text-gray-400 hover:bg-gray-100 flex-shrink-0">&times;</button>
                </div>

                <div class="px-6 py-5">
                    <div class="flex items-baseline justify-between mb-2.5">
                        <div class="flex items-baseline gap-1.5">
                            <span class="font-display font-extrabold text-2xl text-gray-900">{{ $this->breakdown['total'] }}</span>
                            <span class="text-sm text-gray-400 font-semibold">/ {{ $this->target }} {{ __('target') }}</span>
                        </div>
                        <div class="bg-brand-light text-brand text-xs font-bold px-2.5 py-1 rounded-full">{{ $this->percent }}%</div>
                    </div>
                    <div class="h-2 bg-gray-100 rounded overflow-hidden">
                        <div class="h-full bg-brand rounded" style="width: {{ $this->percent }}%"></div>
                    </div>

                    <div class="h-px bg-gray-100 my-5"></div>

                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3.5">{{ __('Weekly Processing Volume') }}</div>
                    <div class="flex items-end gap-3 mb-5" style="height: 92px;">
                        @foreach ($this->breakdown['weeks'] as $w)
                            <div class="flex-1 flex flex-col items-center justify-end gap-1.5 h-full">
                                <div class="text-[11.5px] font-bold text-gray-600">{{ $w['value'] }}</div>
                                <div class="w-full max-w-[34px] rounded-t bg-brand" style="height: {{ $w['barHeight'] }}"></div>
                                <div class="text-[11px] font-semibold text-gray-400">{{ $w['label'] }}</div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex gap-2.5 mb-5">
                        <div class="flex-1 bg-gray-50 border border-gray-100 rounded-lg p-3 text-center">
                            <div class="font-display font-extrabold text-lg text-green-700">{{ $this->breakdown['validated'] }}</div>
                            <div class="text-[11px] text-gray-400 font-semibold mt-0.5">{{ __('Validated') }}</div>
                        </div>
                        <div class="flex-1 bg-gray-50 border border-gray-100 rounded-lg p-3 text-center">
                            <div class="font-display font-extrabold text-lg text-gray-500">{{ $this->breakdown['discarded'] }}</div>
                            <div class="text-[11px] text-gray-400 font-semibold mt-0.5">{{ __('Discarded') }}</div>
                        </div>
                        <div class="flex-1 bg-gray-50 border border-gray-100 rounded-lg p-3 text-center">
                            <div class="font-display font-extrabold text-lg text-blue-700">{{ $this->breakdown['reassigned'] }}</div>
                            <div class="text-[11px] text-gray-400 font-semibold mt-0.5">{{ __('Reassigned') }}</div>
                        </div>
                    </div>

                    <div class="inline-flex items-center gap-1.5 {{ $this->breakdown['pctChange'] >= 0 ? 'bg-green-50 text-green-700' : 'bg-brand-light text-brand' }} text-xs font-bold px-3 py-1.5 rounded-full mb-5">
                        {{ $this->breakdown['pctChange'] >= 0 ? __('Up') : __('Down') }} {{ abs($this->breakdown['pctChange']) }}% {{ __('compared to last month') }}
                    </div>

                    <button wire:click="closeModal" class="w-full border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm py-2.5 rounded-lg">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
