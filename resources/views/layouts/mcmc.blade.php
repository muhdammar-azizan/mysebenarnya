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
        <div class="h-screen flex flex-col overflow-hidden">
            <header class="h-16 flex-shrink-0 bg-brand shadow flex items-center gap-5 px-6 z-20">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2 flex-shrink-0">
                    <div class="w-9 h-9 rounded-lg bg-white border border-black/5 relative flex-shrink-0">
                        <div class="absolute inset-[3px] rounded-full border-2 border-dashed border-brand"></div>
                    </div>
                    <span class="font-display font-extrabold text-lg tracking-tight whitespace-nowrap">
                        <span class="text-gray-900">SE</span><span class="text-white">BENAR</span><span class="text-gray-900">NYA.MY</span>
                    </span>
                </a>

                <form action="{{ route('mcmc.inquiries.index') }}" method="GET" class="flex-1 max-w-md">
                    <input type="text" name="search" placeholder="{{ __('Search inquiries, agencies...') }}"
                        class="w-full text-sm rounded-lg border-0 bg-white/95 focus:ring-2 focus:ring-white/50" />
                </form>

                <div class="flex-1"></div>

                <livewire:mcmc.topbar-widgets />
            </header>

            <div class="flex-1 flex gap-4 min-h-0 p-4">
                <x-mcmc-sidebar />

                <main class="flex-1 bg-white rounded-2xl shadow-sm overflow-auto p-7 min-w-0">
                    @if (isset($header))
                        <div class="mb-6">{{ $header }}</div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
