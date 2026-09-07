@extends('layouts.broker')

@section('title', __('Properties'))

@section('content')
<div class="bp-mb">
    <h1 class="bp-title">{{ __('Available Properties') }}</h1>
    <p class="bp-subtitle">
        {{ trans_choice(':count property shared with you|:count properties shared with you', $properties->total(), ['count' => $properties->total()]) }}
    </p>
</div>

<form method="GET" action="{{ route('broker.index') }}" class="bp-toolbar bp-mb">
    <div class="bp-toolbar__search">
        @include('broker._icon', ['name' => 'search', 'size' => 16])
        <input type="search" name="search" class="bp-input"
               placeholder="{{ __('Search address, city, ZIP...') }}" value="{{ request('search') }}"
               aria-label="{{ __('Search properties') }}">
    </div>
    <select name="property_type" class="bp-select" style="flex:0 1 180px;" aria-label="{{ __('Property type') }}">
        <option value="">{{ __('All types') }}</option>
        @foreach(\App\Services\CustomFieldService::getOptions('property_type') as $slug => $label)
            <option value="{{ $slug }}" @selected(request('property_type') === $slug)>{{ $label }}</option>
        @endforeach
    </select>
    <button type="submit" class="bp-btn bp-btn--primary">{{ __('Search') }}</button>
    @if(request()->hasAny(['search', 'property_type']))
        <a href="{{ route('broker.index') }}" class="bp-btn bp-btn--ghost">{{ __('Clear') }}</a>
    @endif
</form>

@if($properties->isEmpty())
    <div class="bp-card">
        <div class="bp-empty">
            <span class="bp-empty__icon">@include('broker._icon', ['name' => 'building', 'size' => 30])</span>
            @if(request()->hasAny(['search', 'property_type']))
                <p class="bp-empty__title">{{ __('Nothing matched that search') }}</p>
                <p class="bp-empty__text">{{ __('Try a different address, city or type.') }}</p>
                <a href="{{ route('broker.index') }}" class="bp-btn bp-btn--ghost bp-mt">{{ __('Show all properties') }}</a>
            @else
                <p class="bp-empty__title">{{ __('No properties yet') }}</p>
                <p class="bp-empty__text">{{ __('Properties will appear here as soon as they are shared with you.') }}</p>
            @endif
        </div>
    </div>
@else
    <div class="bp-grid">
        @foreach($properties as $property)
            @php
                $price = $property->list_price ?: $property->asking_price ?: $property->estimated_value;
                $type = \App\Services\CustomFieldService::getOptions('property_type')[$property->property_type] ?? $property->property_type;
                $shareText = \App\Support\PropertyShare::message($property, auth()->user());
            @endphp
            <article class="bp-property">
                <a href="{{ route('broker.show', $property) }}" class="bp-property__media" aria-label="{{ $property->address }}">
                    @if($property->primaryImage)
                        <img src="{{ $property->primaryImage->url() }}" alt="{{ $property->address }}" loading="lazy">
                    @else
                        <span class="bp-property__placeholder">
                            @include('broker._icon', ['name' => 'building', 'size' => 48])
                        </span>
                    @endif

                    <span class="bp-property__tags">
                        <span class="bp-chip bp-chip--solid">{{ $type }}</span>
                    </span>
                    <span class="bp-property__price">
                        {{ $price ? number_format((float) $price) : __('Price on request') }}
                    </span>
                </a>

                <div class="bp-property__body">
                    <h2 class="bp-property__address">
                        <a href="{{ route('broker.show', $property) }}">{{ $property->address }}</a>
                    </h2>
                    <p class="bp-property__city">{{ $property->city }}, {{ $property->state }} {{ $property->zip_code }}</p>

                    @if($property->listing_status)
                        <div class="bp-mb" style="margin-bottom:0.6rem;">
                            <span class="bp-chip bp-chip--ok">{{ __(ucfirst($property->listing_status)) }}</span>
                        </div>
                    @endif

                    <div class="bp-specs">
                        @if($property->bedrooms)
                            <span class="bp-spec">
                                @include('broker._icon', ['name' => 'bed', 'size' => 16])
                                {{ trans_choice(':count bed|:count beds', $property->bedrooms, ['count' => $property->bedrooms]) }}
                            </span>
                        @endif
                        @if($property->bathrooms)
                            <span class="bp-spec">
                                @include('broker._icon', ['name' => 'bath', 'size' => 16])
                                {{ trans_choice(':count bath|:count baths', $property->bathrooms, ['count' => $property->bathrooms]) }}
                            </span>
                        @endif
                        @if($property->square_footage)
                            <span class="bp-spec">
                                @include('broker._icon', ['name' => 'ruler', 'size' => 16])
                                {{ number_format($property->square_footage) }} {{ __('sq ft') }}
                            </span>
                        @endif
                    </div>

                    <div class="bp-row bp-mt" style="gap:0.4rem;">
                        <a href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener noreferrer"
                           class="bp-btn bp-btn--ghost bp-btn--sm bp-fill">
                            @include('broker._icon', ['name' => 'share', 'size' => 15])
                            {{ __('Share') }}
                        </a>
                        <a href="{{ route('broker.leads.create', ['property' => $property->id]) }}"
                           class="bp-btn bp-btn--primary bp-btn--sm bp-fill">
                            @include('broker._icon', ['name' => 'plus', 'size' => 15])
                            {{ __('Enquiry') }}
                        </a>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@endif

@if($properties->hasPages())
    <div class="bp-mt-lg">{{ $properties->links() }}</div>
@endif
@endsection
