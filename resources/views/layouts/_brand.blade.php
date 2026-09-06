{{--
    The brand mark: the tenant's uploaded logo when they have one, otherwise
    their name set as a wordmark. Never an image file with a product name
    baked into it, which would show the wrong brand on a white-label
    deployment.

    @param bool   $onDark  Rendered against a dark background.
    @param string $size    Font size for the wordmark fallback.
--}}
@php
    $brandLogo = \App\Support\Brand::logo();
    $onDark = $onDark ?? false;
    $size = $size ?? '1.5rem';
@endphp

@if($brandLogo)
    <img src="{{ $brandLogo }}" alt="{{ \App\Support\Brand::name() }}"
         style="max-height: 48px; max-width: 220px;">
@else
    <span style="font-size: {{ $size }}; font-weight: 700; letter-spacing: -0.02em; white-space: nowrap; color: {{ $onDark ? '#fff' : '#1e293b' }};">
        {{ \App\Support\Brand::name() }}
    </span>
@endif
