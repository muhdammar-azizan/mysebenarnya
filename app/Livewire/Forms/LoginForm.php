<?php

namespace App\Livewire\Forms;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string|in:public,mcmc_staff,agency_staff')]
    public string $role = 'public';

    /**
     * Login identifier: an email for public users, a username for MCMC and
     * agency staff. Kept as `email` so existing bindings keep working.
     */
    #[Validate('required|string')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $isStaff = in_array($this->role, ['mcmc_staff', 'agency_staff'], true);

        if (! $isStaff && ! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'form.email' => __('Please enter a valid email address.'),
            ]);
        }

        $credentials = $isStaff
            ? ['username' => $this->email, 'password' => $this->password]
            : ['email' => $this->email, 'password' => $this->password];

        if (! Auth::attempt($credentials, $this->remember)) {
            $this->hitFailure();

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        if (Auth::user()->role->value !== $this->role) {
            Auth::logout();
            $this->hitFailure();

            throw ValidationException::withMessages([
                'form.email' => __('These credentials do not match the selected portal.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        // Two limits: this account from this IP, and failures from this IP overall
        // (so trying a different username each time does not get around the first).
        $key = match (true) {
            RateLimiter::tooManyAttempts($this->throttleKey(), 5) => $this->throttleKey(),
            RateLimiter::tooManyAttempts($this->ipThrottleKey(), config('security.limits.login_ip')[0]) => $this->ipThrottleKey(),
            default => null,
        };

        if ($key === null) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    protected function ipThrottleKey(): string
    {
        return 'login_ip|'.request()->ip();
    }

    /** Count one failed sign-in against both limits. */
    protected function hitFailure(): void
    {
        RateLimiter::hit($this->throttleKey());
        RateLimiter::hit($this->ipThrottleKey(), config('security.limits.login_ip')[1]);
    }
}
