@extends('layouts.app')

@section('title', 'Onboard New Client')

@section('content')
<div class="container-xl" style="max-width: 720px;">
    <h2 class="mb-3">Onboard a New Client</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('platform-admin.tenants.store') }}">
        @csrf
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Company</h3>
                <div class="mb-3">
                    <label class="form-label required">Company Name</label>
                    <input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Slug (optional, auto-generated if blank)</label>
                    <input type="text" name="slug" class="form-control" value="{{ old('slug') }}" placeholder="arhomes">
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Business Mode</label>
                        <select name="business_mode" class="form-select">
                            <option value="realestate">Real Estate</option>
                            <option value="wholesale">Wholesale</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-control" value="{{ old('country') }}" placeholder="India">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Currency</label>
                        <input type="text" name="currency" class="form-control" value="{{ old('currency', 'USD') }}" placeholder="INR">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Timezone</label>
                    <input type="text" name="timezone" class="form-control" value="{{ old('timezone', 'UTC') }}" placeholder="Asia/Kolkata">
                </div>

                <hr>
                <h3 class="card-title">Client Admin</h3>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label required">Admin Name</label>
                        <input type="text" name="admin_name" class="form-control" value="{{ old('admin_name') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Admin Email</label>
                        <input type="email" name="admin_email" class="form-control" value="{{ old('admin_email') }}" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label required">Admin Password</label>
                    <input type="password" name="admin_password" class="form-control" minlength="8" required>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">Create Client</button>
            </div>
        </div>
    </form>
</div>
@endsection
