@extends('layouts.app')

@section('title', 'Edit '.$tenant->name)

@section('content')
<div class="container-xl" style="max-width: 640px;">
    <h2 class="mb-3">Edit Client: {{ $tenant->name }}</h2>

    <form method="POST" action="{{ route('platform-admin.tenants.update', $tenant) }}">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required">Company Name</label>
                    <input type="text" name="name" class="form-control" value="{{ $tenant->name }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ $tenant->email }}">
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-control" value="{{ $tenant->country }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Currency</label>
                        <input type="text" name="currency" class="form-control" value="{{ $tenant->currency }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Timezone</label>
                        <input type="text" name="timezone" class="form-control" value="{{ $tenant->timezone }}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Business Mode</label>
                    <select name="business_mode" class="form-select">
                        <option value="realestate" @selected($tenant->business_mode === 'realestate')>Real Estate</option>
                        <option value="wholesale" @selected($tenant->business_mode === 'wholesale')>Wholesale</option>
                    </select>
                </div>
                <p class="text-secondary">Logo and full branding (favicon, sender email, etc.) are managed by the client themselves under their own Settings &rarr; Branding.</p>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </div>
    </form>
</div>
@endsection
