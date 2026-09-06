<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The public API (/api/v1/*) is designed to be called directly from a
    | tenant's own website (browser JS via embed.js, or server-side). Since
    | every request is authenticated by an opaque API/embed key rather than
    | cookies/session, allowing all origins here does not expose any
    | tenant's data to a page that doesn't already hold that key.
    |
    */

    'paths' => ['api/*', 'forms/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
