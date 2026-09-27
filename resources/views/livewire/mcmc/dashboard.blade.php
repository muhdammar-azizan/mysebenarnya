<?php

use App\Enums\ClarificationStatus;
use App\Enums\ClarificationPriority;
use App\Enums\ConsultStatus;
use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\ClarificationConsult;
use App\Models\ClarificationThread;
use App\Models\Inquiry;
use App\Models\ReportExport;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.mcmc')] class extends Component
{
    public function getStatsProperty(): array
    {
        return [
            'total' => Inquiry::count(),
            'pending' => Inquiry::where('status', InquiryStatus::Submitted)->count(),
            'reassign' => Inquiry::where('status', InquiryStatus::Rejected)->count(),
            'agencies' => Agency::count(),
        ];
    }

    public function getPlainStatsProperty(): array
    {
        return [
            'total' => Inquiry::count(),
            'assigned' => Inquiry::whereNotNull('agency_id')->count(),
            'reportsThisMonth' => ReportExport::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];
    }

    public function getClarifyAlertProperty(): ?array
    {
        $openThreads = ClarificationThread::where('status', ClarificationStatus::Open)
            ->with(['inquiry', 'inquiry.agency'])
            ->latest()
            ->get();

        $urgentCount = $openThreads->where('priority', ClarificationPriority::Urgent)->count();
        $adviceCount = ClarificationConsult::where('status', ConsultStatus::Responded)->where('unread', true)->count();

        if ($openThreads->isEmpty() && $adviceCount === 0) {
            return null;
        }

        $title = $openThreads->count().' '.Str::plural('clarification request', $openThreads->count()).' from agencies awaiting your response';
        if ($urgentCount > 0) {
            $title .= " ({$urgentCount} urgent)";
        }

        $sub = '';
        if ($latest = $openThreads->first()) {
            $sub = 'Latest: "'.$latest->inquiry?->title.'" from '.($latest->inquiry?->agency?->name ?? '—');
        }
        if ($adviceCount > 0) {
            $sub .= ($sub ? ' · ' : '').$adviceCount.' with new advice from a consulted agency';
        }

        return ['title' => $title, 'sub' => $sub];
    }

    public function getTriageRowsProperty()
    {
        return Inquiry::where('status', InquiryStatus::Submitted)
            ->withCount('evidence')
            ->latest()
            ->limit(5)
            ->get();
    }

    public function getAgencySnapshotProperty()
    {
        return Agency::withCount([
            'inquiries',
            'inquiries as resolved_count' => fn ($q) => $q->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake]),
        ])
            ->orderByDesc('inquiries_count')
            ->limit(5)
            ->get()
            ->map(function ($agency) {
                $avgDays = Inquiry::where('agency_id', $agency->id)
                    ->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
                    ->whereNotNull('resolved_at')
                    ->whereNotNull('reviewed_at')
                    ->get()
                    ->avg(fn ($i) => $i->reviewed_at->diffInDays($i->resolved_at));

                $rate = $agency->inquiries_count > 0 ? round(($agency->resolved_count / $agency->inquiries_count) * 100) : 0;

                $agency->avg_resolution_days = $avgDays ? round($avgDays, 1) : null;
                $agency->resolution_rate = $rate;

                return $agency;
            });
    }

    public function getTrendProperty(): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        $data = $months->map(function ($month) {
            $count = Inquiry::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count();

            return ['label' => $month->format('M'), 'v' => $count];
        })->values()->all();

        $niceMax = max(20, (int) (ceil(max(array_column($data, 'v')) / 20) * 20));
        $chartW = 300;
        $padX = 4;
        $top = 8;
        $bottom = 95;
        $n = count($data);
        $step = $n > 1 ? ($chartW - $padX * 2) / ($n - 1) : 0;

        $points = [];
        foreach ($data as $i => $d) {
            $points[] = [
                'x' => round($padX + $i * $step, 1),
                'y' => round($bottom - ($d['v'] / $niceMax) * ($bottom - $top), 1),
                'label' => $d['label'],
                'v' => $d['v'],
            ];
        }

        $polyline = collect($points)->map(fn ($p) => "{$p['x']},{$p['y']}")->implode(' ');
        $area = 'M '.$points[0]['x'].' '.$bottom.' '.collect($points)->map(fn ($p) => "L {$p['x']} {$p['y']}")->implode(' ').' L '.$points[count($points) - 1]['x'].' '.$bottom.' Z';
        $yTicks = collect(range(0, 4))->map(fn ($i) => (int) round($niceMax * (1 - $i / 4)))->all();
        $gridY = collect(range(0, 4))->map(fn ($i) => round($top + ($i / 4) * ($bottom - $top), 1))->all();

        $latest = end($data);
        $prev = $data[count($data) - 2] ?? null;
        $pctChange = ($prev && $prev['v'] > 0) ? round((($latest['v'] - $prev['v']) / $prev['v']) * 100) : 0;
        $summary = "{$latest['v']} inquiries in {$latest['label']}".($prev ? ' · '.($pctChange >= 0 ? 'up' : 'down').' '.abs($pctChange).'% from '.$prev['label'] : '');

        return compact('points', 'polyline', 'area', 'yTicks', 'gridY', 'summary');
    }

    public function categoryStyle(?InquiryCategory $category): array
    {
        return match ($category) {
            InquiryCategory::HealthMedical => ['bg' => 'bg-brand-light', 'color' => 'text-brand', 'code' => 'HM'],
            InquiryCategory::FinancialScams => ['bg' => 'bg-teal-50', 'color' => 'text-teal-700', 'code' => 'FS'],
            InquiryCategory::ElectoralPolitical => ['bg' => 'bg-purple-50', 'color' => 'text-purple-700', 'code' => 'EP'],
            InquiryCategory::ConsumerRights => ['bg' => 'bg-blue-50', 'color' => 'text-blue-700', 'code' => 'CR'],
            InquiryCategory::CriminalFraud => ['bg' => 'bg-gray-100', 'color' => 'text-gray-600', 'code' => 'CF'],
            InquiryCategory::DisasterEmergency => ['bg' => 'bg-amber-50', 'color' => 'text-amber-700', 'code' => 'DE'],
            InquiryCategory::TechnologyDigital => ['bg' => 'bg-green-50', 'color' => 'text-green-700', 'code' => 'TD'],
            default => ['bg' => 'bg-gray-100', 'color' => 'text-gray-500', 'code' => 'OT'],
        };
    }

}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('MCMC Dashboard') }}</h1>
    <p class="text-gray-500 text-sm mb-5">{{ __('Overview of inquiry activity and system status.') }}</p>

    @if ($this->clarifyAlert)
        <a href="{{ route('mcmc.clarifications.index') }}" wire:navigate class="flex items-center gap-3.5 bg-purple-50 border border-purple-200 rounded-lg px-4 py-3 mb-5 hover:shadow-md transition-shadow">
            <div class="w-9 h-9 rounded-full bg-purple-700 text-white flex items-center justify-center flex-shrink-0">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 5h16v11H9l-5 4V5z" stroke="#fff" stroke-width="2" stroke-linejoin="round"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-bold text-purple-900">{{ $this->clarifyAlert['title'] }}</div>
                @if ($this->clarifyAlert['sub'])
                    <div class="text-xs text-gray-500 mt-0.5">{{ $this->clarifyAlert['sub'] }}</div>
                @endif
            </div>
            <div class="text-sm font-bold text-purple-700 whitespace-nowrap">{{ __('Respond →') }}</div>
        </a>
    @endif

    <div class="flex gap-2.5 mb-6 flex-wrap">
        <div class="bg-white border border-gray-100 rounded-lg px-4 py-2.5 flex items-baseline gap-2">
            <div class="font-display font-extrabold text-lg text-gray-900">{{ $this->plainStats['total'] }}</div>
            <div class="text-xs text-gray-500 font-semibold">{{ __('Total Inquiries Received') }}</div>
        </div>
        <div class="bg-white border border-gray-100 rounded-lg px-4 py-2.5 flex items-baseline gap-2">
            <div class="font-display font-extrabold text-lg text-blue-700">{{ $this->plainStats['assigned'] }}</div>
            <div class="text-xs text-gray-500 font-semibold">{{ __('Assigned to Agencies') }}</div>
        </div>
        <a href="{{ route('mcmc.reports.index') }}" wire:navigate class="bg-white border border-gray-100 rounded-lg px-4 py-2.5 flex items-baseline gap-2 hover:-translate-y-0.5 hover:shadow-md transition">
            <div class="font-display font-extrabold text-lg text-purple-700">{{ $this->plainStats['reportsThisMonth'] }}</div>
            <div class="text-xs text-gray-500 font-semibold">{{ __('Reports Generated This Month') }}</div>
        </a>
    </div>

    <div class="grid grid-cols-[65fr_35fr] gap-4 mb-6 items-stretch">
        <div class="bg-white border border-gray-100 rounded-lg p-5 flex flex-col"
            x-data="{
                points: {{ Js::from($this->trend['points']) }},
                hover: null,
                onMove(e) {
                    const rect = e.currentTarget.getBoundingClientRect();
                    const relX = (e.clientX - rect.left) / rect.width;
                    const idx = Math.max(0, Math.min(this.points.length - 1, Math.round(relX * (this.points.length - 1))));
                    this.hover = this.points[idx];
                },
                onLeave() { this.hover = null; }
            }"
        >
            <div class="font-bold text-gray-900 mb-4">{{ __('Inquiry Trend') }}</div>
            <div class="flex gap-2" style="height:190px;">
                <div class="flex flex-col justify-between text-right" style="height:190px;">
                    @foreach ($this->trend['yTicks'] as $tick)
                        <div class="text-[10.5px] text-gray-400">{{ $tick }}</div>
                    @endforeach
                </div>
                <div class="relative flex-1 min-w-0">
                    <svg viewBox="0 0 300 110" preserveAspectRatio="none" width="100%" height="100%" class="block">
                        @foreach ($this->trend['gridY'] as $gy)
                            <line x1="0" y1="{{ $gy }}" x2="300" y2="{{ $gy }}" stroke="#F0F1F3" stroke-width="1" />
                        @endforeach
                        <path d="{{ $this->trend['area'] }}" fill="#C41230" fill-opacity="0.08" stroke="none" />
                        <polyline points="{{ $this->trend['polyline'] }}" fill="none" stroke="#C41230" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                        @foreach ($this->trend['points'] as $p)
                            <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4" fill="#C41230" stroke="#fff" stroke-width="1.5" vector-effect="non-scaling-stroke" style="pointer-events:none;" />
                        @endforeach
                        <rect x="0" y="0" width="300" height="110" fill="transparent" @mousemove="onMove" @mouseleave="onLeave" style="cursor:crosshair;" />
                    </svg>
                    <div x-show="hover" x-cloak class="absolute bg-gray-900 text-white rounded-lg px-2.5 py-2 text-[11.5px] whitespace-nowrap pointer-events-none z-10"
                        :style="hover ? `left:${(hover.x/300)*100}%; top:${(hover.y/110)*100}%; transform:translate(-50%,-125%);` : ''">
                        <div class="font-bold mb-0.5" x-text="hover?.label"></div>
                        <div class="flex items-center gap-1.5"><span class="w-2 h-2 bg-brand rounded-sm flex-shrink-0"></span><span x-text="'Total Inquiries: ' + hover?.v"></span></div>
                    </div>
                </div>
            </div>
            <div class="flex justify-between mt-2 pl-6">
                @foreach ($this->trend['points'] as $p)
                    <div class="flex-1 text-center text-[11px] text-gray-400">{{ $p['label'] }}</div>
                @endforeach
            </div>
            <div class="text-[11.5px] text-gray-400 mt-3 text-center">{{ $this->trend['summary'] }}</div>
        </div>

        <div class="flex flex-col gap-3.5">
            <a href="{{ route('mcmc.triage.index') }}" wire:navigate class="bg-amber-50 border border-amber-100 rounded-lg p-4 flex items-center gap-3.5 flex-1 hover:-translate-y-0.5 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-full bg-amber-200 text-amber-700 flex items-center justify-center flex-shrink-0">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3.2 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-gray-900">{{ __('Pending Triage') }}</div>
                    <div class="text-gray-500 text-xs">{{ $this->stats['pending'] }} {{ __('inquiries') }}</div>
                </div>
                <div class="w-[30px] h-[30px] rounded-lg bg-white text-amber-700 flex items-center justify-center font-extrabold text-[13px] flex-shrink-0">{{ $this->stats['pending'] }}</div>
            </a>
            <a href="{{ route('mcmc.triage.index', ['tab' => 'reassign']) }}" wire:navigate class="bg-blue-50 border border-blue-100 rounded-lg p-4 flex items-center gap-3.5 flex-1 hover:-translate-y-0.5 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-full bg-blue-200 text-blue-700 flex items-center justify-center flex-shrink-0">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M4 8h13l-3-3M20 16H7l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-gray-900">{{ __('Needs Reassignment') }}</div>
                    <div class="text-gray-500 text-xs">{{ $this->stats['reassign'] }} {{ __('inquiries') }}</div>
                </div>
                <div class="w-[30px] h-[30px] rounded-lg bg-white text-blue-700 flex items-center justify-center font-extrabold text-[13px] flex-shrink-0">{{ $this->stats['reassign'] }}</div>
            </a>
            <a href="{{ route('mcmc.agencies.index') }}" wire:navigate class="bg-green-50 border border-green-100 rounded-lg p-4 flex items-center gap-3.5 flex-1 hover:-translate-y-0.5 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-full bg-green-200 text-green-700 flex items-center justify-center flex-shrink-0">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><rect x="4" y="10" width="16" height="10.5" rx="1.3" stroke="currentColor" stroke-width="1.8"/><path d="M7 10V6.5a1.5 1.5 0 011.5-1.5h7A1.5 1.5 0 0117 6.5V10" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 14.5h1.5M13.5 14.5H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-gray-900">{{ __('Registered Agencies') }}</div>
                    <div class="text-gray-500 text-xs">{{ $this->stats['agencies'] }} {{ __('agencies') }}</div>
                </div>
                <div class="w-[30px] h-[30px] rounded-lg bg-white text-green-700 flex items-center justify-center font-extrabold text-[13px] flex-shrink-0">{{ $this->stats['agencies'] }}</div>
            </a>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-lg overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="font-bold text-gray-900">{{ __('Inquiries Awaiting Triage') }}</div>
            <a href="{{ route('mcmc.triage.index') }}" wire:navigate class="border border-gray-300 hover:bg-gray-50 hover:border-gray-400 text-gray-700 font-semibold text-xs px-3.5 py-2 rounded-lg">{{ __('View All Inquiries →') }}</a>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11.5px] font-bold text-gray-400 uppercase tracking-wide border-b border-gray-100">
                    <th class="px-5 py-3">{{ __('Title') }}</th>
                    <th class="px-5 py-3">{{ __('Evidence') }}</th>
                    <th class="px-5 py-3">{{ __('Date Submitted') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->triageRows as $row)
                    @php $cat = $this->categoryStyle($row->category); @endphp
                    <tr wire:key="triage-{{ $row->id }}" onclick="window.location='{{ route('mcmc.triage.index', ['screen' => 'review', 'currentId' => $row->id]) }}'" class="border-t border-gray-50 hover:bg-gray-50 cursor-pointer">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg {{ $cat['bg'] }} {{ $cat['color'] }} flex items-center justify-center font-extrabold text-[10.5px] flex-shrink-0">{{ $cat['code'] }}</div>
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900 truncate">{{ $row->title }}</div>
                                    <div class="text-gray-400 text-xs">{{ $row->category?->value }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $row->evidence_count }} {{ __('file'.($row->evidence_count === 1 ? '' : 's')).' attached' }}</td>
                        <td class="px-5 py-3.5 text-gray-500 whitespace-nowrap">{{ $row->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-10 text-center text-gray-400">{{ __('No inquiries awaiting triage.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white border border-gray-100 rounded-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="font-bold text-gray-900">{{ __('Agency Performance Snapshot') }}</div>
            <a href="{{ route('mcmc.reports.index') }}" wire:navigate class="border border-gray-300 hover:bg-gray-50 hover:border-gray-400 text-gray-700 font-semibold text-xs px-3.5 py-2 rounded-lg">{{ __('View Full Reports →') }}</a>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11.5px] font-bold text-gray-400 uppercase tracking-wide border-b border-gray-100">
                    <th class="px-5 py-3">{{ __('Agency Name') }}</th>
                    <th class="px-5 py-3">{{ __('Assigned') }}</th>
                    <th class="px-5 py-3">{{ __('Resolved') }}</th>
                    <th class="px-5 py-3">{{ __('Avg. Resolution Time') }}</th>
                    <th class="px-5 py-3">{{ __('Resolution Rate') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->agencySnapshot as $agency)
                    <tr wire:key="snap-{{ $agency->id }}" class="border-t border-gray-50">
                        <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $agency->name }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $agency->inquiries_count }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $agency->resolved_count }}</td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $agency->avg_resolution_days !== null ? $agency->avg_resolution_days.' '.__('days') : '—' }}</td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $agency->resolution_rate >= 85 ? 'bg-green-100 text-green-700' : ($agency->resolution_rate >= 70 ? 'bg-amber-100 text-amber-700' : 'bg-brand-light text-brand') }}">{{ $agency->resolution_rate }}%</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">{{ __('No agency activity yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
