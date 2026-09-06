<?php

/**
 * Entry point for Vercel's PHP runtime (vercel-php). Every request not
 * matched by a static file under public/ is routed here (see vercel.json),
 * so this simply re-uses Laravel's normal public/index.php bootstrap.
 *
 * Vercel's filesystem is read-only outside /tmp and each invocation is a
 * fresh, stateless process — see DEPLOYMENT.md for what that means for
 * sessions/cache/queue/storage configuration (all must be database/S3
 * backed, never "file"/"local").
 */

$publicPath = dirname(__DIR__).'/public';

$_SERVER['SCRIPT_FILENAME'] = $publicPath.'/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

if (($_GET['vercel_diag'] ?? null) === 'db') {
    // One-off diagnostic: report whether the database is reachable, and with
    // what error if not. Never prints credentials — host/port/database only.
    header('Content-Type: application/json');
    $out = [];

    try {
    // Same /tmp cache + storage redirection public/index.php performs; the
    // probe bypasses that file, so replicate it here.
    $tmp = sys_get_temp_dir();
    foreach ([
        'LARAVEL_STORAGE_PATH' => $tmp.'/insula-storage',
        'APP_SERVICES_CACHE' => $tmp.'/insula-bootstrap-cache/services.php',
        'APP_PACKAGES_CACHE' => $tmp.'/insula-bootstrap-cache/packages.php',
        'APP_CONFIG_CACHE' => $tmp.'/insula-bootstrap-cache/config.php',
        'APP_ROUTES_CACHE' => $tmp.'/insula-bootstrap-cache/routes-v7.php',
        'APP_EVENTS_CACHE' => $tmp.'/insula-bootstrap-cache/events.php',
    ] as $k => $v) {
        putenv("{$k}={$v}");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
    @mkdir($tmp.'/insula-bootstrap-cache', 0775, true);
    @mkdir($tmp.'/insula-storage/framework/views', 0775, true);

    require dirname(__DIR__).'/vendor/autoload.php';
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Http\Kernel::class)->bootstrap();

    $cfg = config('database.connections.'.config('database.default'));
    $out = [
        'driver' => $cfg['driver'] ?? null,
        'host' => $cfg['host'] ?? null,
        'port' => $cfg['port'] ?? null,
        'database' => $cfg['database'] ?? null,
        'username' => $cfg['username'] ?? null,
        'sslmode' => $cfg['sslmode'] ?? null,
        'url_set' => ! empty($cfg['url']),
        'url_host' => ! empty($cfg['url']) ? (parse_url($cfg['url'], PHP_URL_HOST).':'.parse_url($cfg['url'], PHP_URL_PORT)) : null,
    ];

    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $out['connected'] = true;
        $out['tenants'] = \Illuminate\Support\Facades\DB::table('tenants')->count();
        $out['users'] = \Illuminate\Support\Facades\DB::table('users')->count();
    } catch (\Throwable $e) {
        $out['connected'] = false;
        $out['error'] = $e->getMessage();
    }
    } catch (\Throwable $outer) {
        $out['probe_failed'] = get_class($outer).': '.$outer->getMessage().' at '.$outer->getFile().':'.$outer->getLine();
    }

    echo json_encode($out, JSON_PRETTY_PRINT);
    exit;
}

if (($_GET['vercel_diag'] ?? null) === 'bootstrap') {
    // One-off diagnostic: step through Laravel's own boot sequence by hand,
    // logging after each stage, to find exactly which step throws. See
    // VERCEL_DIAG commit history for context. Remove once root-caused.
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        error_log("VERCEL_DIAG_PHP_ERROR: [$errno] $errstr in $errfile:$errline");
        return false;
    });

    try {
        error_log('VERCEL_DIAG_STEP: pre-require');
        require dirname(__DIR__).'/vendor/autoload.php';
        error_log('VERCEL_DIAG_STEP: autoload done');

        $app = require dirname(__DIR__).'/bootstrap/app.php';
        error_log('VERCEL_DIAG_STEP: bootstrap/app.php done, class='.get_class($app));

        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        error_log('VERCEL_DIAG_STEP: kernel resolved');

        $request = \Illuminate\Http\Request::capture();
        $response = $kernel->handle($request);
        error_log('VERCEL_DIAG_STEP: kernel handled, status='.$response->getStatusCode());

        $response->send();
        $kernel->terminate($request, $response);
        error_log('VERCEL_DIAG_STEP: terminated OK');
    } catch (\Throwable $e) {
        error_log('VERCEL_DIAG_STEP_FAILED: '.get_class($e).': '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
        error_log('VERCEL_DIAG_STEP_TRACE: '.$e->getTraceAsString());
        http_response_code(500);
        header('Content-Type: text/plain');
        echo 'diag step logged';
    }
    exit;
}

try {
    require $publicPath.'/index.php';
} catch (\Throwable $e) {
    error_log('VERCEL_DIAG_ROOT_CAUSE: '.get_class($e).': '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
    error_log('VERCEL_DIAG_TRACE: '.$e->getTraceAsString());
    if ($prev = $e->getPrevious()) {
        error_log('VERCEL_DIAG_PREVIOUS: '.get_class($prev).': '.$prev->getMessage().' at '.$prev->getFile().':'.$prev->getLine());
    }
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'diag logged';
}
