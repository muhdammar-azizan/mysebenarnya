<?php

use App\Enums\AgencyStaffRole;
use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Notifications\AgencyAccountProvisioned;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.mcmc')] class extends Component
{
    public string $search = '';

    public string $status = 'all';

    public string $specialization = 'all';

    public ?int $detailsId = null;

    public ?int $suspendId = null;

    public string $resendToast = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Agency::class);
    }

    public function getSpecializationsProperty(): array
    {
        return InquiryCategory::cases();
    }

    public function getAgenciesProperty()
    {
        return Agency::query()
            ->withCount([
                'inquiries as active_count' => fn ($q) => $q->where('status', InquiryStatus::UnderInvestigation),
                'inquiries as resolved_count' => fn ($q) => $q->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake]),
                'inquiries as pending_count' => fn ($q) => $q->where('status', InquiryStatus::UnderInvestigation)->whereNull('jurisdiction_accepted_at'),
            ])
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->status !== 'all', fn ($q) => $q->where('is_active', $this->status === 'Active'))
            ->when($this->specialization !== 'all', fn ($q) => $q->where('specialization', $this->specialization))
            ->orderBy('name')
            ->get()
            ->map(function ($agency) {
                $avgDays = $agency->inquiries()
                    ->whereIn('status', [InquiryStatus::VerifiedTrue, InquiryStatus::IdentifiedFake])
                    ->whereNotNull('resolved_at')
                    ->whereNotNull('reviewed_at')
                    ->get()
                    ->avg(fn ($i) => $i->reviewed_at->diffInDays($i->resolved_at));

                $agency->avg_resolution_days = $avgDays ? round($avgDays, 1) : null;

                return $agency;
            });
    }

    public function getDetailsAgencyProperty(): ?Agency
    {
        return $this->detailsId ? Agency::find($this->detailsId) : null;
    }

    public function getSuspendAgencyProperty(): ?Agency
    {
        return $this->suspendId ? Agency::find($this->suspendId) : null;
    }

    public function viewDetails(int $agencyId): void
    {
        $this->detailsId = $agencyId;
    }

    public function closeDetails(): void
    {
        $this->detailsId = null;
    }

    public function openSuspend(int $agencyId): void
    {
        $this->authorize('update', Agency::findOrFail($agencyId));

        $this->suspendId = $agencyId;
    }

    public function cancelSuspend(): void
    {
        $this->suspendId = null;
    }

    public function confirmSuspend(): void
    {
        $agency = Agency::findOrFail($this->suspendId);

        $this->authorize('update', $agency);

        $agency->update(['is_active' => ! $agency->is_active]);

        $this->suspendId = null;
    }

    public function resendCredentials(int $agencyId): void
    {
        $agency = Agency::findOrFail($agencyId);

        $this->authorize('update', $agency);

        $admin = $agency->users()->where('agency_role', AgencyStaffRole::Admin)->first() ?? $agency->users()->first();

        if (! $admin) {
            return;
        }

        $temporaryPassword = Str::password(12);

        $admin->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        $admin->notify(new AgencyAccountProvisioned($agency, $temporaryPassword));

        $this->resendToast = __('Login credentials resent to :email.', ['email' => $admin->email]);
    }
}; ?>

