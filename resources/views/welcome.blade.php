<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }} — Tidak Pasti, Jangan Kongsi</title>
        <meta name="description" content="Report suspicious news or claims circulating online. MCMC reviews every submission and routes it to the right government agency for official verification.">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:600,700,800|inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-[#F7F7F8] text-gray-900">

        {{-- NAVBAR --}}
        <div class="flex items-center justify-between px-6 md:px-10 py-4 max-w-[1280px] mx-auto">
            <div class="flex items-center gap-2.5">
                <x-brand-mark class="w-9 h-9 flex-shrink-0" />
                <div class="font-display font-extrabold text-lg tracking-tight">
                    <span class="text-gray-900">SE</span><span class="text-brand">BENAR</span><span class="text-gray-900">NYA.MY</span>
                </div>
            </div>
            <div class="hidden md:flex items-center gap-7">
                <a href="#how" class="text-gray-600 text-sm font-semibold hover:text-gray-900">{{ __('How it works') }}</a>
                <a href="#about" class="text-gray-600 text-sm font-semibold hover:text-gray-900">{{ __('About') }}</a>
                <a href="{{ route('login') }}" class="text-gray-600 text-sm font-semibold px-[18px] py-2 rounded-lg hover:text-gray-900">{{ __('Log In') }}</a>
                <a href="{{ route('register') }}" class="bg-brand hover:bg-brand-dark text-white text-sm font-semibold px-5 py-2.5 rounded-lg">{{ __('Register') }}</a>
            </div>
            <a href="{{ route('login') }}" class="md:hidden text-brand text-sm font-semibold">{{ __('Log In') }}</a>
        </div>

        {{-- HERO --}}
        <div class="relative mx-4 md:mx-10 rounded-3xl overflow-hidden max-w-[1200px] md:mx-auto min-h-[420px] md:min-h-[560px] flex items-center">
            <div class="absolute inset-0 z-0">
                <img src="{{ asset('images/landing-hero-mcmc-office.png') }}" alt="MCMC office signage" class="w-full h-full object-cover block" />
                <div class="absolute inset-0" style="background: linear-gradient(115deg, rgba(15,15,17,0.92) 0%, rgba(15,15,17,0.78) 42%, rgba(20,10,12,0.35) 75%, rgba(20,10,12,0.15) 100%);"></div>
            </div>
            <div class="relative z-10 px-7 md:px-16 py-16 md:py-20 max-w-2xl">
                <div class="inline-flex items-center gap-2 bg-white/[0.12] border border-white/25 text-white text-xs font-semibold px-3.5 py-1.5 rounded-full mb-6 backdrop-blur-sm">
                    {{ __('Official MCMC Verification Platform') }}
                </div>
                <h1 class="font-display font-extrabold text-4xl md:text-5xl leading-tight mb-5 text-white tracking-tight">
                    {{ __('Tidak Pasti,') }}<br>{{ __('Jangan Kongsi.') }}
                </h1>
                <p class="text-base md:text-lg leading-relaxed text-white/80 mb-9 max-w-lg">
                    {{ __('Report suspicious news or claims circulating online. MCMC reviews every submission and routes it to the right government agency for official verification — so Malaysians can trust what they share.') }}
                </p>
                <div class="flex gap-3.5 flex-wrap">
                    <a href="{{ route('login') }}" class="bg-brand hover:bg-brand-dark text-white font-semibold text-[15px] px-7 py-3.5 rounded-lg">{{ __('Log In') }}</a>
                    <a href="{{ route('register') }}" class="bg-transparent text-white border-[1.5px] border-white/55 hover:border-white font-semibold text-[15px] px-7 py-3.5 rounded-lg">{{ __('Create an Account') }}</a>
                </div>
            </div>
        </div>

        {{-- STATS STRIP --}}
        <div class="max-w-[1200px] mx-auto px-4 md:px-10 pt-10 md:pt-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white border border-gray-100 rounded-2xl px-7 py-6">
                    <div class="font-display font-extrabold text-3xl md:text-[34px] text-gray-900">4,812</div>
                    <div class="text-[13.5px] text-gray-400 mt-1.5 font-medium">{{ __('Inquiries submitted by the public') }}</div>
                </div>
                <div class="bg-white border border-gray-100 rounded-2xl px-7 py-6">
                    <div class="font-display font-extrabold text-3xl md:text-[34px] text-green-700">3,940</div>
                    <div class="text-[13.5px] text-gray-400 mt-1.5 font-medium">{{ __('Cases verified by an agency') }}</div>
                </div>
                <div class="bg-white border border-gray-100 rounded-2xl px-7 py-6">
                    <div class="font-display font-extrabold text-3xl md:text-[34px] text-gray-900">21</div>
                    <div class="text-[13.5px] text-gray-400 mt-1.5 font-medium">{{ __('Government agencies onboard') }}</div>
                </div>
            </div>
        </div>

        {{-- HOW IT WORKS --}}
        <div id="how" class="max-w-[1200px] mx-auto px-4 md:px-10 py-16 md:py-24">
            <div class="text-center max-w-xl mx-auto mb-12 md:mb-14">
                <div class="text-xs font-bold text-brand uppercase tracking-widest mb-3">{{ __('How It Works') }}</div>
                <h2 class="font-display font-bold text-2xl md:text-[32px] mb-3 tracking-tight">{{ __('From tip to truth, in three steps') }}</h2>
                <p class="text-gray-500 text-[15.5px] leading-relaxed">{{ __('Every submission is reviewed by trained officers before it reaches an agency — no claim gets verified without proper jurisdiction.') }}</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white border border-gray-100 rounded-2xl p-7">
                    <div class="w-12 h-12 rounded-xl bg-brand-light flex items-center justify-center mb-5">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 4h16v12H7l-3 3V4z" stroke="#C41230" stroke-width="2" stroke-linejoin="round"/></svg>
                    </div>
                    <div class="font-display font-bold text-xs text-brand mb-2">{{ __('STEP 1') }}</div>
                    <div class="font-display font-semibold text-lg mb-2.5">{{ __('Submit a report') }}</div>
                    <div class="text-gray-500 text-sm leading-relaxed">{{ __("Share the suspicious news, link, or media you've encountered, with any supporting evidence.") }}</div>
                </div>
                <div class="bg-white border border-gray-100 rounded-2xl p-7">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center mb-5">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#B87613" stroke-width="2"/><path d="M12 7v5l3 3" stroke="#B87613" stroke-width="2" stroke-linecap="round"/></svg>
                    </div>
                    <div class="font-display font-bold text-xs text-[#B87613] mb-2">{{ __('STEP 2') }}</div>
                    <div class="font-display font-semibold text-lg mb-2.5">{{ __('MCMC reviews & assigns') }}</div>
                    <div class="text-gray-500 text-sm leading-relaxed">{{ __('Our officers triage the report and route it to the government agency with the right jurisdiction.') }}</div>
                </div>
                <div class="bg-white border border-gray-100 rounded-2xl p-7">
                    <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center mb-5">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M5 12l4 4L19 6" stroke="#1E8E5A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div class="font-display font-bold text-xs text-green-700 mb-2">{{ __('STEP 3') }}</div>
                    <div class="font-display font-semibold text-lg mb-2.5">{{ __('Agency verifies') }}</div>
                    <div class="text-gray-500 text-sm leading-relaxed">{{ __('The agency investigates and issues an official finding — verified true, or identified as fake.') }}</div>
                </div>
            </div>
        </div>

        {{-- ABOUT / WHY --}}
        <div id="about" class="bg-white border-y border-gray-100">
            <div class="max-w-[1200px] mx-auto px-4 md:px-10 py-16 md:py-20 grid grid-cols-1 md:grid-cols-2 gap-10 md:gap-16 items-center">
                <div>
                    <div class="text-xs font-bold text-brand uppercase tracking-widest mb-3">{{ __('Why It Matters') }}</div>
                    <h2 class="font-display font-bold text-2xl md:text-[30px] mb-4 tracking-tight">{{ __('A trusted channel between citizens and government') }}</h2>
                    <p class="text-gray-500 text-[15px] leading-relaxed mb-6">{{ __("Misinformation spreads fastest in the first few hours. SEBENARNYA.MY gives every Malaysian a direct, official way to flag a suspicious claim and get it checked by the agency actually responsible for it — not a crowd, not a guess.") }}</p>
                    <div class="flex flex-col gap-3.5">
                        @foreach ([
                            __('Reports are triaged by trained MCMC officers, not automated filters.'),
                            __('Findings come from the government agency with actual jurisdiction over the claim.'),
                            __('Submitters are notified the moment a decision is reached.'),
                        ] as $point)
                            <div class="flex gap-3 items-start">
                                <div class="w-5 h-5 rounded-full bg-brand-light flex-shrink-0 flex items-center justify-center mt-0.5">
                                    <div class="w-1.5 h-1.5 rounded-full bg-brand"></div>
                                </div>
                                <div class="text-[14.5px] text-gray-600 leading-snug">{{ $point }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <img src="{{ asset('images/landing-about-phones.webp') }}" alt="Citizens engaging with digital content" class="w-full h-[380px] object-cover block rounded-[20px]" />
                </div>
            </div>
        </div>

        {{-- CTA BAND --}}
        <div class="max-w-[1200px] mx-auto px-4 md:px-10 py-16 md:py-24 text-center">
            <h2 class="font-display font-bold text-2xl md:text-[30px] mb-3.5 tracking-tight">{{ __("Seen something that doesn't look right?") }}</h2>
            <p class="text-gray-500 text-[15.5px] mb-8 max-w-md mx-auto">{{ __('Create a free account to submit a report, or log in if you already have one.') }}</p>
            <div class="flex gap-3.5 justify-center flex-wrap">
                <a href="{{ route('register') }}" class="bg-brand hover:bg-brand-dark text-white font-semibold text-[15px] px-[30px] py-3.5 rounded-lg">{{ __('Create an Account') }}</a>
                <a href="{{ route('login') }}" class="bg-transparent text-brand border-[1.5px] border-brand hover:bg-brand-light font-semibold text-[15px] px-[30px] py-3.5 rounded-lg">{{ __('Log In') }}</a>
            </div>
        </div>

        {{-- FOOTER --}}
        <div class="bg-gray-900 px-6 md:px-10 py-10">
            <div class="max-w-[1200px] mx-auto flex flex-col md:flex-row justify-between items-center gap-5">
                <div class="flex items-center gap-2.5">
                    <x-brand-mark class="w-7 h-7 flex-shrink-0" />
                    <div class="font-display font-bold text-sm text-white">SEBENARNYA.MY</div>
                </div>
                <div class="text-white/50 text-[13px] text-center">{{ __('A public verification service by the Malaysian Communications and Multimedia Commission (MCMC).') }}</div>
                <div class="text-white/40 text-[12.5px]">{{ __('© :year MCMC. All rights reserved.', ['year' => date('Y')]) }}</div>
            </div>
        </div>

    </body>
</html>
