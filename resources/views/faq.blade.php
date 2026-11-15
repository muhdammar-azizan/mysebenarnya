@php
    $faqs = [
        [__('Who can submit an inquiry?'), __('Anyone with a registered and email-verified public account can submit an inquiry.')],
        [__('What should I include?'), __('A clear title, a description of where you saw it, and at least one piece of supporting evidence: an image, a PDF, or a link to the original post.')],
        [__('How long does verification take?'), __('It depends on the claim and the agency. You can follow the status of each inquiry in My Inquiries and you will be notified when it changes.')],
        [__('Who investigates my inquiry?'), __('MCMC reviews it first and assigns it to the government ministry or agency responsible for that subject, for example health claims go to the health ministry.')],
        [__('Can other people see my name?'), __('No. Public inquiries never show the name or email of the person who submitted them.')],
        [__('What do the results mean?'), __('Verified True means the claim is accurate. Identified Fake means it is false or misleading. Rejected or Discarded means it could not be verified or was not a serious inquiry.')],
        [__('I forgot my password. What do I do?'), __('Use the "Forgot password" link on the login page. MCMC and agency staff should contact their administrator.')],
    ];
    $tips = [
        __('Check the source: is it from an official or well-known outlet?'),
        __('Look at the date: old stories are often shared as if they are new.'),
        __('Be careful with shocking or emotional headlines that push you to share quickly.'),
        __('Search for the same story on trusted news sites before believing it.'),
        __('When in doubt, do not share. Submit it here instead.'),
    ];
@endphp

<x-info-layout :title="__('FAQ')">
    <h1 class="font-display font-extrabold text-3xl md:text-4xl text-gray-900 mb-3">{{ __('Frequently Asked Questions') }}</h1>
    <p class="text-gray-600 text-base mb-8">{{ __('Quick answers about using MySebenarnya.') }}</p>

    <div class="flex flex-col gap-3 mb-14">
        @foreach ($faqs as [$q, $a])
            <details class="group bg-white border border-gray-100 rounded-xl px-5 py-4">
                <summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-semibold text-gray-900 text-sm">
                    {{ $q }}
                    <span class="text-brand text-lg leading-none transition-transform group-open:rotate-45">+</span>
                </summary>
                <p class="text-sm text-gray-600 leading-relaxed mt-3">{{ $a }}</p>
            </details>
        @endforeach
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-6 md:p-8 mb-10">
        <h2 class="font-display font-bold text-xl text-gray-900 mb-4">{{ __('How to spot fake news') }}</h2>
        <ul class="flex flex-col gap-3 text-sm text-gray-600 list-disc pl-5">
            @foreach ($tips as $tip)
                <li>{{ $tip }}</li>
            @endforeach
        </ul>
    </div>

    <a href="{{ route('register') }}" class="inline-block bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-3 rounded-lg">{{ __('Report something suspicious') }}</a>
</x-info-layout>
