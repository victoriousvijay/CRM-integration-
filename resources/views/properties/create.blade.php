@extends('layouts.app')

@section('title', __('Add Property'))
@section('page-title', __('Add Property'))

@section('breadcrumbs')
<li class="breadcrumb-item"><a href="{{ route('properties.index') }}">{{ __('Properties') }}</a></li>
<li class="breadcrumb-item active">{{ __('Add Property') }}</li>
@endsection

@section('content')
<form method="POST" action="{{ route('properties.store') }}" enctype="multipart/form-data">
    @csrf

    @include('properties._form', ['property' => null])

    <div class="card mt-3">
        <div class="card-body">
            <h3 class="card-title">{{ __('Photos') }}</h3>
            <input type="file" name="images[]" class="form-control @error('images.*') is-invalid @enderror"
                   accept="image/jpeg,image/png,image/webp" multiple>
            <small class="form-hint">
                {{ __('JPG, PNG or WebP. Up to :count photos, :size MB each. The first one becomes the cover image.', [
                    'count' => \App\Http\Controllers\PropertyImageController::MAX_PER_PROPERTY,
                    'size' => round(\App\Http\Controllers\PropertyImageController::MAX_KILOBYTES / 1024),
                ]) }}
            </small>
            @error('images.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('Save Property') }}</button>
        <a href="{{ route('properties.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
