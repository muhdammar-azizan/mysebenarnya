<?php

use App\Concerns\GeneratesReports;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.mcmc')] class extends Component
{
    use GeneratesReports;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'All';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public ?int $panelUserId = null;

    public string $panelTab = 'profile';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function openPanel(int $id): void
    {
        $this->panelUserId = $id;
        $this->panelTab = 'profile';
    }

    public function closePanel(): void
    {
        $this->panelUserId = null;
    }

    protected function baseQuery()
    {
        return User::where('role', UserRole::Public)
            ->withCount('submittedInquiries')
            ->when($this->search, fn ($q) => $q->where(fn ($qq) => $qq->where('name', 'like', '%'.$this->search.'%')->orWhere('email', 'like', '%'.$this->search.'%')))
            ->when($this->status === 'Verified', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($this->status === 'Unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo));
    }

    public function getRowsProperty()
    {
        return $this->baseQuery()->latest()->paginate(10);
    }

    public function getSummaryProperty(): array
    {
        $publicUsers = User::where('role', UserRole::Public);

        return [
            'total' => (clone $publicUsers)->count(),
            'verified' => (clone $publicUsers)->whereNotNull('email_verified_at')->count(),
            'unverified' => (clone $publicUsers)->whereNull('email_verified_at')->count(),
            'active' => (clone $publicUsers)->where('last_active_at', '>=', now()->subDays(30))->count(),
        ];
    }

    public function getPanelUserProperty(): ?User
    {
        return $this->panelUserId ? User::withCount('submittedInquiries')->find($this->panelUserId) : null;
    }

    public function getPanelInquiriesProperty()
    {
        return $this->panelUserId
            ? Inquiry::where('submitted_by', $this->panelUserId)->latest()->limit(10)->get()
            : collect();
    }

    public function getPanelActivityProperty()
    {
        if (! $this->panelUserId) {
            return collect();
        }

        $user = $this->panelUser;
        $events = collect();

        $events->push(['date' => $user->created_at, 'label' => __('Account registered')]);
        if ($user->email_verified_at) {
            $events->push(['date' => $user->email_verified_at, 'label' => __('Email verified')]);
        }

        InquiryActivityLog::with('inquiry')
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereHas('inquiry', fn ($qq) => $qq->where('submitted_by', $user->id)))
            ->latest()
            ->limit(20)
            ->get()
            ->each(function ($log) use ($events) {
                $events->push(['date' => $log->created_at, 'label' => str($log->action)->headline().': '.$log->inquiry?->title]);
            });

        return $events->sortByDesc('date')->values();
    }

    public function exportPdf()
    {
        $rows = $this->baseQuery()->latest()->get()->map(fn ($u) => [
            $u->name,
            $u->email,
            $u->created_at->format('d M Y'),
            $u->email_verified_at ? 'Verified' : 'Unverified',
            $u->submitted_inquiries_count,
        ])->all();

        $filters = [];
        $filters[] = $this->status !== 'All' ? $this->status : __('All Statuses');
        if ($this->search) {
            $filters[] = __('Search: ":q"', ['q' => $this->search]);
        }

        return $this->downloadPdf('SEBENARNYA_Registered-Users', 'Registered Users', implode(' · ', $filters), [[
            'name' => 'Registered Users',
            'kpis' => [
                ['label' => 'Total', 'value' => $this->summary['total']],
                ['label' => 'Verified', 'value' => $this->summary['verified']],
                ['label' => 'Unverified', 'value' => $this->summary['unverified']],
                ['label' => 'Active (30d)', 'value' => $this->summary['active']],
            ],
            'tables' => [[
                'heading' => 'User Directory',
                'columns' => ['Name', 'Email', 'Registered', 'Status', 'Inquiries'],
                'rows' => $rows,
            ]],
        ]]);
    }
}; ?>

<div>
    <div class="flex items-start justify-between gap-4 mb-1">
        <div>
            <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Registered Users') }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ __('View all registered public user accounts, profile details, and activity history.') }}</p>
        </div>
        <button wire:click="exportPdf" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg whitespace-nowrap flex items-center gap-2">
            📄 {{ __('Export Report') }}
        </button>
    </div>

    <div class="flex flex-wrap gap-5 text-sm font-semibold text-gray-600 my-5">
        <span>{{ __('Total:') }} <b class="text-gray-900">{{ $this->summary['total'] }}</b></span>
        <span class="text-green-700">{{ __('Verified:') }} <b>{{ $this->summary['verified'] }}</b></span>
        <span class="text-amber-600">{{ __('Unverified:') }} <b>{{ $this->summary['unverified'] }}</b></span>
        <span class="text-blue-700">{{ __('Active (30d):') }} <b>{{ $this->summary['active'] }}</b></span>
    </div>

    <div class="flex flex-wrap gap-3 mb-6">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search by name or email...') }}"
            class="flex-1 min-w-[220px] rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="All">{{ __('All') }}</option>
            <option value="Verified">{{ __('Verified') }}</option>
            <option value="Unverified">{{ __('Unverified') }}</option>
        </select>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">
                    <th class="px-5 py-3">{{ __('Name') }}</th>
                    <th class="px-5 py-3">{{ __('Email') }}</th>
                    <th class="px-5 py-3">{{ __('Registered') }}</th>
                    <th class="px-5 py-3">{{ __('Status') }}</th>
                    <th class="px-5 py-3">{{ __('Inquiries') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $user)
                    <tr wire:key="user-{{ $user->id }}" wire:click="openPanel({{ $user->id }})" class="border-t border-gray-50 hover:bg-gray-50 cursor-pointer">
                        <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $user->name }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $user->email }}</td>
                        <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="px-5 py-3.5">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $user->email_verified_at ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $user->email_verified_at ? __('Verified') : __('Unverified') }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $user->submitted_inquiries_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">{{ __('No registered users found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t border-gray-100">{{ $this->rows->links() }}</div>
    </div>

    @if ($this->panelUser)
        <div wire:click="closePanel" class="fixed inset-0 bg-black/35 z-40"></div>
        <div class="fixed top-0 right-0 bottom-0 w-96 bg-white shadow-2xl z-50 flex flex-col">
            <div class="p-5 border-b border-gray-100 flex items-start justify-between gap-3">
                <div class="font-extrabold text-gray-900">{{ $this->panelUser->name }}</div>
                <button wire:click="closePanel" class="text-gray-400 hover:text-brand text-lg leading-none">✕</button>
            </div>
            <div class="flex border-b border-gray-100">
                <button wire:click="$set('panelTab', 'profile')" class="flex-1 text-center py-2.5 text-xs font-bold {{ $panelTab === 'profile' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Profile') }}</button>
                <button wire:click="$set('panelTab', 'activity')" class="flex-1 text-center py-2.5 text-xs font-bold {{ $panelTab === 'activity' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Activity Log') }}</button>
            </div>
            <div class="flex-1 overflow-auto p-5">
                @if ($panelTab === 'profile')
                    <div class="flex flex-col gap-4 text-sm">
                        <div><div class="text-[11px] font-bold text-gray-400 uppercase">{{ __('Email') }}</div><div class="font-semibold text-gray-800">{{ $this->panelUser->email }}</div></div>
                        <div><div class="text-[11px] font-bold text-gray-400 uppercase">{{ __('Phone') }}</div><div class="font-semibold text-gray-800">{{ $this->panelUser->phone ?? '—' }}</div></div>
                        <div><div class="text-[11px] font-bold text-gray-400 uppercase">{{ __('Registered') }}</div><div class="font-semibold text-gray-800">{{ $this->panelUser->created_at->format('d M Y') }}</div></div>
                        <div><div class="text-[11px] font-bold text-gray-400 uppercase">{{ __('Last Active') }}</div><div class="font-semibold text-gray-800">{{ $this->panelUser->last_active_at?->diffForHumans() ?? __('Never') }}</div></div>
                        <div><div class="text-[11px] font-bold text-gray-400 uppercase">{{ __('Total Inquiries') }}</div><div class="font-semibold text-gray-800">{{ $this->panelUser->submitted_inquiries_count }}</div></div>
                        <div class="border-t border-gray-100 pt-3.5">
                            <div class="text-[11px] font-bold text-gray-400 uppercase mb-2">{{ __('Submitted Inquiries') }}</div>
                            <div class="flex flex-col gap-2">
                                @forelse ($this->panelInquiries as $inquiry)
                                    <div class="bg-gray-50 rounded-lg px-3 py-2 text-xs font-semibold text-gray-800">{{ $inquiry->title }}</div>
                                @empty
                                    <span class="text-xs text-gray-400">{{ __('No inquiries submitted yet.') }}</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col gap-4">
                        @forelse ($this->panelActivity as $event)
                            <div class="flex gap-3">
                                <div class="w-2 h-2 rounded-full bg-brand mt-1.5 flex-shrink-0"></div>
                                <div>
                                    <div class="text-[11px] font-bold text-gray-400">{{ $event['date']->format('d M Y') }}</div>
                                    <div class="text-sm font-semibold text-gray-900">{{ $event['label'] }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">{{ __('No activity yet.') }}</p>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
