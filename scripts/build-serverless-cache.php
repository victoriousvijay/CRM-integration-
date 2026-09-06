<?php

/**
 * Warm Laravel's build-time caches for a serverless deployment.
 *
 * Vercel gives each request a fresh, read-only process, so anything Laravel
 * would normally compile once and reuse (the config array, the route
 * collection, the event map, every Blade template) is otherwise recompiled
 * on every cold start. Doing it here bakes the results into the deployment
 * bundle instead; public/index.php points Laravel at them (see
 * insulaPrepareServerlessStoragePath()).
 *
 * Only runs on the serverless host, and never fails the build: a cache that
 * can't be built just means the app compiles it at runtime as before.
 *
 * Deliberately excludes config:cache. That one freezes the environment values
 * present at build time, which both breaks changing an environment variable
 * without a redeploy and hard-fails the app if a variable happens not to be
 * exposed to the build. Config parsing is a small share of boot cost compared
 * to route registration and Blade compilation, so it isn't worth that.
 */

if (getenv('VERCEL') === false) {
    exit(0);
}

chdir(dirname(__DIR__));

foreach (['event:cache', 'route:cache', 'view:cache'] as $command) {
    $output = [];
    $status = 0;

    exec('php artisan '.escapeshellarg($command).' 2>&1', $output, $status);

    fwrite(
        STDOUT,
        $status === 0
            ? "[serverless-cache] {$command}: ok\n"
            : "[serverless-cache] {$command}: skipped\n".implode("\n", $output)."\n"
    );

    // A failed cache is recoverable (Laravel falls back to compiling at
    // runtime), but a half-written one is not — drop it so the runtime
    // fallback is what actually happens.
    if ($status !== 0) {
        foreach ([
            'event:cache' => 'bootstrap/cache/events.php',
            'route:cache' => 'bootstrap/cache/routes-v7.php',
        ] as $failed => $artifact) {
            if ($failed === $command && is_file($artifact)) {
                @unlink($artifact);
            }
        }
    }
}

exit(0);
