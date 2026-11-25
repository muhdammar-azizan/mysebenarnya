<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds browser-side protections to every web response. A header that a route
 * already set itself (for example the evidence downloads) is left untouched.
 */
class SecurityHeaders
{
    /**
     * Livewire and Alpine need inline scripts and eval, so script-src cannot be
     * strict. The policy still blocks foreign scripts, plugins, framing and
     * form posts to other sites.
     */
    private const CSP = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://cdnjs.cloudflare.com",
        "font-src 'self' data: https://fonts.bunny.net",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ];

        $hsts = (int) config('security.hsts_max_age');

        if ($hsts > 0 && $request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age='.$hsts;
        }

        // The Vite dev server (public/hot) loads from another origin, so skip the policy while developing.
        if (! file_exists(public_path('hot'))) {
            $name = config('security.csp_report_only') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $headers[$name] = implode('; ', self::CSP);
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
