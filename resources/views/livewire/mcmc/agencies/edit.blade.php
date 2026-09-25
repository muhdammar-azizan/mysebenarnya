<?php

use App\Enums\InquiryCategory;
use App\Models\Agency;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.mcmc')] class extends Component
{
    public Agency $agency;

    public string $name = '';

    public string $specialization = '';

    public string $contactEmail = '';

    public string $contactPhone = '';

    public string $description = '';

    public bool $isActive = true;

    public bool $saved = false;

    public function mount(Agency $agency): void
    {
        $this->authorize('update', $agency);

        $this->agency = $agency;
        $this->name = $agency->name;
        $this->specialization = $agency->specialization?->value ?? '';
        $this->contactEmail = $agency->contact_email ?? '';
        $this->contactPhone = $agency->contact_phone ?? '';
        $this->description = $agency->description ?? '';
        $this->isActive = $agency->is_active;
    }

    public function getSpecializationsProperty(): array
    {
        return array_filter(InquiryCategory::cases(), fn ($case) => $case !== InquiryCategory::Other);
    }

    public function getStaffProperty()
    {
        return $this->agency->users()->get();
    }

    public function save(): void
    {
        $this->authorize('update', $this->agency);

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'specialization' => 'required|string',
            'contactEmail' => 'required|email|max:255',
            'contactPhone' => 'required|string|max:30',
        ]);

        $this->agency->update([
            'name' => $validated['name'],
            'specialization' => $validated['specialization'],
            'contact_email' => $validated['contactEmail'],
            'contact_phone' => $validated['contactPhone'],
            'description' => $this->description ?: null,
            'is_active' => $this->isActive,
        ]);

        $this->saved = true;
    }
}; ?>

<div>
    <a href="{{ route('mcmc.agencies.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-gray-500 hover:text-brand font-semibold text-sm mb-5">&larr; {{ __('Back to Agency Management') }}</a>

    <div class="flex items-center gap-4 mb-6">
        @if ($agency->logo_path)
            <img src="{{ asset('storage/'.$agency->logo_path) }}" class="w-14 h-14 rounded-xl object-cover border border-gray-100" alt="{{ __('Agency logo') }}">
        @else
            <div class="w-14 h-14 rounded-xl bg-brand text-white flex items-center justify-center font-bold text-lg flex-shrink-0">
                {{ collect(explode(' ', $agency->name))->map(fn ($w) => $w[0] ?? '')->take(2)->implode('') }}
            </div>
        @endif
        <div>
            <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Edit Agency') }}</h1>
            <p class="text-gray-500 text-sm">{{ $agency->code }}</p>
        </div>
    </div>
    <p class="text-xs text-gray-400 -mt-4 mb-6">{{ __('The agency logo is managed by the agency itself under their Agency Profile page.') }}</p>

    @if ($saved)
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 font-semibold text-sm mb-5">✓ {{ __('Agency details saved.') }}</div>
    @endif

    <div class="grid grid-cols-3 gap-6">
        <form wire:submit="save" class="col-span-2 bg-white border border-gray-100 rounded-2xl p-7 space-y-5">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Agency Name') }}</label>
                <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
                <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Specialization / Jurisdiction') }}</label>
                <select wire:model="specialization" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                    @foreach ($this->specializations as $case)
                        <option value="{{ $case->value }}">{{ $case->value }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('specialization')" class="mt-1.5" />
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Contact Email') }}</label>
                <input type="text" wire:model="contactEmail" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
                <x-input-error :messages="$errors->get('contactEmail')" class="mt-1.5" />
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Contact Phone Number') }}</label>
                <input type="text" wire:model="contactPhone" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
                <x-input-error :messages="$errors->get('contactPhone')" class="mt-1.5" />
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Description') }}</label>
                <textarea wire:model="description" rows="3" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand"></textarea>
            </div>
            <label class="flex items-center gap-2.5">
                <input type="checkbox" wire:model="isActive" class="rounded border-gray-300 text-brand focus:ring-brand" />
                <span class="text-sm font-semibold text-gray-700">{{ __('Active') }}</span>
            </label>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-6 py-3 rounded-lg">{{ __('Save Changes') }}</button>
                <a href="{{ route('mcmc.agencies.index') }}" wire:navigate class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-6 py-3 rounded-lg">{{ __('Cancel') }}</a>
            </div>
        </form>

        <div class="bg-white border border-gray-100 rounded-2xl p-6 h-fit">
            <div class="font-bold text-gray-900 mb-3">{{ __('Staff') }}</div>
            <div class="flex flex-col gap-3">
                @foreach ($this->staff as $staffMember)
                    <div class="flex items-center justify-between">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-gray-900 truncate">{{ $staffMember->name }}</div>
                            <div class="text-xs text-gray-400 truncate">{{ $staffMember->email }}</div>
                        </div>
                        <span class="flex-shrink-0 px-2 py-0.5 rounded-full text-[11px] font-bold {{ $staffMember->agency_role?->value === 'admin' ? 'bg-brand-light text-brand' : 'bg-gray-100 text-gray-600' }}">
                            {{ str($staffMember->agency_role?->value ?? '—')->headline() }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
