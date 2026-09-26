<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $terms = false;

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ]);

        unset($validated['terms']);
        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<x-auth-card>
    <div class="font-display font-bold text-2xl mb-5">{{ __('Create an Account') }}</div>

    <form wire:submit="register" class="flex flex-col gap-4">
        <div>
            <label for="name" class="block text-[13px] font-medium mb-1.5">{{ __('Full Name') }}</label>
            <input id="name" type="text" wire:model="name" placeholder="Ahmad Ismail" required autofocus autocomplete="name"
                class="w-full box-border h-10 px-3 rounded-lg text-sm border focus:outline-none focus:ring-0 {{ $errors->has('name') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}" />
            <x-input-error :messages="$errors->get('name')" class="mt-1.5 text-brand" />
        </div>

        <div>
            <label for="email" class="block text-[13px] font-medium mb-1.5">{{ __('Email') }}</label>
            <input id="email" type="email" wire:model="email" placeholder="you@example.com" required autocomplete="username"
                class="w-full box-border h-10 px-3 rounded-lg text-sm border focus:outline-none focus:ring-0 {{ $errors->has('email') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-brand" />
        </div>

        <div x-data="{ show: false, pw: '' }">
            <label for="password" class="block text-[13px] font-medium mb-1.5">{{ __('Password') }}</label>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" wire:model="password" x-model="pw" placeholder="Create a password" required autocomplete="new-password"
                    class="w-full box-border h-10 px-3 pr-10 rounded-lg text-sm border focus:outline-none focus:ring-0 {{ $errors->has('password') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}" />
                <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-gray-400 flex items-center justify-center">
                    <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.8"/></svg>
                    <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10.6 5.2A10.6 10.6 0 0112 5c6.5 0 10 6 10 6a15.6 15.6 0 01-3.3 4M6.6 6.6C4 8.3 2 12 2 12s1.6 3 4.6 4.7M9.9 14.1a3 3 0 004.2-4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
            <template x-if="pw">
                <div x-data="{
                    get strength() {
                        if (pw.length < 6) return { bars: ['#C41230','#EDEEF0','#EDEEF0'], label: 'Weak password' };
                        if (pw.length < 10) return { bars: ['#C41230','#E8A33D','#EDEEF0'], label: 'Medium strength' };
                        return { bars: ['#1E8E5A','#1E8E5A','#1E8E5A'], label: 'Strong password' };
                    }
                }">
                    <div class="flex gap-1 mt-2">
                        <div class="flex-1 h-1 rounded-full" :style="'background:' + strength.bars[0]"></div>
                        <div class="flex-1 h-1 rounded-full" :style="'background:' + strength.bars[1]"></div>
                        <div class="flex-1 h-1 rounded-full" :style="'background:' + strength.bars[2]"></div>
                    </div>
                    <div class="text-xs text-gray-400 mt-1" x-text="strength.label"></div>
                </div>
            </template>
            <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-brand" />
        </div>

        <div x-data="{ show: false }">
            <label for="password_confirmation" class="block text-[13px] font-medium mb-1.5">{{ __('Confirm Password') }}</label>
            <div class="relative">
                <input id="password_confirmation" :type="show ? 'text' : 'password'" wire:model="password_confirmation" placeholder="Re-enter password" required autocomplete="new-password"
                    class="w-full box-border h-10 px-3 pr-10 rounded-lg text-sm border focus:outline-none focus:ring-0 {{ $errors->has('password_confirmation') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}" />
                <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-gray-400 flex items-center justify-center">
                    <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.8"/></svg>
                    <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10.6 5.2A10.6 10.6 0 0112 5c6.5 0 10 6 10 6a15.6 15.6 0 01-3.3 4M6.6 6.6C4 8.3 2 12 2 12s1.6 3 4.6 4.7M9.9 14.1a3 3 0 004.2-4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5 text-brand" />
        </div>

        <label class="flex items-start gap-2 cursor-pointer">
            <input type="checkbox" wire:model="terms" class="w-[18px] h-[18px] mt-0.5 rounded border-gray-300 text-brand focus:ring-brand flex-shrink-0" />
            <span class="text-[13px] text-gray-600 leading-snug">{{ __('I agree to the') }} <span class="text-brand">{{ __('Terms of Service') }}</span> {{ __('and') }} <span class="text-brand">{{ __('Privacy Policy') }}</span></span>
        </label>
        <x-input-error :messages="$errors->get('terms')" class="text-brand" />

        <button type="submit" class="h-10 rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold text-sm">
            {{ __('Register') }}
        </button>

        <div class="text-center text-[13px] text-gray-600">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" wire:navigate class="text-brand font-medium">{{ __('Log In') }}</a>
        </div>
    </form>
</x-auth-card>
