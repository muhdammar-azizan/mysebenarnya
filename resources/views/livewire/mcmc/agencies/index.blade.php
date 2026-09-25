<?php

use App\Models\Agency;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.mcmc')] class extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Agency::class);
    }

    public function getAgenciesProperty()
    {
        return Agency::withCount(['users', 'inquiries'])->orderBy('name')->get();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-1">
        <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Agency Management') }}</h1>
        <a href="{{ route('mcmc.agencies.create') }}" wire:navigate class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">+ {{ __('Register New Agency') }}</a>
    </div>
    <p class="text-gray-500 text-sm mt-1 mb-6">{{ __('Registered verification agencies and their staff.') }}</p>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">
                    <th class="px-5 py-3">{{ __('Agency') }}</th>
                    <th class="px-5 py-3">{{ __('Specialization') }}</th>
                    <th class="px-5 py-3">{{ __('Contact') }}</th>
                    <th class="px-5 py-3">{{ __('Staff') }}</th>
                    <th class="px-5 py-3">{{ __('Inquiries') }}</th>
                    <th class="px-5 py-3">{{ __('Status') }}</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->agencies as $agency)
                    <tr wire:key="agency-{{ $agency->id }}" class="border-t border-gray-50 hover:bg-gray-50">
                        <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $agency->name }} <span class="text-gray-400 font-normal">({{ $agency->code }})</span></td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $agency->specialization?->value }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $agency->contact_email }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $agency->users_count }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $agency->inquiries_count }}</td>
                        <td class="px-5 py-3.5">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $agency->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $agency->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="{{ route('mcmc.agencies.edit', $agency) }}" wire:navigate class="text-sm font-bold text-brand hover:underline">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">{{ __('No agencies registered yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
