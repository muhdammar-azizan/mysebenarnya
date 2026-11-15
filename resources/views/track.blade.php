@php
    use App\Enums\InquiryStatus;

    $explanations = [
        InquiryStatus::Submitted->value => __('Your inquiry has been received and is waiting for MCMC to review it.'),
        InquiryStatus::UnderInvestigation->value => __('An agency is investigating this inquiry.'),
        InquiryStatus::VerifiedTrue->value => __('The claim was checked and found to be accurate.'),
        InquiryStatus::IdentifiedFake->value => __('The claim was checked and found false or misleading.'),
        InquiryStatus::Rejected->value => __('This inquiry could not be verified.'),
        InquiryStatus::Discarded->value => __('This inquiry was closed as not a serious inquiry.'),
    ];
@endphp

<x-info-layout :title="__('Track an Inquiry')">
    <div class="max-w-xl mx-auto">
        <h1 class="font-display font-extrabold text-3xl text-gray-900 mb-2">{{ __('Track an Inquiry') }}</h1>
        <p class="text-gray-600 text-sm mb-6">{{ __('Enter the reference number you received when you submitted your inquiry to see its current status.') }}</p>

        <form method="GET" action="{{ route('track') }}" class="flex flex-col sm:flex-row gap-3 mb-6">
            <input type="text" name="ref" value="{{ $reference }}" placeholder="INQ-2026-00001" maxlength="20" autocomplete="off"
                class="flex-1 rounded-lg border-gray-300 text-sm uppercase focus:border-brand focus:ring-brand" />
            <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-6 py-2.5 rounded-lg">{{ __('Check Status') }}</button>
        </form>

        @if ($error)
            <div class="bg-brand-light border border-red-100 text-brand text-sm font-semibold rounded-xl px-4 py-3">{{ $error }}</div>
        @endif

        @if ($inquiry)
            <div class="bg-white border border-gray-100 rounded-2xl p-6">
                <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Reference No.') }}</div>
                <div class="font-display font-extrabold text-xl text-gray-900 mt-0.5 mb-4">{{ $inquiry->reference_no }}</div>

                <x-inquiry-status-badge :status="$inquiry->status" />
                <p class="text-sm text-gray-600 mt-3">{{ $explanations[$inquiry->status->value] ?? '' }}</p>

                <div class="flex gap-8 flex-wrap mt-5 pt-5 border-t border-gray-100">
                    <div>
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Date Submitted') }}</div>
                        <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->created_at->format('d M Y') }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Last Updated') }}</div>
                        <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $inquiry->updated_at->format('d M Y') }}</div>
                    </div>
                </div>

                <p class="text-xs text-gray-400 mt-5">
                    {{ __('For the full details and result, log in to your account.') }}
                    <a href="{{ route('login') }}" class="text-brand font-semibold hover:underline">{{ __('Log In') }}</a>
                </p>
            </div>
        @endif
    </div>
</x-info-layout>
