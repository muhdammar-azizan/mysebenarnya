<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * One place for the abuse limits that guard actions outside plain routes
 * (Livewire actions cannot use the `throttle` route middleware).
 *
 * Limits live in config/security.php as [attempts, seconds]. They are counted
 * per visitor IP, never per account, so a shared demo account cannot be used
 * to lock everyone else out.
 */
class Throttle
{
    /**
     * Count one attempt. Returns how many seconds the visitor must wait when
     * the limit has been exceeded, or null while they are still allowed.
     */
    public static function limited(string $name, ?string $by = null): ?int
    {
        [$max, $decaySeconds] = config("security.limits.{$name}");

        $key = $name.'|'.($by ?? self::visitor());

        if (RateLimiter::tooManyAttempts($key, $max)) {
            return RateLimiter::availableIn($key);
        }

        RateLimiter::hit($key, $decaySeconds);

        return null;
    }

    /** IP plus signed-in user: for actions that only signed-in people can reach. */
    public static function visitor(): string
    {
        return self::ip().'|'.(auth()->id() ?? 'guest');
    }

    /** Visitor IP only: for sign-in, sign-up and password flows, which happen before any account is known. */
    public static function ip(): string
    {
        return (string) request()->ip();
    }

    public static function message(int $seconds): string
    {
        return __('Too many attempts. Please try again in about :minutes minute(s).', [
            'minutes' => max(1, (int) ceil($seconds / 60)),
        ]);
    }

    /** Show the limit as an error under a form field. */
    public static function enforce(string $name, string $errorKey, ?string $by = null): void
    {
        if (($wait = self::limited($name, $by)) !== null) {
            throw ValidationException::withMessages([$errorKey => self::message($wait)]);
        }
    }

    /** Answer with the "Too Many Requests" page, for actions that have no field to show an error on. */
    public static function abortIfLimited(string $name, ?string $by = null): void
    {
        if (($wait = self::limited($name, $by)) !== null) {
            throw new TooManyRequestsHttpException($wait);
        }
    }
}
