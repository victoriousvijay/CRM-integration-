@php
    /** @var \App\Models\Property|null $property */
    $property = $property ?? null;
    $isRealEstate = ($businessMode ?? 'wholesale') === 'realestate';
    $val = fn (string $field, $default = null) => old($field, $property?->{$field} ?? $default);
@endphp

<div class="card">
    <div class="card-body">
        <h3 class="card-title">{{ __('Address') }}</h3>
        <div class="row g-3">
            <div class="col-md-12">
                <label class="form-label required" for="address">{{ __('Street Address') }}</label>
                <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror"
                       value="{{ $val('address') }}" required maxlength="255">
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-5">
                <label class="form-label required" for="city">{{ __('City') }}</label>
                <input type="text" id="city" name="city" class="form-control @error('city') is-invalid @enderror"
                       value="{{ $val('city') }}" required maxlength="255">
                @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label required" for="state">{{ __('State') }}</label>
                <input type="text" id="state" name="state" class="form-control @error('state') is-invalid @enderror"
                       value="{{ $val('state') }}" required maxlength="2" placeholder="{{ __('e.g. TX') }}">
                @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label required" for="zip_code">{{ __('ZIP Code') }}</label>
                <input type="text" id="zip_code" name="zip_code" class="form-control @error('zip_code') is-invalid @enderror"
                       value="{{ $val('zip_code') }}" required maxlength="10">
                @error('zip_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <h3 class="card-title">{{ __('Details') }}</h3>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label required" for="property_type">{{ __('Property Type') }}</label>
                <select id="property_type" name="property_type" class="form-select @error('property_type') is-invalid @enderror" required>
                    <option value="">{{ __('Select...') }}</option>
                    @foreach(\App\Services\CustomFieldService::getOptions('property_type') as $slug => $label)
                        <option value="{{ $slug }}" @selected($val('property_type') === $slug)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('property_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="condition">{{ __('Condition') }}</label>
                <select id="condition" name="condition" class="form-select @error('condition') is-invalid @enderror">
                    <option value="">{{ __('Not specified') }}</option>
                    @foreach(\App\Services\CustomFieldService::getOptions('property_condition') as $slug => $label)
                        <option value="{{ $slug }}" @selected($val('condition') === $slug)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('condition')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="year_built">{{ __('Year Built') }}</label>
                <input type="number" id="year_built" name="year_built" class="form-control @error('year_built') is-invalid @enderror"
                       value="{{ $val('year_built') }}" min="1800" max="{{ date('Y') }}">
                @error('year_built')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="bedrooms">{{ __('Bedrooms') }}</label>
                <input type="number" id="bedrooms" name="bedrooms" class="form-control @error('bedrooms') is-invalid @enderror"
                       value="{{ $val('bedrooms') }}" min="0">
                @error('bedrooms')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="bathrooms">{{ __('Bathrooms') }}</label>
                <input type="number" id="bathrooms" name="bathrooms" class="form-control @error('bathrooms') is-invalid @enderror"
                       value="{{ $val('bathrooms') }}" min="0">
                @error('bathrooms')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="square_footage">{{ __('Square Footage') }}</label>
                <input type="number" id="square_footage" name="square_footage" class="form-control @error('square_footage') is-invalid @enderror"
                       value="{{ $val('square_footage') }}" min="0">
                @error('square_footage')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="lot_size">{{ __('Lot Size') }}</label>
                <input type="number" step="0.01" id="lot_size" name="lot_size" class="form-control @error('lot_size') is-invalid @enderror"
                       value="{{ $val('lot_size') }}" min="0">
                @error('lot_size')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <h3 class="card-title">{{ $isRealEstate ? __('Listing & Pricing') : __('Deal Numbers') }}</h3>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="estimated_value">{{ __('Estimated Value') }}</label>
                <input type="number" step="0.01" id="estimated_value" name="estimated_value" class="form-control @error('estimated_value') is-invalid @enderror"
                       value="{{ $val('estimated_value') }}" min="0">
                @error('estimated_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="asking_price">{{ __('Asking Price') }}</label>
                <input type="number" step="0.01" id="asking_price" name="asking_price" class="form-control @error('asking_price') is-invalid @enderror"
                       value="{{ $val('asking_price') }}" min="0">
                @error('asking_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @if($isRealEstate)
                <div class="col-md-4">
                    <label class="form-label" for="list_price">{{ __('List Price') }}</label>
                    <input type="number" step="0.01" id="list_price" name="list_price" class="form-control @error('list_price') is-invalid @enderror"
                           value="{{ $val('list_price') }}" min="0">
                    @error('list_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="listing_status">{{ __('Listing Status') }}</label>
                    <select id="listing_status" name="listing_status" class="form-select @error('listing_status') is-invalid @enderror">
                        <option value="">{{ __('Not listed') }}</option>
                        @foreach(['active', 'pending', 'sold', 'withdrawn', 'expired'] as $status)
                            <option value="{{ $status }}" @selected($val('listing_status') === $status)>{{ __(ucfirst($status)) }}</option>
                        @endforeach
                    </select>
                    @error('listing_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="listed_at">{{ __('Listed On') }}</label>
                    <input type="date" id="listed_at" name="listed_at" class="form-control @error('listed_at') is-invalid @enderror"
                           value="{{ old('listed_at', $property?->listed_at?->format('Y-m-d')) }}">
                    @error('listed_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sold_at">{{ __('Sold On') }}</label>
                    <input type="date" id="sold_at" name="sold_at" class="form-control @error('sold_at') is-invalid @enderror"
                           value="{{ old('sold_at', $property?->sold_at?->format('Y-m-d')) }}">
                    @error('sold_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sold_price">{{ __('Sold Price') }}</label>
                    <input type="number" step="0.01" id="sold_price" name="sold_price" class="form-control @error('sold_price') is-invalid @enderror"
                           value="{{ $val('sold_price') }}" min="0">
                    @error('sold_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @else
                <div class="col-md-4">
                    <label class="form-label" for="repair_estimate">{{ __('Repair Estimate') }}</label>
                    <input type="number" step="0.01" id="repair_estimate" name="repair_estimate" class="form-control @error('repair_estimate') is-invalid @enderror"
                           value="{{ $val('repair_estimate') }}" min="0">
                    @error('repair_estimate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="after_repair_value">{{ __('After Repair Value (ARV)') }}</label>
                    <input type="number" step="0.01" id="after_repair_value" name="after_repair_value" class="form-control @error('after_repair_value') is-invalid @enderror"
                           value="{{ $val('after_repair_value') }}" min="0">
                    <small class="form-hint">{{ __('Max allowable offer is calculated automatically as (ARV x 0.70) - repairs.') }}</small>
                    @error('after_repair_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="our_offer">{{ __('Our Offer') }}</label>
                    <input type="number" step="0.01" id="our_offer" name="our_offer" class="form-control @error('our_offer') is-invalid @enderror"
                           value="{{ $val('our_offer') }}" min="0">
                    @error('our_offer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-12">
                    <label class="form-label" for="distress_markers">{{ __('Distress Markers') }}</label>
                    @php $selectedMarkers = (array) old('distress_markers', $property?->distress_markers ?? []); @endphp
                    <div class="row g-2">
                        @foreach(\App\Services\CustomFieldService::getOptions('distress_markers') as $slug => $label)
                            <div class="col-md-3">
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="distress_markers[]"
                                           value="{{ $slug }}" @checked(in_array($slug, $selectedMarkers, true))>
                                    <span class="form-check-label">{{ $label }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('distress_markers')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            @endif
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <label class="form-label" for="notes">{{ __('Notes') }}</label>
        <textarea id="notes" name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror">{{ $val('notes') }}</textarea>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
