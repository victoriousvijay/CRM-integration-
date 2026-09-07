@extends('layouts.app')

@section('title', 'Edit '.$tenant->name)

@section('content')
<div class="container-xl" style="max-width: 760px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="mb-0">Edit Client: {{ $tenant->name }}</h2>
        <a href="{{ route('platform-admin.tenants.show', $tenant) }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <form method="POST" action="{{ route('platform-admin.tenants.update', $tenant) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Branding</h3></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required">Company Name</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $tenant->name) }}" required>
                    <small class="form-hint">Shown to this client's staff everywhere in place of the platform name.</small>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Logo</label>
                    <div class="d-flex align-items-center gap-3">
                        @if($tenant->logo)
                            <img src="{{ \App\Support\Brand::logoFor($tenant) }}" alt=""
                                 style="max-height:56px;max-width:180px;border-radius:6px;">
                        @else
                            <span class="text-secondary">No logo — their name shows as a wordmark.</span>
                        @endif
                        <div class="flex-fill">
                            <input type="file" name="logo" class="form-control @error('logo') is-invalid @enderror"
                                   accept="image/jpeg,image/png,image/webp,image/svg+xml">
                            <small class="form-hint">PNG, JPG, WEBP or SVG, up to 2 MB. Stored in the database, so it survives a redeploy.</small>
                            @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    @if($tenant->logo)
                        <label class="form-check mt-2">
                            <input type="checkbox" name="remove_logo" value="1" class="form-check-input">
                            <span class="form-check-label">Remove the current logo</span>
                        </label>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Features</h3></div>
            <div class="card-body">
                <p class="text-secondary">What this client's plan includes. Turning one off hides it from every one of their users straight away.</p>
                @foreach($features as $flag => $label)
                    <label class="form-check form-switch">
                        <input type="checkbox" name="{{ $flag }}" value="1" class="form-check-input"
                               @checked(old($flag, $tenant->$flag))>
                        <span class="form-check-label">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Plan &amp; limits</h3></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">User limit</label>
                    <input type="number" name="max_users" min="1" max="10000"
                           class="form-control @error('max_users') is-invalid @enderror"
                           value="{{ old('max_users', $tenant->max_users) }}" placeholder="Unlimited">
                    <small class="form-hint">
                        Leave blank for no limit. They currently have {{ $tenant->users()->count() }}
                        {{ \Illuminate\Support\Str::plural('user', $tenant->users()->count()) }};
                        past the limit, inviting another is refused with a message pointing them at you.
                    </small>
                    @error('max_users')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Business Mode</label>
                    <select name="business_mode" class="form-select">
                        <option value="realestate" @selected(old('business_mode', $tenant->business_mode) === 'realestate')>Real Estate</option>
                        <option value="wholesale" @selected(old('business_mode', $tenant->business_mode) === 'wholesale')>Wholesale</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Locale &amp; contact</h3></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $tenant->email) }}">
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-control" value="{{ old('country', $tenant->country) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Currency</label>
                        <input type="text" name="currency" class="form-control" value="{{ old('currency', $tenant->currency) }}" placeholder="INR">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Timezone</label>
                        <input type="text" name="timezone" class="form-control" value="{{ old('timezone', $tenant->timezone) }}" placeholder="Asia/Kolkata">
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </div>
    </form>
</div>
@endsection
