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
