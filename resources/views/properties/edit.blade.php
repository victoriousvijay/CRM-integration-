@extends('layouts.app')

@section('title', __('Edit Property'))
@section('page-title', __('Edit Property'))

@section('breadcrumbs')
<li class="breadcrumb-item"><a href="{{ route('properties.index') }}">{{ __('Properties') }}</a></li>
<li class="breadcrumb-item"><a href="{{ route('properties.show', $property) }}">{{ $property->address }}</a></li>
<li class="breadcrumb-item active">{{ __('Edit') }}</li>
@endsection

@section('content')
<form method="POST" action="{{ route('properties.update', $property) }}">
    @csrf
    @method('PUT')

    @include('properties._form')

    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
        <a href="{{ route('properties.show', $property) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
