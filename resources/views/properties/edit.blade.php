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

<div class="card mt-3">
    <div class="card-body">
        <h3 class="card-title">{{ __('Photos') }}</h3>

        @if($property->images->isNotEmpty())
            <div class="row row-cards mb-3">
                @foreach($property->images as $image)
                    <div class="col-6 col-md-3">
                        <div class="card">
                            <img src="{{ $image->url() }}" alt="{{ $image->filename }}"
                                 class="card-img-top" style="height:140px;object-fit:cover;">
                            <div class="card-body p-2 d-flex gap-1 align-items-center">
                                @if($image->is_primary)
                                    <span class="badge bg-green-lt">{{ __('Cover') }}</span>
                                @else
                                    <form method="POST" action="{{ route('properties.images.primary', $image) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-ghost-secondary btn-sm px-1">{{ __('Make cover') }}</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('properties.images.destroy', $image) }}" class="ms-auto"
                                      onsubmit="return confirm('{{ __('Remove this photo?') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-ghost-danger btn-sm px-1">{{ __('Remove') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-secondary">{{ __('No photos yet.') }}</p>
        @endif

        <form method="POST" action="{{ route('properties.images.store', $property) }}" enctype="multipart/form-data"
              class="d-flex gap-2 align-items-start flex-wrap">
            @csrf
            <div class="flex-grow-1" style="min-width:240px;">
                <input type="file" name="images[]" class="form-control"
                       accept="image/jpeg,image/png,image/webp" multiple required>
                <small class="form-hint">
                    {{ __('JPG, PNG or WebP. Up to :count photos, :size MB each.', [
                        'count' => \App\Http\Controllers\PropertyImageController::MAX_PER_PROPERTY,
                        'size' => round(\App\Http\Controllers\PropertyImageController::MAX_KILOBYTES / 1024),
                    ]) }}
                </small>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('Upload') }}</button>
        </form>
        @error('images.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div>
</div>
@endsection
