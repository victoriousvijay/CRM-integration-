@extends('layouts.broker')

@section('title', __('My Enquiries'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h2 class="mb-0">{{ __('My Enquiries') }}</h2>
        <p class="text-secondary mb-0">
            {{ trans_choice(':count client handled|:count clients handled', $leads->total(), ['count' => $leads->total()]) }}
        </p>
    </div>
    <a href="{{ route('broker.leads.create') }}" class="btn btn-primary">{{ __('New Enquiry') }}</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead>
                <tr>
                    <th></th>
                    <th>{{ __('Client') }}</th>
                    <th>{{ __('Contact') }}</th>
                    <th>{{ __('Property Visited') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Logged') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                    <tr>
                        <td style="width:56px;">
                            @if($lead->clientPhoto)
                                <img src="{{ $lead->clientPhoto->url() }}" alt=""
                                     class="rounded-circle" style="height:40px;width:40px;object-fit:cover;">
                            @else
                                <span class="avatar">{{ strtoupper(substr($lead->first_name, 0, 1)) }}</span>
                            @endif
                        </td>
                        <td>
                            <strong>{{ trim($lead->first_name.' '.$lead->last_name) }}</strong>
                            @if($lead->notes)
                                <div class="text-secondary small">{{ Str::limit($lead->notes, 70) }}</div>
                            @endif
                        </td>
                        <td>
                            <a href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a>
                            @if($lead->email)
                                <div class="text-secondary small">{{ $lead->email }}</div>
                            @endif
                        </td>
                        <td>
                            @if($lead->visitedProperty)
                                <a href="{{ route('broker.show', $lead->visitedProperty) }}">{{ $lead->visitedProperty->address }}</a>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-blue-lt">
                                {{ \App\Services\CustomFieldService::getOptions('lead_status')[$lead->status] ?? $lead->status }}
                            </span>
                        </td>
                        <td class="text-nowrap">
                            {{ $lead->created_at?->format('d M Y, g:i A') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-5">
                            <p class="mb-1">{{ __('No enquiries logged yet.') }}</p>
                            <a href="{{ route('broker.leads.create') }}" class="btn btn-primary mt-2">{{ __('Add your first enquiry') }}</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $leads->links() }}</div>
@endsection
