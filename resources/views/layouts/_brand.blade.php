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

@php $wordmarkId = 'brand-wordmark-'.\Illuminate\Support\Str::random(6); @endphp

<span id="{{ $wordmarkId }}" @if($brandLogo) hidden @endif
      style="font-size: {{ $size }}; font-weight: 700; letter-spacing: -0.02em; white-space: nowrap; color: {{ $onDark ? '#fff' : '#1e293b' }};">
    {{ \App\Support\Brand::name() }}
</span>

@if($brandLogo)
    {{-- A stored logo can be unreachable (a file lost with an ephemeral disk,
         storage reconfigured). Fall back to the wordmark rather than leaving a
         broken image where the brand should be. --}}
    <img src="{{ $brandLogo }}" alt="{{ \App\Support\Brand::name() }}"
         style="max-height: 48px; max-width: 220px;"
         onerror="this.remove(); document.getElementById('{{ $wordmarkId }}').hidden = false;">
@endif
