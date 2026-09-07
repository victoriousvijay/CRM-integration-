@extends('layouts.app')

@section('title', 'Clients')

@section('content')
<div class="container-xl">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Platform Clients</h2>
        <a href="{{ route('platform-admin.tenants.create') }}" class="btn btn-primary">+ Onboard New Client</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Slug</th>
                        <th>Users</th>
                        <th>Business Mode</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                        @php $isOwn = $tenant->id === auth()->user()->tenant_id; @endphp
                        <tr>
                            <td>
                                @if(\App\Support\Brand::logoFor($tenant))
                                    <img src="{{ \App\Support\Brand::logoFor($tenant) }}" alt="" style="height:20px;margin-right:8px;">
                                @endif
                                {{ $tenant->name }}
                                {{-- Your own account sits in this list too; it is not a client. --}}
                                @if($isOwn)
                                    <span class="badge bg-blue-lt ms-1">Your platform account</span>
                                @endif
                            </td>
                            <td><code>{{ $tenant->slug }}</code></td>
                            <td>{{ $tenant->users_count }}</td>
                            <td>{{ $tenant->business_mode }}</td>
                            <td>
                                <span class="badge {{ $tenant->status === 'active' ? 'bg-green-lt' : 'bg-red-lt' }}">{{ $tenant->status }}</span>
                            </td>
                            <td>{{ $tenant->created_at->format('M j, Y') }}</td>
                            <td>
                                <a href="{{ route('platform-admin.tenants.show', $tenant) }}" class="btn btn-sm btn-outline-secondary">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-secondary py-4">No clients yet. Onboard your first one.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $tenants->links() }}</div>
</div>
@endsection
