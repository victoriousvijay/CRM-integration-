@extends('layouts.broker')

@section('title', __('My Enquiries'))

@section('content')
<div class="bp-row bp-row--between bp-row--wrap bp-mb">
    <div>
        <h1 class="bp-title">{{ __('My Enquiries') }}</h1>
        <p class="bp-subtitle">
            {{ trans_choice(':count client handled|:count clients handled', $stats['total'], ['count' => $stats['total']]) }}
        </p>
    </div>
    <a href="{{ route('broker.leads.create') }}" class="bp-btn bp-btn--primary">
        @include('broker._icon', ['name' => 'plus', 'size' => 18])
        {{ __('New Enquiry') }}
    </a>
</div>

@if($stats['total'] > 0)
    <div class="bp-stats bp-mb">
        <div class="bp-stat">
            <div class="bp-stat__value">{{ number_format($stats['total']) }}</div>
            <div class="bp-stat__label">{{ __('All time') }}</div>
        </div>
        <div class="bp-stat">
            <div class="bp-stat__value">{{ number_format($stats['this_month']) }}</div>
            <div class="bp-stat__label">{{ __('This month') }}</div>
        </div>
        <div class="bp-stat">
            <div class="bp-stat__value">{{ number_format($stats['this_week']) }}</div>
            <div class="bp-stat__label">{{ __('This week') }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('broker.leads.index') }}" class="bp-toolbar bp-mb">
        <div class="bp-toolbar__search">
            @include('broker._icon', ['name' => 'search', 'size' => 16])
            <input type="search" name="search" class="bp-input"
                   placeholder="{{ __('Search name, phone, email...') }}" value="{{ request('search') }}"
                   aria-label="{{ __('Search enquiries') }}">
        </div>
        <select name="status" class="bp-select" style="flex:0 1 180px;" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Any status') }}</option>
            @foreach($statuses as $slug => $label)
                <option value="{{ $slug }}" @selected(request('status') === $slug)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="bp-btn bp-btn--primary">{{ __('Search') }}</button>
        @if(request()->hasAny(['search', 'status']))
            <a href="{{ route('broker.leads.index') }}" class="bp-btn bp-btn--ghost">{{ __('Clear') }}</a>
        @endif
    </form>
@endif

