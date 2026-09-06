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
