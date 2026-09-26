<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Session Expired') }} — {{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:700,800|inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-[#F0F1F3]">
        <div class="min-h-screen flex items-center justify-center px-6 py-8">
            <div class="w-full max-w-[420px] bg-[#1A1A1A] rounded-xl px-6 py-10 text-center">
                <div class="w-[52px] h-[52px] rounded-full bg-white/[0.08] text-white text-2xl flex items-center justify-center mx-auto mb-4">🔒</div>
                <div class="font-bold text-base text-white mb-1.5">{{ __('Your session has expired') }}</div>
                <div class="text-gray-400 text-sm mb-5">{{ __('For your security, please log in again to continue.') }}</div>
                <a href="{{ route('login') }}" class="inline-block bg-brand hover:bg-brand-dark text-white font-bold text-sm px-[22px] py-2.5 rounded-lg">
                    {{ __('Log In Again') }}
                </a>
            </div>
        </div>
    </body>
</html>
