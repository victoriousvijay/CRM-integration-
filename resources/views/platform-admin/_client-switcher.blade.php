{{--
    Open any client's CRM from anywhere in the console.

    Built from <details> and a plain form rather than a Bootstrap dropdown and
    modal: those need the CDN's JavaScript, and the one control that gets the
    platform owner into a client should not stop working because a CDN had a bad
    minute. No script at all here.

    Picking a client still asks for the owner's password. Signing in as a client
    means reading their leads, their clients' phone numbers and their stored
    credentials — a hijacked console session should not walk into every
    customer's data unchallenged, and the prompt is what makes each entry a
    deliberate, audited act.

    $switchableClients is shared by App\Providers\AppServiceProvider.
--}}
@if(($switchableClients ?? collect())->isNotEmpty())
<details class="pa-switcher">
    <summary class="btn btn-outline-light btn-sm">{{ __('Open a client') }}</summary>

    <div class="pa-switcher__panel card">
        {{-- The tenant travels in the body, not the URL, so no script is needed
             to rewrite the action when the selection changes. --}}
        <form method="POST" action="{{ route('platform-admin.switch') }}" class="card-body">
            @csrf

            <label class="form-label required">{{ __('Client') }}</label>
            <select name="tenant" class="form-select mb-3" required>
                <option value="">{{ __('Choose a client...') }}</option>
                @foreach($switchableClients as $client)
                    <option value="{{ $client->id }}">
                        {{ $client->name }}@if($client->status !== 'active') — {{ $client->status }}@endif
                    </option>
                @endforeach
            </select>

            <label class="form-label required">{{ __('Your password') }}</label>
            <input type="password" name="password" class="form-control mb-3" required autocomplete="current-password">

            <p class="text-secondary small">
                {{ __('You will be signed in as their admin and see everything they see. It is recorded in the audit log, and a banner will show you the way back.') }}
            </p>

            <button type="submit" class="btn btn-primary w-100">{{ __('Open their CRM') }}</button>
        </form>
    </div>
</details>

<style>
    .pa-switcher { position: relative; }
    .pa-switcher > summary { list-style: none; cursor: pointer; }
    .pa-switcher > summary::-webkit-details-marker { display: none; }
    .pa-switcher__panel {
        position: absolute;
        right: 0;
        top: calc(100% + .5rem);
        z-index: 1030;
        width: 22rem;
        max-width: calc(100vw - 2rem);
        text-align: left;
        color: var(--tblr-body-color, #1e293b);
    }
</style>
@endif
