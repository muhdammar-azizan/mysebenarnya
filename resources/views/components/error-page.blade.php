@props(['code', 'title', 'message', 'icon' => '⚠️', 'retry' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} — {{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:700,800|inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-[#F0F1F3]">
        <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 py-8">
            <div class="w-full max-w-md bg-white border border-gray-100 rounded-xl shadow-sm px-6 py-12 text-center">
                <div class="w-16 h-16 rounded-full bg-brand-light text-3xl flex items-center justify-center mx-auto mb-5">{{ $icon }}</div>

                <div class="font-display font-extrabold text-6xl text-brand leading-none mb-2.5">{{ $code }}</div>
                <div class="font-bold text-lg mb-2">{{ $title }}</div>
                <div class="text-gray-500 text-sm mb-6">{{ $message }}</div>

                <div class="flex gap-3 justify-center flex-wrap">
                    <a href="{{ url('/') }}" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-[22px] py-2.5 rounded-lg">
                        {{ __('Back to Home') }}
                    </a>
                    @if ($retry)
                        <button type="button" onclick="location.reload()" class="bg-white text-gray-600 border-[1.5px] border-gray-300 hover:bg-gray-50 font-bold text-sm px-[22px] py-2.5 rounded-lg">
                            {{ __('Try Again') }}
                        </button>
                    @else
                        <button type="button" onclick="history.back()" class="bg-white text-gray-600 border-[1.5px] border-gray-300 hover:bg-gray-50 font-bold text-sm px-[22px] py-2.5 rounded-lg">
                            {{ __('Go Back') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </body>
</html>
