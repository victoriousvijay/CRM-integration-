@extends('layouts.app')

@section('title', __('Add Property'))
@section('page-title', __('Add Property'))

@section('breadcrumbs')
<li class="breadcrumb-item"><a href="{{ route('properties.index') }}">{{ __('Properties') }}</a></li>
<li class="breadcrumb-item active">{{ __('Add Property') }}</li>
@endsection

@section('content')
<form method="POST" action="{{ route('properties.store') }}">
    @csrf

    @include('properties._form', ['property' => null])

    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('Save Property') }}</button>
        <a href="{{ route('properties.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
