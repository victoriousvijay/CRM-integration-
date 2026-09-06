@extends('layouts.broker')

@section('title', __('New Enquiry'))

@section('content')
<a href="{{ route('broker.leads.index') }}" class="btn btn-outline-secondary mb-3">&larr; {{ __('Back to my enquiries') }}</a>

<div class="card">
    <div class="card-body">
        <h2 class="mb-1">{{ __('New Client Enquiry') }}</h2>
        <p class="text-secondary mb-4">
            {{ __('Record the client you just met. The team sees this in the CRM straight away, listed under your name.') }}
        </p>

        <form method="POST" action="{{ route('broker.leads.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="first_name">{{ __('Client Name') }}</label>
                    <input type="text" id="first_name" name="first_name" class="form-control @error('first_name') is-invalid @enderror"
                           value="{{ old('first_name') }}" required autofocus>
                    @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="last_name">{{ __('Surname') }}</label>
                    <input type="text" id="last_name" name="last_name" class="form-control @error('last_name') is-invalid @enderror"
                           value="{{ old('last_name') }}">
                    @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label required" for="phone">{{ __('Contact Number') }}</label>
                    <input type="tel" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone') }}" required>
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">{{ __('Email') }}</label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email') }}">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="visited_property_id">{{ __('Property Visited') }}</label>
                    <select id="visited_property_id" name="visited_property_id" class="form-select @error('visited_property_id') is-invalid @enderror">
                        <option value="">{{ __('No specific property') }}</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}"
                                @selected((int) old('visited_property_id', $selectedPropertyId) === $property->id)>
                                {{ $property->address }}, {{ $property->city }}
                            </option>
                        @endforeach
                    </select>
                    @error('visited_property_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label required" for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                        @foreach($statuses as $slug => $label)
                            <option value="{{ $slug }}" @selected(old('status', 'new') === $slug)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="photo">{{ __("Client's Photo") }}</label>
                    <input type="file" id="photo" name="photo" class="form-control @error('photo') is-invalid @enderror"
                           accept="image/jpeg,image/png,image/webp" capture="environment">
                    <small class="form-hint">{{ __('Optional. You can take one with your camera.') }}</small>
                    @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="notes">{{ __('Notes / What was the conclusion?') }}</label>
                    <textarea id="notes" name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror"
                              placeholder="{{ __('What the client is looking for, budget, how the visit went, next step...') }}">{{ old('notes') }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Add Enquiry') }}</button>
                <a href="{{ route('broker.leads.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </div>

            <p class="text-secondary mt-3 mb-0">
                {{ __('The date and time are recorded automatically when you submit.') }}
            </p>
        </form>
    </div>
</div>
@endsection
