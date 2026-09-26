<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth')] class extends Component
{
    public LoginForm $form;

    public string $googleMessage = '';

    /**
     * @var array<string, string>
     */
    public array $portals = [
        'public' => 'Public',
        'mcmc_staff' => 'MCMC Staff',
        'agency_staff' => 'Agency Partner',
    ];

    public function setRole(string $role): void
    {
        $this->form->role = $role;
        $this->googleMessage = '';
        $this->resetErrorBag();
    }

    public function getCopyProperty(): array
    {
        return match ($this->form->role) {
            'mcmc_staff' => [
                'heading' => 'MCMC Staff Login',
                'subtitle' => 'Sign in to triage, assign, and manage inquiries.',
                'emailPlaceholder' => 'Staff Email',
                'submitLabel' => 'Login as MCMC Staff',
            ],
            'agency_staff' => [
                'heading' => 'Agency Partner Login',
                'subtitle' => 'Sign in to review and investigate assigned inquiries.',
                'emailPlaceholder' => 'Agency Email',
                'submitLabel' => 'Login as Agency Staff',
            ],
            default => [
                'heading' => 'Welcome Back',
                'subtitle' => 'Verify news, one inquiry at a time.',
                'emailPlaceholder' => 'Email',
                'submitLabel' => 'Login',
            ],
        };
    }

    public function showGoogleMessage(): void
    {
        $this->googleMessage = 'Google sign-in is not available in this demo.';
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="min-h-screen box-border flex items-center justify-center px-6 py-8 bg-[#F0F1F3]">
    <div class="w-full max-w-[1000px] min-h-[620px] bg-white rounded-xl shadow-2xl overflow-hidden flex flex-wrap">

        {{-- Photo panel --}}
        <div class="flex-[0.8_1_320px] min-w-[260px] relative overflow-hidden">
            <img src="{{ asset('images/login-photo.jpg') }}" alt="" class="absolute inset-0 w-full h-full object-cover" />
            <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(0,0,0,0.55) 0%, rgba(0,0,0,0.05) 30%, rgba(0,0,0,0.15) 60%, rgba(0,0,0,0.82) 100%);"></div>

            <div class="absolute top-8 left-8 flex items-center gap-2.5 z-10">
                <x-brand-mark class="w-[34px] h-[34px] flex-shrink-0" />
                <div class="font-display font-extrabold text-[15px] tracking-tight whitespace-nowrap">
                    <span class="text-white">SE</span><span class="text-[#F5919E]">BENAR</span><span class="text-white">NYA.MY</span>
                </div>
            </div>

            <div class="absolute bottom-8 left-8 right-8 z-10">
                <div class="font-display font-bold text-[22px] text-white leading-snug mb-3.5">&quot;Tidak Pasti, Jangan Kongsi&quot;</div>
                <div class="text-[13px] font-bold text-white">MCMC Fact-Check Desk</div>
                <div class="text-xs text-white/75">Malaysian Communications and Multimedia Commission</div>
            </div>
        </div>

        {{-- Form panel --}}
        <div class="flex-[1_1_380px] min-w-[320px] box-border px-11 py-12 flex flex-col items-center justify-center">
            <div class="w-full max-w-[340px]">

                <div class="flex bg-gray-100 rounded-[10px] p-1 gap-1 mb-6" role="tablist">
                    @foreach ($portals as $value => $label)
                        <button
                            type="button"
                            role="tab"
                            aria-selected="{{ $form->role === $value ? 'true' : 'false' }}"
                            wire:click="setRole('{{ $value }}')"
                            @class([
                                'flex-1 text-center py-2 px-1.5 rounded-lg text-[12.5px] font-bold cursor-pointer transition',
                                'bg-white text-brand shadow-sm' => $form->role === $value,
                                'bg-transparent text-gray-500 hover:text-gray-700' => $form->role !== $value,
                            ])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="font-display font-bold text-[23px] mb-1.5">{{ $this->copy['heading'] }}</div>
                <div class="text-[13px] text-gray-400 mb-6 leading-relaxed">{{ $this->copy['subtitle'] }}</div>

                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form wire:submit="login" class="flex flex-col gap-4">
                    <div>
                        <input
                            type="email"
                            wire:model="form.email"
                            placeholder="{{ $this->copy['emailPlaceholder'] }}"
                            autofocus
                            autocomplete="username"
                            class="w-full box-border h-11 px-3 rounded-[9px] bg-gray-100 text-sm border focus:outline-none focus:ring-0 {{ $errors->has('form.email') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}"
                        />
                        <x-input-error :messages="$errors->get('form.email')" class="mt-1.5 text-brand" />
                    </div>

                    <div x-data="{ show: false }">
                        <div class="relative">
                            <input
                                :type="show ? 'text' : 'password'"
                                wire:model="form.password"
                                placeholder="Password"
                                autocomplete="current-password"
                                class="w-full box-border h-11 px-3 pr-10 rounded-[9px] bg-gray-100 text-sm border focus:outline-none focus:ring-0 {{ $errors->has('form.password') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}"
                            />
                            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-gray-400 flex items-center justify-center">
                                <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.8"/></svg>
                                <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10.6 5.2A10.6 10.6 0 0112 5c6.5 0 10 6 10 6a15.6 15.6 0 01-3.3 4M6.6 6.6C4 8.3 2 12 2 12s1.6 3 4.6 4.7M9.9 14.1a3 3 0 004.2-4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('form.password')" class="mt-1.5 text-brand" />
                    </div>

                    <div class="flex items-center justify-between -mt-1.5">
                        <label class="flex items-center gap-1.5 text-[13px] text-gray-600 cursor-pointer">
                            <input wire:model="form.remember" type="checkbox" class="w-[15px] h-[15px] rounded border-gray-300 text-brand focus:ring-brand cursor-pointer" />
                            {{ __('Remember Me') }}
                        </label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" wire:navigate class="text-[13px] text-brand font-medium hover:text-brand-dark">{{ __('Forgot Password') }}</a>
                        @endif
                    </div>

                    @if ($googleMessage)
                        <div class="text-[13px] text-gray-600 bg-gray-100 rounded-lg px-3 py-2.5">{{ $googleMessage }}</div>
                    @endif

                    <button type="submit" class="h-[46px] rounded-[9px] bg-brand hover:bg-brand-dark active:bg-brand-darker text-white font-semibold text-sm mt-1 shadow-[0_2px_6px_rgba(196,18,48,0.25)] hover:shadow-[0_8px_18px_rgba(196,18,48,0.35)] hover:-translate-y-0.5 transition">
                        {{ $this->copy['submitLabel'] }}
                    </button>

                    @if ($form->role === 'public')
                        <button type="button" wire:click="showGoogleMessage" class="h-[46px] rounded-[9px] bg-white text-gray-900 border-[1.5px] border-gray-200 font-semibold text-sm flex items-center justify-center gap-2.5 shadow-sm hover:bg-gray-50 hover:border-gray-300 hover:-translate-y-0.5 transition">
                            <svg width="17" height="17" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l6-6C34.5 5.1 29.5 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.2-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l6-6C34.5 5.1 29.5 3 24 3 16.3 3 9.7 7.4 6.3 14.7z"/><path fill="#4CAF50" d="M24 45c5.4 0 10.3-2.1 14-5.5l-6.5-5.3C29.5 35.9 26.9 37 24 37c-5.2 0-9.6-3.3-11.3-8l-6.6 5.1C9.6 40.5 16.2 45 24 45z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1.1 3-3.4 5.3-6.3 6.7l6.5 5.3C39.5 36.7 45 30.9 45 24c0-1.2-.1-2.4-.4-3.5z"/></svg>
                            Sign in with Google
                        </button>
                    @endif
                </form>

                @if ($form->role === 'public')
                    <div class="mt-5 text-[13px] text-gray-400 text-center">
                        {{ __("Don't have an account yet?") }}
                        <a href="{{ route('register') }}" wire:navigate class="font-semibold text-brand">{{ __('Sign Up') }}</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
