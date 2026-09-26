@php
    $clipId = 'brandMarkClip'.\Illuminate\Support\Str::random(8);
@endphp

<svg {{ $attributes->merge(['viewBox' => '0 0 512 512']) }}>
    <defs><clipPath id="{{ $clipId }}"><circle cx="256" cy="248" r="150"/></clipPath></defs>
    <rect x="16" y="16" width="480" height="480" rx="92" ry="92" fill="#ffffff" stroke="#EDEEF0" stroke-width="4"/>
    <g clip-path="url(#{{ $clipId }})">
        <path d="M 256 248 L 256 98 A 150 150 0 0 1 386 323 Z" fill="#0072bc" opacity="0.3"/>
        <path d="M 256 248 L 386 323 A 150 150 0 0 1 126 323 Z" fill="#e31c79" opacity="0.3"/>
        <path d="M 256 248 L 126 323 A 150 150 0 0 1 256 98 Z" fill="#f5a623" opacity="0.3"/>
    </g>
    <circle cx="256" cy="248" r="192" fill="none" stroke="#C41230" stroke-width="24" stroke-dasharray="34 22" stroke-linecap="round"/>
    <circle cx="256" cy="248" r="165" fill="none" stroke="#C41230" stroke-width="24" stroke-dasharray="30 19" stroke-linecap="round"/>
    <path d="M 168 248 L 224 304 L 344 178" fill="none" stroke="#ffffff" stroke-width="70" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M 168 248 L 224 304 L 344 178" fill="none" stroke="#C41230" stroke-width="52" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
