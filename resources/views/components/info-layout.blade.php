@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} — {{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:600,700,800|inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-[#F7F7F8] text-gray-900">
        <header class="bg-white border-b border-gray-100">
            <div class="flex items-center justify-between gap-4 px-4 sm:px-6 md:px-10 py-4 max-w-[1100px] mx-auto">
                <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                    <x-brand-mark class="w-9 h-9 flex-shrink-0" />
                    <span class="hidden sm:inline font-display font-extrabold text-lg tracking-tight">
                        <span class="text-gray-900">SE</span><span class="text-brand">BENAR</span><span class="text-gray-900">NYA.MY</span>
                    </span>
                </a>
                <nav class="flex items-center gap-4 sm:gap-6 text-sm font-semibold">
                    <a href="{{ route('about') }}" class="{{ request()->routeIs('about') ? 'text-brand' : 'text-gray-600 hover:text-gray-900' }}">{{ __('About') }}</a>
                    <a href="{{ route('track') }}" class="{{ request()->routeIs('track') ? 'text-brand' : 'text-gray-600 hover:text-gray-900' }}">{{ __('Track') }}</a>
                    <a href="{{ route('faq') }}" class="{{ request()->routeIs('faq') ? 'text-brand' : 'text-gray-600 hover:text-gray-900' }}">{{ __('FAQ') }}</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="bg-brand hover:bg-brand-dark text-white px-4 py-2 rounded-lg">{{ __('Dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="bg-brand hover:bg-brand-dark text-white px-4 py-2 rounded-lg">{{ __('Log In') }}</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="max-w-[1100px] mx-auto px-4 sm:px-6 md:px-10 py-10 md:py-14">
            {{ $slot }}
        </main>

        <footer class="border-t border-gray-100 bg-white">
            <div class="max-w-[1100px] mx-auto px-4 sm:px-6 md:px-10 py-6 text-center text-xs text-gray-400">
                {{ __('Tidak Pasti, Jangan Kongsi') }} · {{ config('app.name') }}
            </div>
        </footer>
    </body>
</html>
