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

];
