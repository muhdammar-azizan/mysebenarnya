<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:600,700,800|inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-[#EDEEF0]">
        <x-network-error-banner />
        <div class="h-screen flex flex-col overflow-hidden" x-data="{ nav: false }" @keydown.escape.window="nav = false">
            <header class="h-16 flex-shrink-0 bg-brand shadow flex items-center gap-3 sm:gap-5 px-3 sm:px-6 z-20">
                <button type="button" @click="nav = !nav" class="lg:hidden w-9 h-9 flex-shrink-0 rounded-lg bg-white/15 hover:bg-white/25 text-white flex items-center justify-center" aria-label="{{ __('Toggle menu') }}">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2 flex-shrink-0">
                    <x-brand-mark class="w-10 h-10 flex-shrink-0" />
                    <span class="hidden sm:inline font-display font-extrabold text-lg tracking-tight whitespace-nowrap">
                        <span class="text-gray-900">SE</span><span class="text-white">BENAR</span><span class="text-gray-900">NYA.MY</span>
                    </span>
                </a>

                <div class="flex-1"></div>

                <form action="{{ route('mcmc.inquiries.index') }}" method="GET" class="relative w-64 flex-shrink-0 hidden md:block">
                    <x-icon name="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input type="text" name="search" placeholder="{{ __('Search inquiries, agencies...') }}"
                        class="w-full h-9 pl-9 pr-3 text-sm rounded-lg border-0 bg-white focus:ring-2 focus:ring-white/50" />
                </form>

                <livewire:mcmc.topbar-widgets />
            </header>

            <div class="flex-1 flex min-h-0">
                <div x-show="nav" x-cloak x-transition.opacity @click="nav = false" class="fixed inset-0 top-16 bg-black/40 z-30 lg:hidden"></div>
                <div :class="nav ? 'translate-x-0' : '-translate-x-full'" @click="if ($event.target.closest('a')) nav = false"
                    class="fixed top-16 bottom-0 left-0 z-40 flex transition-transform duration-200 lg:static lg:translate-x-0 lg:z-auto">
                    <x-mcmc-sidebar />
                </div>

                <div class="flex-1 min-w-0 min-h-0 p-2 sm:p-4 flex">
                    <main class="flex-1 bg-white rounded-2xl shadow-sm overflow-auto p-4 sm:p-7 min-w-0">
                        @if (isset($header))
                            <div class="mb-6 max-w-[1280px] mx-auto w-full">{{ $header }}</div>
                        @endif

                        <div class="max-w-[1280px] mx-auto w-full">{{ $slot }}</div>
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
