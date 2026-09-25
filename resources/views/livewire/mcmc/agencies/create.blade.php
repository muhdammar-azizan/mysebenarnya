<?php

use App\Enums\AgencyStaffRole;
use App\Enums\InquiryCategory;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\User;
use App\Notifications\AgencyAccountProvisioned;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.mcmc')] class extends Component
{
    public string $name = '';

    public string $specialization = '';

    public string $contactName = '';

    public string $contactEmail = '';

    public string $contactPhone = '';

    public string $description = '';

    public bool $registered = false;

    public string $registeredAgencyName = '';

    public function mount(): void
    {
        $this->authorize('create', Agency::class);
    }

    public function getSpecializationsProperty(): array
    {
        return array_filter(InquiryCategory::cases(), fn ($case) => $case !== InquiryCategory::Other);
    }

    public function register(): void
    {
        $this->authorize('create', Agency::class);

        $validated = Validator::make([
            'name' => $this->name,
            'specialization' => $this->specialization,
            'contactName' => $this->contactName,
            'contactEmail' => $this->contactEmail,
            'contactPhone' => $this->contactPhone,
        ], [
            'name' => 'required|string|max:255',
            'specialization' => 'required|string',
            'contactName' => 'required|string|max:255',
            'contactEmail' => 'required|email|max:255|unique:users,email',
            'contactPhone' => 'required|string|max:30',
        ])->validate();

        $agency = Agency::create([
            'name' => $validated['name'],
            'code' => Agency::generateCodeFrom($validated['name']),
            'specialization' => $validated['specialization'],
            'description' => $this->description ?: null,
            'contact_email' => $validated['contactEmail'],
            'contact_phone' => $validated['contactPhone'],
        ]);

        $temporaryPassword = Str::password(12);

        $admin = User::create([
            'name' => $validated['contactName'],
            'email' => $validated['contactEmail'],
            'password' => Hash::make($temporaryPassword),
            'role' => UserRole::AgencyStaff,
            'agency_id' => $agency->id,
            'agency_role' => AgencyStaffRole::Admin,
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        $admin->notify(new AgencyAccountProvisioned($agency, $temporaryPassword));

        $this->registered = true;
        $this->registeredAgencyName = $agency->name;
    }

    public function registerAnother(): void
    {
        $this->reset(['name', 'specialization', 'contactName', 'contactEmail', 'contactPhone', 'description', 'registered', 'registeredAgencyName']);
    }
}; ?>

<div>
    <a href="{{ route('mcmc.agencies.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-gray-500 hover:text-brand font-semibold text-sm mb-5">&larr; {{ __('Back to Agency Management') }}</a>

    @if (! $registered)
        <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('Register New Agency') }}</h1>
        <p class="text-gray-500 text-sm mb-6">{{ __('Login credentials will be generated and sent automatically via email.') }}</p>

        <form wire:submit="register" class="bg-white border border-gray-100 rounded-2xl p-7 max-w-xl space-y-5">
            <div class="flex gap-2.5 bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 text-xs text-blue-800">
                <span>ℹ️</span>
                <span>{{ __('A temporary password will be generated and emailed to the contact above. The agency will be required to change it on first login.') }}</span>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Agency Name') }}</label>
                <input type="text" wire:model="name" placeholder="{{ __('e.g. Ministry of Health (MOH)') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
                <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Specialization / Jurisdiction') }}</label>
                <select wire:model="specialization" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                    <option value="">{{ __('Select specialization...') }}</option>
                    @foreach ($this->specializations as $case)
                        <option value="{{ $case->value }}">{{ $case->value }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('specialization')" class="mt-1.5" />
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Contact Person Name') }}</label>
                <input type="text" wire:model="contactName" placeholder="{{ __('Full name') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
                <x-input-error :messages="$errors->get('contactName')" class="mt-1.5" />
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Contact Email') }}</label>
                <input type="text" wire:model="contactEmail" placeholder="agency@example.gov.my" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
                <x-input-error :messages="$errors->get('contactEmail')" class="mt-1.5" />
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Contact Phone Number') }}</label>
                <input type="text" wire:model="contactPhone" placeholder="03-1234 5678" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
                <x-input-error :messages="$errors->get('contactPhone')" class="mt-1.5" />
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Brief Description (optional)') }}</label>
                <textarea wire:model="description" rows="3" placeholder="{{ __("Describe this agency's role or scope of verification...") }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand"></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-6 py-3 rounded-lg">{{ __('Register Agency') }}</button>
                <a href="{{ route('mcmc.agencies.index') }}" wire:navigate class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-6 py-3 rounded-lg">{{ __('Cancel') }}</a>
            </div>
        </form>
    @else
        <div class="bg-white border border-gray-100 rounded-2xl p-9 max-w-md text-center">
            <div class="w-14 h-14 rounded-full bg-green-100 text-green-600 text-2xl flex items-center justify-center mx-auto mb-4">✓</div>
            <h2 class="font-display font-extrabold text-lg text-gray-900 mb-2">{{ __('Agency Registered Successfully') }}</h2>
            <p class="text-sm text-gray-500 mb-1"><strong>{{ $registeredAgencyName }}</strong> {{ __('has been added to the system.') }}</p>
            <p class="text-xs text-gray-400 mb-6">{{ __('Login credentials have been emailed to the contact address.') }}</p>
            <div class="flex gap-3 justify-center">
                <button wire:click="registerAnother" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Register Another') }}</button>
                <a href="{{ route('mcmc.agencies.index') }}" wire:navigate class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Back to Agency Management') }}</a>
            </div>
        </div>
    @endif
</div>
