<?php

use App\Concerns\GeneratesReports;
use App\Models\InquiryActivityLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.mcmc')] class extends Component
{
    use GeneratesReports;
    use WithPagination;

    protected const ACTION_BADGES = [
        'submitted' => ['bg' => 'bg-gray-100', 'color' => 'text-gray-600'],
        'assigned' => ['bg' => 'bg-blue-100', 'color' => 'text-blue-700'],
        'reassigned' => ['bg' => 'bg-blue-100', 'color' => 'text-blue-700'],
        'discarded' => ['bg' => 'bg-gray-200', 'color' => 'text-gray-600'],
        'jurisdiction_accepted' => ['bg' => 'bg-amber-100', 'color' => 'text-amber-700'],
        'jurisdiction_rejected' => ['bg' => 'bg-red-100', 'color' => 'text-red-700'],
        'investigation_updated' => ['bg' => 'bg-amber-100', 'color' => 'text-amber-700'],
        'verdict_finalized' => ['bg' => 'bg-green-100', 'color' => 'text-green-700'],
    ];

    #[Url]
    public string $search = '';

    #[Url]
    public string $action = 'All';

    #[Url]
    public string $actor = 'All';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'action', 'actor', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    protected function baseQuery()
    {
        return InquiryActivityLog::query()
            ->with(['inquiry', 'user.agency'])
            ->when($this->search, fn ($q) => $q->where(fn ($qq) => $qq
                ->whereHas('inquiry', fn ($i) => $i->where('title', 'like', '%'.$this->search.'%')->orWhere('reference_no', 'like', '%'.$this->search.'%'))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$this->search.'%')->orWhere('username', 'like', '%'.$this->search.'%'))))
            ->when($this->action !== 'All', fn ($q) => $q->where('action', $this->action))
            ->when($this->actor !== 'All', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('role', $this->actor)))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo));
    }

    public function getRowsProperty()
    {
        return $this->baseQuery()->latest()->latest('id')->paginate(20);
    }

    public function getActionsProperty()
    {
        return InquiryActivityLog::query()->distinct()->orderBy('action')->pluck('action');
    }

    public function badgeFor(string $action): array
    {
        return self::ACTION_BADGES[$action] ?? ['bg' => 'bg-gray-100', 'color' => 'text-gray-600'];
    }

    public function exportCsv()
    {
        return $this->inEnglish(fn () => $this->buildExportCsv());
    }

    protected function buildExportCsv()
    {
        $rows = $this->baseQuery()->latest()->latest('id')->cursor()->map(fn ($log) => [
            $log->created_at->format('Y-m-d H:i:s'),
            $log->user?->name,
            $log->user?->role?->value,
            $log->user?->agency?->name,
            $log->action,
            $log->inquiry?->reference_no,
            $log->inquiry?->title,
            $log->from_status,
            $log->to_status,
            $log->notes,
        ]);

        return $this->downloadCsv('SEBENARNYA_Audit-Log', [
            'Date/Time', 'User', 'Role', 'Agency', 'Action', 'Reference No', 'Inquiry', 'From Status', 'To Status', 'Notes',
        ], $rows);
    }
}; ?>

<div>
    <div class="flex items-start justify-between gap-4 mb-1">
        <div>
            <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Audit Log') }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ __('Every recorded action on inquiries, by every user. Use the filters to narrow it down.') }}</p>
        </div>
        <button wire:click="exportCsv" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2.5 rounded-lg whitespace-nowrap flex-shrink-0">
            {{ __('Export CSV') }}
        </button>
    </div>

    <div class="flex flex-wrap items-center gap-3 my-5">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search inquiry, reference or user...') }}" class="flex-1 min-w-[220px] max-w-sm rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <select wire:model.live="action" class="w-full sm:w-auto rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Actions') }}</option>
            @foreach ($this->actions as $name)
                <option value="{{ $name }}">{{ str($name)->headline() }}</option>
            @endforeach
        </select>
        <select wire:model.live="actor" class="w-full sm:w-auto rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All Users') }}</option>
            <option value="public">{{ __('Public') }}</option>
            <option value="mcmc_staff">{{ __('MCMC Staff') }}</option>
            <option value="agency_staff">{{ __('Agency Staff') }}</option>
        </select>
        <input type="date" wire:model.live="dateFrom" aria-label="{{ __('From date') }}" class="w-full sm:w-auto rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <input type="date" wire:model.live="dateTo" aria-label="{{ __('To date') }}" class="w-full sm:w-auto rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <button type="button" wire:click="clearFilters" class="text-sm font-semibold text-gray-500 hover:text-brand">{{ __('Clear') }}</button>
    </div>

    <div class="bg-white border border-gray-100 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[860px] text-sm">
            <thead>
                <tr class="text-left text-[11.5px] font-bold text-gray-400 uppercase tracking-wide border-b border-gray-100">
                    <th class="px-5 py-3">{{ __('Date/Time') }}</th>
                    <th class="px-5 py-3">{{ __('User') }}</th>
                    <th class="px-5 py-3">{{ __('Action') }}</th>
                    <th class="px-5 py-3">{{ __('Inquiry') }}</th>
                    <th class="px-5 py-3">{{ __('Status Change') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $log)
                    @php $badge = $this->badgeFor($log->action); @endphp
                    <tr wire:key="audit-{{ $log->id }}" class="border-t border-gray-50 align-top">
                        <td class="px-5 py-3.5 text-gray-500 whitespace-nowrap">{{ $log->created_at->format('d M Y, g:i A') }}</td>
                        <td class="px-5 py-3.5">
                            <div class="font-semibold text-gray-900">{{ $log->user?->name ?? __('System') }}</div>
                            <div class="text-xs text-gray-400">
                                {{ $log->user ? str($log->user->role->value)->headline() : '' }}@if ($log->user?->agency) &middot; {{ $log->user->agency->name }}@endif
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold {{ $badge['bg'] }} {{ $badge['color'] }}">{{ str($log->action)->headline() }}</span>
                            @if ($log->notes)
                                <div class="text-xs text-gray-500 mt-1.5 max-w-xs">{{ $log->notes }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            @if ($log->inquiry)
                                <a href="{{ route('mcmc.inquiries.show', $log->inquiry) }}" wire:navigate class="font-semibold text-gray-900 hover:text-brand">{{ $log->inquiry->title }}</a>
                                <div class="text-xs text-gray-400">{{ $log->inquiry->reference_no }}</div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-xs text-gray-600 whitespace-nowrap">
                            @if ($log->from_status || $log->to_status)
                                {{ $log->from_status ?: '—' }} &rarr; {{ $log->to_status ?: '—' }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">{{ __('No log entries match your filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-4">{{ $this->rows->links() }}</div>
</div>
