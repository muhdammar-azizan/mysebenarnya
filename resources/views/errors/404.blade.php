<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Page Not Found') }} — {{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:700,800|inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            @keyframes nf-pulse { 0%, 100% { transform: scale(0.9); opacity: 0.7; } 50% { transform: scale(1.05); opacity: 1; } }
            @keyframes nf-search { 0%, 100% { transform: translate(-50%,-50%) rotate(0deg); } 50% { transform: translate(-50%,-50%) rotate(-12deg); } }
            .nf-pulse { animation: nf-pulse 2.2s ease-in-out infinite; }
            .nf-search { animation: nf-search 2.6s ease-in-out infinite; }
        </style>
    </head>
    <body class="font-sans antialiased bg-[#F0F1F3]">
        <div class="min-h-screen flex items-center justify-center px-6 py-8">
            <div class="w-full max-w-md bg-white border border-gray-100 rounded-xl shadow-sm px-6 py-14 text-center overflow-hidden">
                <div class="relative w-24 h-24 mx-auto mb-5">
                    <div class="nf-pulse absolute inset-0 rounded-full bg-brand-light"></div>
                    <svg class="nf-search absolute top-1/2 left-1/2" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#C41230" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="10.5" cy="10.5" r="6.5"></circle>
                        <line x1="15.3" y1="15.3" x2="21" y2="21"></line>
                    </svg>
                </div>

                <div class="font-display font-extrabold text-6xl text-brand leading-none mb-2.5">404</div>
                <div class="font-bold text-lg mb-2">{{ __('Page Not Found') }}</div>
                <div class="text-gray-500 text-sm mb-6">{{ __("The page you're looking for doesn't exist or may have been moved.") }}</div>

                <div class="flex gap-3 justify-center flex-wrap">
                    <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-[22px] py-2.5 rounded-lg">
                        {{ __('Back to Dashboard') }}
                    </a>
                    <button type="button" onclick="history.back()" class="bg-white text-gray-600 border-[1.5px] border-gray-300 hover:bg-gray-50 font-bold text-sm px-[22px] py-2.5 rounded-lg">
                        {{ __('Go Back') }}
                    </button>
                </div>
            </div>
        </div>
    </body>
</html>
