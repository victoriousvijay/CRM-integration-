@extends('layouts.broker')

@section('title', __('Properties'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h2 class="mb-0">{{ __('Available Properties') }}</h2>
        <p class="text-secondary mb-0">
            {{ trans_choice(':count property available|:count properties available', $properties->total(), ['count' => $properties->total()]) }}
        </p>
    </div>

    <form method="GET" action="{{ route('broker.index') }}" class="d-flex gap-2 flex-wrap">
        <input type="search" name="search" class="form-control" style="min-width:220px;"
               placeholder="{{ __('Search address, city, ZIP...') }}" value="{{ request('search') }}">
        <select name="property_type" class="form-select" style="min-width:170px;">
            <option value="">{{ __('All types') }}</option>
            @foreach(\App\Services\CustomFieldService::getOptions('property_type') as $slug => $label)
                <option value="{{ $slug }}" @selected(request('property_type') === $slug)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
        @if(request()->hasAny(['search', 'property_type']))
            <a href="{{ route('broker.index') }}" class="btn btn-outline-secondary">{{ __('Clear') }}</a>
        @endif
    </form>
</div>

<div class="row row-cards">
    @forelse($properties as $property)
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h3 class="card-title mb-1">{{ $property->address }}</h3>
                    <p class="text-secondary mb-3">{{ $property->city }}, {{ $property->state }} {{ $property->zip_code }}</p>

                    <div class="mb-3">
                        <span class="badge bg-blue-lt">
                            {{ \App\Services\CustomFieldService::getOptions('property_type')[$property->property_type] ?? $property->property_type }}
                        </span>
                        @if($property->listing_status)
                            <span class="badge bg-green-lt">{{ __(ucfirst($property->listing_status)) }}</span>
                        @endif
                    </div>

                    <div class="datagrid mb-3">
                        @if($property->bedrooms)
                            <div class="datagrid-item">
                                <div class="datagrid-title">{{ __('Beds') }}</div>
                                <div class="datagrid-content">{{ $property->bedrooms }}</div>
                            </div>
                        @endif
                        @if($property->bathrooms)
                            <div class="datagrid-item">
                                <div class="datagrid-title">{{ __('Baths') }}</div>
                                <div class="datagrid-content">{{ $property->bathrooms }}</div>
                            </div>
                        @endif
                        @if($property->square_footage)
                            <div class="datagrid-item">
                                <div class="datagrid-title">{{ __('Sq Ft') }}</div>
                                <div class="datagrid-content">{{ number_format($property->square_footage) }}</div>
                            </div>
                        @endif
                    </div>

                    @php $price = $property->list_price ?: $property->asking_price ?: $property->estimated_value; @endphp
                    <div class="mt-auto d-flex justify-content-between align-items-center">
                        <strong class="h3 mb-0">
                            {{ $price ? number_format((float) $price) : __('Price on request') }}
                        </strong>
                        <a href="{{ route('broker.show', $property) }}" class="btn btn-primary">{{ __('View') }}</a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <h3>{{ __('No properties yet') }}</h3>
                    <p class="text-secondary mb-0">
                        {{ __('Properties will appear here as soon as they are shared with you.') }}
                    </p>
                </div>
            </div>
        </div>
    @endforelse
</div>

<div class="mt-3">
    {{ $properties->links() }}
</div>
@endsection
