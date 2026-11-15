<x-info-layout :title="__('About')">
    <h1 class="font-display font-extrabold text-3xl md:text-4xl text-gray-900 mb-3">{{ __('About MySebenarnya') }}</h1>
    <p class="text-gray-600 text-base leading-relaxed max-w-2xl mb-10">
        {{ __('MySebenarnya is a platform by the Malaysian Communications and Multimedia Commission (MCMC) that lets you check whether news or claims circulating online are true. You report it, the right government agency investigates, and the verified answer is shared with everyone.') }}
    </p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-12">
        @foreach ([
            ['1', __('You report'), __('Submit the news, message or link you are unsure about, together with supporting evidence.')],
            ['2', __('MCMC routes it'), __('MCMC reviews every submission and assigns it to the government ministry or agency best placed to verify it.')],
            ['3', __('Agency verifies'), __('The agency investigates and gives an official result, which you can follow and everyone can read.')],
        ] as [$n, $head, $body])
            <div class="bg-white border border-gray-100 rounded-2xl p-6">
                <div class="w-9 h-9 rounded-full bg-brand text-white font-bold flex items-center justify-center mb-4">{{ $n }}</div>
                <div class="font-bold text-gray-900 mb-1.5">{{ $head }}</div>
                <p class="text-sm text-gray-500 leading-relaxed">{{ $body }}</p>
            </div>
        @endforeach
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-6 md:p-8 mb-12">
        <h2 class="font-display font-bold text-xl text-gray-900 mb-4">{{ __('Possible results') }}</h2>
        <ul class="flex flex-col gap-3 text-sm text-gray-600">
            <li><span class="font-bold text-green-700">{{ __('Verified True') }}</span> — {{ __('the claim was checked and found to be accurate.') }}</li>
            <li><span class="font-bold text-brand">{{ __('Identified Fake') }}</span> — {{ __('the claim was checked and found false or misleading.') }}</li>
            <li><span class="font-bold text-gray-700">{{ __('Rejected / Discarded') }}</span> — {{ __('the submission could not be verified or was not a serious inquiry.') }}</li>
        </ul>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-6 md:p-8 mb-12">
        <h2 class="font-display font-bold text-xl text-gray-900 mb-3">{{ __('Your privacy') }}</h2>
        <p class="text-sm text-gray-600 leading-relaxed">{{ __('Public inquiries show only the claim, its category and the result. Your name and email are never shown to other users.') }}</p>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('register') }}" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-3 rounded-lg">{{ __('Register') }}</a>
        <a href="{{ route('faq') }}" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold text-sm px-6 py-3 rounded-lg">{{ __('Read the FAQ') }}</a>
    </div>
</x-info-layout>
