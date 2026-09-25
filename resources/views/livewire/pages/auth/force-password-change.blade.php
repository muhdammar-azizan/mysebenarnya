<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';
    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        $this->validate([
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = Auth::user();

        $user->forceFill([
            'password' => Hash::make($this->password),
            'must_change_password' => false,
        ])->save();

        $this->redirectRoute('dashboard', navigate: true);
    }
}; ?>

<div>
    <p class="mb-4 text-sm text-gray-600">
        {{ __('For security, you must set a new password before continuing.') }}
    </p>

    <form wire:submit="updatePassword">
        <div>
            <x-input-label for="password" :value="__('New Password')" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" name="password" required autofocus autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm New Password')" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Update Password') }}
            </x-primary-button>
        </div>
    </form>
</div>
