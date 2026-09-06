@extends('layouts.app')

@section('title', $tenant->name)

@section('content')
<div class="container-xl">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>{{ $tenant->name }} <span class="badge {{ $tenant->status === 'active' ? 'bg-green-lt' : 'bg-red-lt' }}">{{ $tenant->status }}</span></h2>
        <div class="d-flex gap-2">
            <a href="{{ route('platform-admin.tenants.edit', $tenant) }}" class="btn btn-outline-secondary">Edit Branding</a>
            @if($tenant->status === 'active')
                <form method="POST" action="{{ route('platform-admin.tenants.suspend', $tenant) }}">@csrf
                    <button class="btn btn-outline-danger">Suspend</button>
                </form>
            @else
                <form method="POST" action="{{ route('platform-admin.tenants.activate', $tenant) }}">@csrf
                    <button class="btn btn-outline-success">Activate</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @if(session('provisioned_full_key'))
        <div class="alert alert-warning">
            <strong>Save these now — they will not be shown again:</strong><br>
            Full API Key: <code>{{ session('provisioned_full_key') }}</code><br>
            Website Embed Key: <code>{{ session('provisioned_embed_key') }}</code>
        </div>
    @endif

    <div class="row row-cards mb-3">
        <div class="col-md-3"><div class="card card-body text-center"><div class="h1">{{ $tenant->users_count }}</div>Users</div></div>
        <div class="col-md-3"><div class="card card-body text-center"><div class="h1">{{ $tenant->leads_count }}</div>Leads</div></div>
        <div class="col-md-3"><div class="card card-body text-center"><div class="h1">{{ $tenant->deals_count }}</div>Deals</div></div>
        <div class="col-md-3"><div class="card card-body text-center"><div class="h1">{{ $tenant->buyers_count }}</div>Buyers</div></div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">API Credentials</h3></div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>Name</th><th>Type</th><th>Prefix</th><th>Last Used</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($credentials as $cred)
                        <tr>
                            <td>{{ $cred->name }}</td>
                            <td><span class="badge bg-blue-lt">{{ $cred->type }}</span></td>
                            <td><code>{{ $cred->key_prefix }}</code></td>
                            <td>{{ $cred->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                            <td>{{ $cred->isRevoked() ? 'Revoked' : 'Active' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-3">No credentials yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
