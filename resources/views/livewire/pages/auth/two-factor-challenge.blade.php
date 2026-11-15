<?php

use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth')] class extends Component
{
    /** How long a user has to enter the code after their password was accepted. */
    private const WINDOW_SECONDS = 600;

    public string $code = '';

    public bool $useRecovery = false;

    public function mount(): void
    {
        if (! $this->pendingUser()) {
            Session::forget('two_factor');

            $this->redirect(route('login', absolute: false), navigate: true);
        }
    }

    public function toggleMode(): void
    {
        $this->useRecovery = ! $this->useRecovery;
        $this->code = '';
        $this->resetErrorBag();
    }

    public function verify(TwoFactorAuthentication $twoFactor): void
    {
        $this->validate(['code' => ['required', 'string', 'max:32']]);

        $user = $this->pendingUser();

        if (! $user) {
            Session::forget('two_factor');
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $key = 'two-factor:'.$user->id.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => trans('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($key),
                    'minutes' => ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }

        $accepted = $this->useRecovery
            ? $user->useRecoveryCode($twoFactor->normalizeRecoveryCode($this->code))
            : $this->acceptCode($twoFactor, $user);

        if (! $accepted) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'code' => $this->useRecovery
                    ? __('That recovery code is not valid.')
                    : __('That code is not valid. Please try again.'),
            ]);
        }

        RateLimiter::clear($key);

        $remember = (bool) Session::get('two_factor.remember', false);
        Session::forget('two_factor');

        Auth::login($user, $remember);
        Session::regenerate();

        if ($this->useRecovery) {
            Log::info('Two-factor recovery code used to sign in.', ['user_id' => $user->id]);
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    private function acceptCode(TwoFactorAuthentication $twoFactor, User $user): bool
    {
        $timestamp = $twoFactor->verify($user->two_factor_secret, $this->code, $user->two_factor_last_timestamp);

        if ($timestamp === null) {
            return false;
        }

        $user->forceFill(['two_factor_last_timestamp' => $timestamp])->save();

        return true;
    }

    private function pendingUser(): ?User
    {
        $pending = Session::get('two_factor');

        if (! is_array($pending) || ! isset($pending['id'], $pending['started_at'])) {
            return null;
        }

        if (now()->timestamp - (int) $pending['started_at'] > self::WINDOW_SECONDS) {
            return null;
        }

        $user = User::find($pending['id']);

        return $user && $user->canUseTwoFactor() && $user->hasTwoFactorEnabled() ? $user : null;
    }
}; ?>

<div class="min-h-screen box-border bg-[#F0F1F3] flex items-center justify-center p-4 sm:p-6">
    <div class="w-full max-w-[400px] bg-white rounded-2xl border border-gray-100 shadow-[0_4px_24px_rgba(0,0,0,0.06)] px-6 sm:px-8 py-9">

        <div class="flex flex-col items-center text-center mb-6">
            <div class="w-[52px] h-[52px] rounded-full bg-brand-light flex items-center justify-center mb-[18px]">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect x="5" y="10" width="14" height="10" rx="2" stroke="#C41230" stroke-width="2"/>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="#C41230" stroke-width="2"/>
                    <circle cx="12" cy="15" r="1.4" fill="#C41230"/>
                </svg>
            </div>
            <div class="font-display font-bold text-xl mb-2">{{ __('Two-Factor Authentication') }}</div>
            <p class="text-sm text-gray-500 leading-relaxed max-w-[290px]">
                @if ($useRecovery)
                    {{ __('Enter one of your recovery codes. Each code can be used only once.') }}
                @else
                    {{ __('Enter the 6-digit code from your authenticator app.') }}
                @endif
            </p>
        </div>

        <form wire:submit="verify" class="flex flex-col gap-[18px]">
            <div>
                <label for="code" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
                    {{ $useRecovery ? __('Recovery code') : __('Authentication code') }}
                </label>
                <input id="code" type="text" wire:model="code" required autofocus autocomplete="one-time-code"
                    @if (! $useRecovery) inputmode="numeric" maxlength="7" placeholder="123456" @else placeholder="xxxxx-xxxxx" @endif
                    class="w-full box-border px-3.5 py-3 rounded-lg text-sm tracking-widest border focus:outline-none focus:ring-0 {{ $errors->has('code') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}" />
                <x-input-error :messages="$errors->get('code')" class="mt-1.5 text-brand" />
            </div>

            <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm py-3 rounded-lg">
                {{ __('Verify') }}
            </button>
        </form>

        <div class="flex items-center justify-between mt-5 text-sm">
            <button type="button" wire:click="toggleMode" class="text-brand font-semibold hover:underline">
                {{ $useRecovery ? __('Use authenticator code') : __('Use a recovery code') }}
            </button>
            <a href="{{ route('login') }}" wire:navigate class="text-gray-500 hover:text-gray-700">{{ __('Back to login') }}</a>
        </div>
    </div>
</div>
