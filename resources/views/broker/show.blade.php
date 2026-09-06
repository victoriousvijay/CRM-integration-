@extends('layouts.broker')

@section('title', $property->address)

@section('content')
<a href="{{ route('broker.index') }}" class="btn btn-outline-secondary mb-3">&larr; {{ __('Back to properties') }}</a>

<div class="card">
    <div class="card-body">
        <h2 class="mb-1">{{ $property->address }}</h2>
        <p class="text-secondary">{{ $property->city }}, {{ $property->state }} {{ $property->zip_code }}</p>

        @php $price = $property->list_price ?: $property->asking_price ?: $property->estimated_value; @endphp
        <div class="h1 mb-4">{{ $price ? number_format((float) $price) : __('Price on request') }}</div>

        <div class="datagrid">
            <div class="datagrid-item">
                <div class="datagrid-title">{{ __('Type') }}</div>
                <div class="datagrid-content">
                    {{ \App\Services\CustomFieldService::getOptions('property_type')[$property->property_type] ?? $property->property_type }}
                </div>
            </div>
            @if($property->condition)
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('Condition') }}</div>
                    <div class="datagrid-content">
                        {{ \App\Services\CustomFieldService::getOptions('property_condition')[$property->condition] ?? $property->condition }}
                    </div>
                </div>
            @endif
            @if($property->listing_status)
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('Status') }}</div>
                    <div class="datagrid-content">{{ __(ucfirst($property->listing_status)) }}</div>
                </div>
            @endif
            @if($property->bedrooms)
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('Bedrooms') }}</div>
                    <div class="datagrid-content">{{ $property->bedrooms }}</div>
                </div>
            @endif
            @if($property->bathrooms)
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('Bathrooms') }}</div>
                    <div class="datagrid-content">{{ $property->bathrooms }}</div>
                </div>
            @endif
            @if($property->square_footage)
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('Square Footage') }}</div>
                    <div class="datagrid-content">{{ number_format($property->square_footage) }}</div>
                </div>
            @endif
            @if($property->lot_size)
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('Lot Size') }}</div>
                    <div class="datagrid-content">{{ $property->lot_size }}</div>
                </div>
            @endif
            @if($property->year_built)
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('Year Built') }}</div>
                    <div class="datagrid-content">{{ $property->year_built }}</div>
                </div>
            @endif
            @if($property->listed_at)
                <div class="datagrid-item">
                    <div class="datagrid-title">{{ __('Listed On') }}</div>
                    <div class="datagrid-content">{{ $property->listed_at->format('d M Y') }}</div>
                </div>
            @endif
        </div>

        @if($property->notes)
            <h3 class="mt-4">{{ __('Notes') }}</h3>
            <p class="mb-0" style="white-space: pre-line;">{{ $property->notes }}</p>
        @endif
    </div>
</div>
@endsection