<div class="bp-card">
    @php
        // Same normalisation the automated messages use, so a wa.me link the
        // broker taps reaches the number the CRM would have messaged.
        $whatsappService = app(\App\Services\WhatsAppService::class);
        $countryCode = auth()->user()->tenant?->whatsapp_settings['default_country_code'] ?? null;
    @endphp

    @forelse($leads as $lead)
        @php
            $name = trim($lead->first_name.' '.$lead->last_name);
            $whatsapp = $whatsappService->normalizeNumber($lead->phone, $countryCode);
        @endphp
        <div class="bp-enquiry">
            @if($lead->clientPhoto)
                <img src="{{ $lead->clientPhoto->url() }}" alt="" class="bp-enquiry__photo" loading="lazy">
            @else
                <span class="bp-enquiry__photo bp-enquiry__initial">
                    {{ strtoupper(mb_substr($lead->first_name, 0, 1)) }}
                </span>
            @endif

            <div class="bp-fill">
                <div class="bp-row bp-row--wrap">
                    <span class="bp-enquiry__name">{{ $name }}</span>
                    <span class="bp-chip">{{ $statuses[$lead->status] ?? $lead->status }}</span>
                </div>

                <div class="bp-enquiry__meta bp-row bp-row--wrap" style="gap:0.35rem 1rem;margin-top:0.25rem;">
                    <span class="bp-spec">
                        @include('broker._icon', ['name' => 'clock', 'size' => 15])
                        {{ $lead->created_at?->format('d M Y, g:i A') }}
                    </span>
                    @if($lead->visitedProperty)
                        <a href="{{ route('broker.show', $lead->visitedProperty) }}" class="bp-spec">
                            @include('broker._icon', ['name' => 'building', 'size' => 15])
                            {{ $lead->visitedProperty->address }}
                        </a>
                    @endif
                </div>

                @if($lead->notes)
                    <p class="bp-enquiry__meta" style="margin-top:0.5rem;white-space:pre-line;">{{ Str::limit($lead->notes, 220) }}</p>
                @endif

                {{-- Reaching the client is the whole point of the row, so the
                     phone and WhatsApp are one tap, not a copied number. --}}
                <div class="bp-row bp-row--wrap bp-mt" style="gap:0.4rem;">
                    <a href="tel:{{ $lead->phone }}" class="bp-btn bp-btn--ghost bp-btn--sm">
                        @include('broker._icon', ['name' => 'phone', 'size' => 15])
                        {{ $lead->phone }}
                    </a>
                    @if($whatsapp)
                        <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                           class="bp-btn bp-btn--ghost bp-btn--sm">
                            @include('broker._icon', ['name' => 'message', 'size' => 15])
                            {{ __('WhatsApp') }}
                        </a>
                    @endif
                    <button type="button" class="bp-btn bp-btn--ghost bp-btn--sm" data-toggle-update="{{ $lead->id }}">
                        @include('broker._icon', ['name' => 'note', 'size' => 15])
                        {{ __('Update') }}
                    </button>
                </div>

                <form method="POST" action="{{ route('broker.leads.update', $lead) }}"
                      id="update-{{ $lead->id }}" class="bp-mt" hidden>
                    @csrf
                    @method('PATCH')
                    <div class="bp-fields bp-fields--2">
                        <div>
                            <label class="bp-label" for="status-{{ $lead->id }}">{{ __('Status') }}</label>
                            <select id="status-{{ $lead->id }}" name="status" class="bp-select">
                                @foreach($statuses as $slug => $label)
                                    <option value="{{ $slug }}" @selected($lead->status === $slug)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="bp-label" for="note-{{ $lead->id }}">{{ __('Add a note') }}</label>
                            <input type="text" id="note-{{ $lead->id }}" name="note" class="bp-input"
                                   placeholder="{{ __('Called again, visiting Sunday...') }}" maxlength="2000">
                        </div>
                    </div>
                    <div class="bp-row bp-mt" style="gap:0.4rem;">
                        <button type="submit" class="bp-btn bp-btn--primary bp-btn--sm">{{ __('Save') }}</button>
                        <button type="button" class="bp-btn bp-btn--ghost bp-btn--sm" data-toggle-update="{{ $lead->id }}">
                            {{ __('Cancel') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="bp-empty">
            <span class="bp-empty__icon">@include('broker._icon', ['name' => 'users', 'size' => 30])</span>
            @if(request()->hasAny(['search', 'status']))
                <p class="bp-empty__title">{{ __('No enquiries matched') }}</p>
                <p class="bp-empty__text">{{ __('Try a different name, number or status.') }}</p>
                <a href="{{ route('broker.leads.index') }}" class="bp-btn bp-btn--ghost bp-mt">{{ __('Show all enquiries') }}</a>
            @else
                <p class="bp-empty__title">{{ __('No enquiries logged yet') }}</p>
                <p class="bp-empty__text">
                    {{ __('Every client you meet goes here, and straight to the team in the CRM under your name.') }}
                </p>
                <a href="{{ route('broker.leads.create') }}" class="bp-btn bp-btn--primary bp-mt">
                    @include('broker._icon', ['name' => 'plus', 'size' => 18])
                    {{ __('Add your first enquiry') }}
                </a>
            @endif
        </div>
    @endforelse
</div>

@if($leads->hasPages())
    <div class="bp-mt-lg">{{ $leads->links() }}</div>
@endif

<script>
// The update form stays out of the way until asked for: the list is for
// scanning, and a status select on every row would bury the client's details.
document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-toggle-update]');
    if (!trigger) return;

    var form = document.getElementById('update-' + trigger.dataset.toggleUpdate);
    if (!form) return;

    form.hidden = !form.hidden;
    if (!form.hidden) form.querySelector('select').focus();
});
</script>
@endsection
