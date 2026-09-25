<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TouchLastActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->last_active_at || $user->last_active_at->diffInMinutes(now()) >= 5)) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
