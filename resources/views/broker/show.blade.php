@extends('layouts.broker')

@section('title', $property->address)

@section('content')
<a href="{{ route('broker.index') }}" class="bp-back">
    @include('broker._icon', ['name' => 'arrow-left', 'size' => 16])
    {{ __('Back to properties') }}
</a>

@php
    $price = $property->list_price ?: $property->asking_price ?: $property->estimated_value;
    $type = \App\Services\CustomFieldService::getOptions('property_type')[$property->property_type] ?? $property->property_type;

    $facts = array_filter([
        __('Type') => $type,
        __('Condition') => $property->condition
            ? (\App\Services\CustomFieldService::getOptions('property_condition')[$property->condition] ?? $property->condition)
            : null,
        __('Status') => $property->listing_status ? __(ucfirst($property->listing_status)) : null,
        __('Bedrooms') => $property->bedrooms,
        __('Bathrooms') => $property->bathrooms,
        __('Square Footage') => $property->square_footage ? number_format($property->square_footage) : null,
        __('Lot Size') => $property->lot_size,
        __('Year Built') => $property->year_built,
        __('Listed On') => $property->listed_at?->format('d M Y'),
    ], fn ($value) => filled($value));

    $shareText = \App\Support\PropertyShare::message($property, auth()->user());
@endphp

<div class="bp-split">
    <div>
        @if($property->images->isNotEmpty())
            @php $galleryId = 'gallery-'.$property->id; @endphp
            <img id="{{ $galleryId }}-main" class="bp-gallery__main"
                 src="{{ $property->images->first()->url() }}" alt="{{ $property->address }}">

            @if($property->images->count() > 1)
                <div class="bp-gallery__strip">
                    @foreach($property->images as $i => $image)
                        <img src="{{ $image->url() }}" alt="{{ $image->filename }}" loading="lazy"
                             class="bp-gallery__thumb {{ $i === 0 ? 'is-active' : '' }}"
                             data-gallery="{{ $galleryId }}">
                    @endforeach
                </div>
            @endif
        @else
            <div class="bp-card">
                <div class="bp-empty">
                    <span class="bp-empty__icon">@include('broker._icon', ['name' => 'building', 'size' => 30])</span>
                    <p class="bp-empty__title">{{ __('No photos for this property yet') }}</p>
                </div>
            </div>
        @endif

        <div class="bp-card bp-mt-lg">
            <div class="bp-card__body">
                <div class="bp-facts">
                    @foreach($facts as $label => $value)
                        <div>
                            <div class="bp-fact__label">{{ $label }}</div>
                            <div class="bp-fact__value">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>

                @if($property->notes)
                    <h2 class="bp-fact__label bp-mt-lg" style="margin-bottom:0.4rem;">{{ __('Notes') }}</h2>
                    <p style="white-space: pre-line;">{{ $property->notes }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- What the broker came here to do: read the price, find the place, send it
         to a client, log the one standing in front of them. --}}
    <div>
        <div class="bp-card bp-sticky">
            <div class="bp-card__body">
                <span class="bp-chip">{{ $type }}</span>

                <h1 class="bp-title" style="margin-top:0.6rem;">{{ $property->address }}</h1>
                <p class="bp-subtitle">{{ $property->city }}, {{ $property->state }} {{ $property->zip_code }}</p>

                <p class="bp-price bp-mt">{{ $price ? number_format((float) $price) : __('Price on request') }}</p>
                @if($price)
                    <p class="bp-subtitle" style="font-size:0.8125rem;">{{ __('Asking price') }}</p>
                @endif

                <div class="bp-stack bp-mt-lg">
                    <a href="{{ route('broker.leads.create', ['property' => $property->id]) }}"
                       class="bp-btn bp-btn--primary bp-btn--lg">
                        @include('broker._icon', ['name' => 'plus', 'size' => 18])
                        {{ __('Log an enquiry') }}
                    </a>
                    <a href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener noreferrer"
                       class="bp-btn bp-btn--ghost">
                        @include('broker._icon', ['name' => 'share', 'size' => 18])
                        {{ __('Send to a client') }}
                    </a>
                    <a href="{{ $property->map_link }}" target="_blank" rel="noopener noreferrer" class="bp-btn bp-btn--ghost">
                        @include('broker._icon', ['name' => 'map-pin', 'size' => 18])
                        {{ __('Open in Google Maps') }}
                    </a>
                    <button type="button" class="bp-btn bp-btn--ghost" id="copy-details"
                            data-text="{{ $shareText }}" data-done="{{ __('Copied') }}">
                        @include('broker._icon', ['name' => 'copy', 'size' => 18])
                        <span>{{ __('Copy details') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Thumbnails swap into the main image. Delegated, so it costs one listener
// however many photos a property has.
document.addEventListener('click', function (event) {
    var thumb = event.target.closest('.bp-gallery__thumb');
    if (!thumb) return;

    var main = document.getElementById(thumb.dataset.gallery + '-main');
    if (!main) return;

    main.src = thumb.src;
    thumb.parentElement.querySelectorAll('.bp-gallery__thumb')
        .forEach(function (el) { el.classList.toggle('is-active', el === thumb); });
});

document.getElementById('copy-details').addEventListener('click', function () {
    var button = this;
    var label = button.querySelector('span');
    var original = label.textContent;

    function done() {
        label.textContent = button.dataset.done;
        setTimeout(function () { label.textContent = original; }, 1800);
    }

    // navigator.clipboard needs a secure context and is missing on some older
    // Android browsers, so fall back to a hidden textarea rather than silently
    // doing nothing when a broker taps this.
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(button.dataset.text).then(done);
        return;
    }

    var area = document.createElement('textarea');
    area.value = button.dataset.text;
    area.style.cssText = 'position:fixed;top:-1000px;';
    document.body.appendChild(area);
    area.select();
    try { document.execCommand('copy'); done(); } finally { area.remove(); }
});
</script>
@endsection
