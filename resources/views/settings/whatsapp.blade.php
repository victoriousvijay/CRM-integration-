@extends('layouts.app')

@section('title', __('WhatsApp'))
@section('page-title', __('WhatsApp Automation'))

@section('content')
<div class="row">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="card-title">{{ __('Connection') }}</h3>
                <p class="text-secondary">
                    {{ __('Messages go out from your own WhatsApp Business number through Meta\'s Cloud API. Get these four values from Meta Business Manager → WhatsApp → API Setup.') }}
                </p>

                <form method="POST" action="{{ route('settings.whatsapp.connection') }}">
                    @csrf
                    @method('PUT')

                    <label class="form-check form-switch mb-3">
                        <input type="hidden" name="enabled" value="0">
                        <input class="form-check-input" type="checkbox" name="enabled" value="1"
                               @checked($settings['enabled'] ?? false)>
                        <span class="form-check-label">{{ __('Send WhatsApp messages automatically') }}</span>
                    </label>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="phone_number_id">{{ __('Phone Number ID') }}</label>
                            <input type="text" id="phone_number_id" name="phone_number_id" class="form-control"
                                   value="{{ old('phone_number_id', $settings['phone_number_id'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="business_account_id">{{ __('WhatsApp Business Account ID') }}</label>
                            <input type="text" id="business_account_id" name="business_account_id" class="form-control"
                                   value="{{ old('business_account_id', $settings['business_account_id'] ?? '') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="access_token">{{ __('Permanent Access Token') }}</label>
                            <input type="password" id="access_token" name="access_token" class="form-control"
                                   placeholder="{{ $hasToken ? __('Saved — leave blank to keep it') : __('Paste the token') }}"
                                   autocomplete="new-password">
                            <small class="form-hint">
                                {{ __('Stored encrypted and never shown again. Use a System User token so it does not expire.') }}
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="default_country_code">{{ __('Default country code') }}</label>
                            <input type="text" id="default_country_code" name="default_country_code" class="form-control"
                                   value="{{ old('default_country_code', $settings['default_country_code'] ?? '') }}"
                                   placeholder="91">
                            <small class="form-hint">{{ __('Used when a client is saved with a local number.') }}</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="default_language">{{ __('Default template language') }}</label>
                            <input type="text" id="default_language" name="default_language" class="form-control"
                                   value="{{ old('default_language', $settings['default_language'] ?? 'en') }}">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">{{ __('Save connection') }}</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h3 class="card-title">{{ __('When to send') }}</h3>
                <p class="text-secondary">
                    {{ __('Meta only delivers business-initiated messages from a template it has approved, so create the template there first and name it here.') }}
                </p>

                <form method="POST" action="{{ route('settings.whatsapp.template') }}" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label required" for="event">{{ __('Send when') }}</label>
                        <select id="event" name="event" class="form-select" required>
                            <option value="lead.created" @selected(old('event') === 'lead.created')>{{ __('A new lead is added') }}</option>
                            <option value="lead.status_changed" @selected(old('event') === 'lead.status_changed')>{{ __('A lead moves to a status') }}</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="status">{{ __('Which status') }}</label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                            <option value="">{{ __('— only for a status change —') }}</option>
                            @foreach($statuses as $slug => $label)
                                <option value="{{ $slug }}" @selected(old('status') === $slug)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label required" for="template_name">{{ __('Template name in Meta') }}</label>
                        <input type="text" id="template_name" name="template_name" class="form-control"
                               value="{{ old('template_name') }}" placeholder="visit_thank_you" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="language_code">{{ __('Language') }}</label>
                        <input type="text" id="language_code" name="language_code" class="form-control"
                               value="{{ old('language_code', $settings['default_language'] ?? 'en') }}" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">{{ __('Fill the template placeholders, in order') }}</label>
                        <div class="row g-2">
                            @for($i = 1; $i <= 4; $i++)
                                <div class="col-md-3">
                                    <select name="variables[]" class="form-select">
                                        <option value="">{{ __('Placeholder :n — not used', ['n' => $i]) }}</option>
                                        @foreach($variables as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endfor
                        </div>
                        <small class="form-hint">
                            {{ __('These map to the numbered placeholders in the body of your approved template.') }}
                        </small>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="preview">{{ __('What the template says (for your own reference)') }}</label>
                        <textarea id="preview" name="preview" rows="2" class="form-control"
                                  placeholder="{{ __('Thank you for visiting :one with :two. Your enquiry status is :three.', ['one' => '{1}', 'two' => '{2}', 'three' => '{3}']) }}">{{ old('preview') }}</textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">{{ __('Save template') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('Configured templates') }}</h3>
            </div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>{{ __('Sends when') }}</th>
                            <th>{{ __('Template') }}</th>
                            <th>{{ __('Placeholders') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($templates as $template)
                            <tr>
                                <td>
                                    @if($template->event === 'lead.created')
                                        {{ __('New lead added') }}
                                    @else
                                        {{ __('Status → :status', ['status' => $statuses[$template->status] ?? $template->status]) }}
                                    @endif
                                    @unless($template->is_active)
                                        <span class="badge bg-secondary-lt ms-1">{{ __('Off') }}</span>
                                    @endunless
                                </td>
                                <td>
                                    <code>{{ $template->template_name }}</code>
                                    <span class="text-secondary">({{ $template->language_code }})</span>
                                    @if($template->preview)
                                        <div class="text-secondary small">{{ $template->preview }}</div>
                                    @endif
                                </td>
                                <td class="text-secondary small">
                                    {{ collect($template->variables ?? [])->map(fn ($v) => $variables[$v] ?? $v)->implode(', ') ?: '—' }}
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('settings.whatsapp.template.delete', $template) }}"
                                          onsubmit="return confirm('{{ __('Remove this template mapping?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-ghost-danger btn-sm">{{ __('Remove') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-secondary py-4">
                                    {{ __('No templates configured — nothing is sent automatically yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="card-title">{{ __('Send a test') }}</h3>
                <p class="text-secondary">{{ __('Sends a real message to a real lead, so check with someone who expects it.') }}</p>
                <form method="POST" action="{{ route('settings.whatsapp.test') }}" class="row g-2">
                    @csrf
                    <div class="col-12">
                        <label class="form-label" for="test_lead">{{ __('Lead') }}</label>
                        <select id="test_lead" name="lead_id" class="form-select" required>
                            @foreach(\App\Models\Lead::whereNotNull('phone')->latest()->limit(30)->get() as $lead)
                                <option value="{{ $lead->id }}">{{ trim($lead->first_name.' '.$lead->last_name) }} — {{ $lead->phone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="test_template">{{ __('Template') }}</label>
                        <select id="test_template" name="template_id" class="form-select" required>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->template_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-primary w-100">{{ __('Send test message') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('Recent messages') }}</h3>
            </div>
            <div class="list-group list-group-flush">
                @forelse($recent as $message)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <strong>{{ $message->lead ? trim($message->lead->first_name.' '.$message->lead->last_name) : $message->to_number }}</strong>
                                <div class="text-secondary small">
                                    {{ $message->template_name ?? '—' }} · {{ $message->created_at?->format('d M, g:i A') }}
                                </div>
                                @if($message->error)
                                    <div class="text-danger small">{{ $message->error }}</div>
                                @endif
                            </div>
                            <span class="badge {{ ['sent' => 'bg-green-lt', 'failed' => 'bg-red-lt'][$message->status] ?? 'bg-secondary-lt' }}">
                                {{ __(ucfirst($message->status)) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-secondary">{{ __('Nothing sent yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
