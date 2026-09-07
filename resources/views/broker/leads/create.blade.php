@extends('layouts.broker')

@section('title', __('New Enquiry'))

@section('content')
<a href="{{ route('broker.leads.index') }}" class="bp-back">
    @include('broker._icon', ['name' => 'arrow-left', 'size' => 16])
    {{ __('Back to my enquiries') }}
</a>

<div style="max-width: 46rem; margin: 0 auto;">
    <div class="bp-mb">
        <h1 class="bp-title">{{ __('New Client Enquiry') }}</h1>
        <p class="bp-subtitle">
            {{ __('Record the client you just met. The team sees this in the CRM straight away, listed under your name.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('broker.leads.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="bp-card bp-mb">
            <div class="bp-card__body">
                <fieldset class="bp-fieldset">
                    <legend class="bp-legend">
                        @include('broker._icon', ['name' => 'users', 'size' => 14])
                        {{ __('Who they are') }}
                    </legend>

                    <div class="bp-fields bp-fields--2">
                        <div>
                            <label class="bp-label bp-label--required" for="first_name">{{ __('Client Name') }}</label>
                            <input type="text" id="first_name" name="first_name" class="bp-input @error('first_name') is-invalid @enderror"
                                   value="{{ old('first_name') }}" required autofocus autocomplete="given-name">
                            @error('first_name')<div class="bp-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="bp-label" for="last_name">{{ __('Surname') }}</label>
                            <input type="text" id="last_name" name="last_name" class="bp-input @error('last_name') is-invalid @enderror"
                                   value="{{ old('last_name') }}" autocomplete="family-name">
                            @error('last_name')<div class="bp-error">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="bp-label bp-label--required" for="phone">{{ __('Contact Number') }}</label>
                            <input type="tel" id="phone" name="phone" class="bp-input @error('phone') is-invalid @enderror"
                                   value="{{ old('phone') }}" required inputmode="tel" autocomplete="tel">
                            @error('phone')<div class="bp-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="bp-label" for="email">{{ __('Email') }}</label>
                            <input type="email" id="email" name="email" class="bp-input @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}" inputmode="email" autocomplete="email">
                            @error('email')<div class="bp-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="bp-field--wide">
                            <label class="bp-label" for="photo">{{ __("Client's Photo") }}</label>
                            <div class="bp-photo">
                                <span class="bp-photo__preview" id="photo-preview">
                                    @include('broker._icon', ['name' => 'camera', 'size' => 22])
                                </span>
                                <div class="bp-fill">
                                    <input type="file" id="photo" name="photo" class="bp-input @error('photo') is-invalid @enderror"
                                           accept="image/jpeg,image/png,image/webp" capture="environment">
                                    <div class="bp-hint">{{ __('Optional. You can take one with your camera.') }}</div>
                                    @error('photo')<div class="bp-error">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="bp-fieldset">
                    <legend class="bp-legend">
                        @include('broker._icon', ['name' => 'building', 'size' => 14])
                        {{ __('What they saw') }}
                    </legend>

                    <div class="bp-fields bp-fields--2">
                        <div>
                            <label class="bp-label" for="visited_property_id">{{ __('Property Visited') }}</label>
                            <select id="visited_property_id" name="visited_property_id" class="bp-select @error('visited_property_id') is-invalid @enderror">
                                <option value="">{{ __('No specific property') }}</option>
                                @foreach($properties as $property)
                                    <option value="{{ $property->id }}"
                                        @selected((int) old('visited_property_id', $selectedPropertyId) === $property->id)>
                                        {{ $property->address }}, {{ $property->city }}
                                    </option>
                                @endforeach
                            </select>
                            @error('visited_property_id')<div class="bp-error">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="bp-label bp-label--required" for="status">{{ __('Status') }}</label>
                            <select id="status" name="status" class="bp-select @error('status') is-invalid @enderror" required>
                                @foreach($statuses as $slug => $label)
                                    <option value="{{ $slug }}" @selected(old('status', 'new') === $slug)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')<div class="bp-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="bp-fieldset">
                    <legend class="bp-legend">
                        @include('broker._icon', ['name' => 'note', 'size' => 14])
                        {{ __('How it went') }}
                    </legend>

                    <label class="bp-label" for="notes">{{ __('Notes / What was the conclusion?') }}</label>
                    <textarea id="notes" name="notes" rows="4" class="bp-textarea @error('notes') is-invalid @enderror"
                              placeholder="{{ __('What the client is looking for, budget, how the visit went, next step...') }}">{{ old('notes') }}</textarea>
                    @error('notes')<div class="bp-error">{{ $message }}</div>@enderror
                </fieldset>
            </div>
        </div>

        <div class="bp-row bp-row--wrap" style="gap:0.5rem;">
            <button type="submit" class="bp-btn bp-btn--primary bp-btn--lg">
                @include('broker._icon', ['name' => 'check', 'size' => 18])
                {{ __('Add Enquiry') }}
            </button>
            <a href="{{ route('broker.leads.index') }}" class="bp-btn bp-btn--ghost bp-btn--lg">{{ __('Cancel') }}</a>
            <span class="bp-subtitle bp-push" style="font-size:0.8125rem;">
                {{ __('The date and time are recorded automatically when you submit.') }}
            </span>
        </div>
    </form>
</div>

<script>
// Show the photo the moment it is chosen — on a phone the file input alone
// gives no sign that the camera shot actually attached.
document.getElementById('photo').addEventListener('change', function () {
    var file = this.files && this.files[0];
    var preview = document.getElementById('photo-preview');
    if (!file || !preview) return;

    var url = URL.createObjectURL(file);
    var img = document.createElement('img');
    img.src = url;
    img.alt = '';
    img.onload = function () { URL.revokeObjectURL(url); };
    preview.replaceChildren(img);
});
</script>
@endsection
