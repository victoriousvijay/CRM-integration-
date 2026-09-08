<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Identity
    |--------------------------------------------------------------------------
    |
    | The name of the SaaS product itself, as opposed to the name of any
    | tenant running on it. This is what appears where the platform speaks as
    | itself — the installer, the platform admin area, update and licensing
    | copy — and as the fallback brand anywhere no tenant is resolved.
    |
    | Tenant-facing screens should use App\Support\Brand::name() instead, so
    | each client sees their own brand rather than the platform's.
    |
    */

    'name' => env('PLATFORM_NAME', 'ValtCRM'),

    'tagline' => env('PLATFORM_TAGLINE', 'Real Estate CRM'),

    /*
    |--------------------------------------------------------------------------
    | New Client Defaults
    |--------------------------------------------------------------------------
    |
    | What a client is set up with unless the platform owner says otherwise.
    | The shipped values are India's, because that is where this deployment
    | sells; a deployment elsewhere overrides them in .env rather than editing
    | code. They are only defaults — every one is editable per client, before
    | and after onboarding.
    |
    | The timezone must be a real IANA identifier ("Asia/Kolkata"), not an
    | abbreviation like "IST", which PHP does not accept and which produced a
    | tenant whose dates were silently wrong.
    |
    */

    'defaults' => [
        // A two-letter ISO code, which is what tenants.country stores —
        // varchar(2). A country *name* here overflows the column and the save
        // dies with a 500 nobody can read.
        'country' => env('PLATFORM_DEFAULT_COUNTRY', 'IN'),
        'currency' => env('PLATFORM_DEFAULT_CURRENCY', 'INR'),
        'timezone' => env('PLATFORM_DEFAULT_TIMEZONE', 'Asia/Kolkata'),
        'date_format' => env('PLATFORM_DEFAULT_DATE_FORMAT', 'd/m/Y'),
        'measurement_system' => env('PLATFORM_DEFAULT_MEASUREMENT', 'metric'),
        'locale' => env('PLATFORM_DEFAULT_LOCALE', 'en'),
        'business_mode' => env('PLATFORM_DEFAULT_BUSINESS_MODE', 'realestate'),
    ],

];
