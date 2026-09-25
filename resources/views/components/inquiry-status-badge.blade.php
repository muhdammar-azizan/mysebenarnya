@props(['status'])

@php
    $statusEnum = $status instanceof \App\Enums\InquiryStatus ? $status : \App\Enums\InquiryStatus::tryFrom($status);
    $value = $statusEnum?->value ?? $status;
    $label = $statusEnum?->label() ?? $status;

    $styles = [
        'Submitted' => 'bg-gray-100 text-gray-600',
        'Under Investigation' => 'bg-amber-100 text-amber-700',
        'Verified True' => 'bg-green-100 text-green-700',
        'Identified Fake' => 'bg-brand-light text-brand',
        'Rejected' => 'bg-gray-200 text-gray-600',
        'Discarded' => 'bg-gray-100 text-gray-500',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-block px-2.5 py-1 rounded-full text-xs font-bold whitespace-nowrap ' . ($styles[$value] ?? 'bg-gray-100 text-gray-600')]) }}>
    {{ $label }}
</span>