<div>
    <div class="flex items-start justify-between mb-1">
        <div>
            <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Agency Management') }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ __('View and manage all registered verification agencies.') }}</p>
        </div>
        <a href="{{ route('mcmc.agencies.create') }}" wire:navigate class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg whitespace-nowrap">+ {{ __('Register New Agency') }}</a>
    </div>

    @if ($resendToast)
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 font-semibold text-sm my-4">✓ {{ $resendToast }}</div>
    @endif

    <div class="flex gap-3 my-5">
        <input type="text" wire:model.live="search" placeholder="{{ __('Search agencies...') }}" class="flex-1 max-w-xs rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
        <select wire:model.live="status" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="all">{{ __('All Status') }}</option>
            <option value="Active">{{ __('Active') }}</option>
            <option value="Suspended">{{ __('Suspended') }}</option>
        </select>
        <select wire:model.live="specialization" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <option value="all">{{ __('All Specializations') }}</option>
            @foreach ($this->specializations as $case)
                <option value="{{ $case->value }}">{{ $case->value }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-visible">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">
                    <th class="px-5 py-3">{{ __('Agency Name') }}</th>
                    <th class="px-5 py-3">{{ __('Specialization') }}</th>
                    <th class="px-5 py-3">{{ __('Contact Email') }}</th>
                    <th class="px-5 py-3">{{ __('Active') }}</th>
                    <th class="px-5 py-3">{{ __('Resolved') }}</th>
                    <th class="px-5 py-3">{{ __('Status') }}</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->agencies as $agency)
                    <tr wire:key="agency-{{ $agency->id }}" class="border-t border-gray-50 hover:bg-gray-50">
                        <td class="px-5 py-3.5 font-semibold text-gray-900">
                            <div class="flex items-center gap-2.5">
                                @if ($agency->logo_path)
                                    <img src="{{ asset('storage/'.$agency->logo_path) }}" class="w-7 h-7 rounded-md object-cover flex-shrink-0" alt="">
                                @else
                                    <div class="w-7 h-7 rounded-md bg-brand text-white flex items-center justify-center font-bold text-[10px] flex-shrink-0">
                                        {{ collect(explode(' ', $agency->name))->map(fn ($w) => $w[0] ?? '')->take(2)->implode('') }}
                                    </div>
                                @endif
                                <span>{{ $agency->name }} <span class="text-gray-400 font-normal">({{ $agency->code }})</span></span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $agency->specialization?->value }}</td>
                        <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $agency->contact_email }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $agency->active_count }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $agency->resolved_count }}</td>
                        <td class="px-5 py-3.5">
                            <span class="inline-block min-w-[80px] text-center px-2.5 py-1 rounded-full text-xs font-bold {{ $agency->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $agency->is_active ? __('Active') : __('Suspended') }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right relative" x-data="{ open: false }" @click.outside="open = false">
                            <button @click="open = !open" class="cursor-pointer font-bold text-gray-400 hover:text-gray-600 px-1">&vellip;</button>
                            <div x-show="open" x-cloak class="absolute right-5 mt-1 w-52 bg-white border border-gray-100 rounded-lg shadow-lg z-30 text-left overflow-hidden">
                                <button type="button" wire:click="viewDetails({{ $agency->id }})" @click="open = false" class="block w-full text-left px-3.5 py-2.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('View Details') }}</button>
                                <a href="{{ route('mcmc.agencies.edit', $agency) }}" wire:navigate class="block w-full text-left px-3.5 py-2.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('Edit Agency') }}</a>
                                <button type="button" wire:click="openSuspend({{ $agency->id }})" @click="open = false" class="block w-full text-left px-3.5 py-2.5 text-xs font-medium text-brand hover:bg-gray-50">
                                    {{ $agency->is_active ? __('Suspend Account') : __('Reactivate Account') }}
                                </button>
                                <button type="button" wire:click="resendCredentials({{ $agency->id }})" @click="open = false" class="block w-full text-left px-3.5 py-2.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('Resend Login Credentials') }}</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">{{ __('No agencies match these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($this->detailsAgency)
        <div class="fixed inset-0 bg-black/45 flex justify-end z-50" wire:click="closeDetails">
            <div class="w-[420px] h-full bg-white p-7 overflow-auto shadow-2xl" wire:click.stop>
                <div class="font-display font-bold text-lg mb-4">{{ $this->detailsAgency->name }}</div>
                <div class="flex flex-col gap-3.5 text-sm">
                    <div>
                        <div class="text-gray-400 text-xs mb-1">{{ __('Specialization') }}</div>
                        <div class="font-semibold">{{ $this->detailsAgency->specialization?->value }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-xs mb-1">{{ __('Contact Email') }}</div>
                        <div class="font-semibold">{{ $this->detailsAgency->contact_email }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-xs mb-1">{{ __('Contact Phone') }}</div>
                        <div class="font-semibold">{{ $this->detailsAgency->contact_phone }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-xs mb-1">{{ __('Date Registered') }}</div>
                        <div class="font-semibold">{{ $this->detailsAgency->created_at->format('d M Y') }}</div>
                    </div>
                    <div class="border-t border-gray-100 pt-3.5 grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-gray-400 text-xs mb-1">{{ __('Assigned') }}</div>
                            <div class="font-bold text-base">{{ $this->detailsAgency->active_count }}</div>
                        </div>
                        <div>
                            <div class="text-gray-400 text-xs mb-1">{{ __('Resolved') }}</div>
                            <div class="font-bold text-base">{{ $this->detailsAgency->resolved_count }}</div>
                        </div>
                        <div>
                            <div class="text-gray-400 text-xs mb-1">{{ __('Pending') }}</div>
                            <div class="font-bold text-base">{{ $this->detailsAgency->pending_count }}</div>
                        </div>
                        <div>
                            <div class="text-gray-400 text-xs mb-1">{{ __('Avg. Resolution') }}</div>
                            <div class="font-bold text-base">{{ $this->detailsAgency->avg_resolution_days !== null ? $this->detailsAgency->avg_resolution_days.' '.__('days') : '—' }}</div>
                        </div>
                    </div>
                </div>
                <button wire:click="closeDetails" class="w-full mt-6 border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold text-sm py-2.5 rounded-lg">{{ __('Close') }}</button>
            </div>
        </div>
    @endif

    @if ($this->suspendAgency)
        <div class="fixed inset-0 bg-black/45 flex items-center justify-center z-[55]">
            <div class="w-[420px] bg-white rounded-xl p-6 shadow-2xl">
                <div class="font-display font-bold text-base mb-2.5">
                    {{ $this->suspendAgency->is_active ? __('Suspend :name?', ['name' => $this->suspendAgency->name]) : __('Reactivate :name?', ['name' => $this->suspendAgency->name]) }}
                </div>
                <div class="text-sm text-gray-600 leading-relaxed mb-5">
                    {{ $this->suspendAgency->is_active
                        ? __('They will no longer receive new inquiry assignments until reactivated.')
                        : __('They will resume receiving new inquiry assignments.') }}
                </div>
                <div class="flex gap-2.5">
                    <button wire:click="cancelSuspend" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-900 font-semibold text-sm py-2.5 rounded-lg">{{ __('Cancel') }}</button>
                    <button wire:click="confirmSuspend" class="flex-1 bg-brand hover:bg-brand-dark text-white font-semibold text-sm py-2.5 rounded-lg">
                        {{ $this->suspendAgency->is_active ? __('Confirm Suspend') : __('Confirm Reactivate') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
