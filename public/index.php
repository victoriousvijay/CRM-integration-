<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (! function_exists('insulaRenderPreflightError')) {
    /**
     * Render a minimal preflight failure page before Laravel boots.
     *
     * This avoids opaque 500 responses when the host cannot write the paths
     * Laravel needs just to start the installer.
     *
     * @param  list<string>  $issues
     */
    function insulaRenderPreflightError(array $issues): never
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');

        $issueItems = '';
        foreach ($issues as $issue) {
            $issueItems .= '<li>' . htmlspecialchars($issue, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>InsulaCRM Setup Check</title><style>'
            . 'body{margin:0;padding:32px;font:16px/1.6 Arial,sans-serif;background:#f5f1e8;color:#1e1e1e;}'
            . '.panel{max-width:760px;margin:0 auto;background:#fff;border:1px solid #d9d0c1;border-radius:16px;padding:32px;box-shadow:0 12px 32px rgba(0,0,0,.08);}'
            . 'h1{margin-top:0;font-size:28px;}code,pre{background:#f4f4f4;border-radius:6px;}code{padding:2px 6px;}pre{padding:14px;overflow:auto;}'
            . 'ul{padding-left:20px;}'
            . '</style></head><body><div class="panel"><h1>InsulaCRM cannot start yet</h1>'
            . '<p>The web server cannot write one or more required paths, so Laravel cannot boot the installer safely.</p>'
            . '<ul>' . $issueItems . '</ul>'
            . '<p>Fix the permissions for <code>storage/</code> and <code>bootstrap/cache</code>, then refresh this page.</p>'
            . '<p>Typical Linux commands:</p>'
            . '<pre>chmod -R 775 storage bootstrap/cache' . "\n"
            . '# and ensure the web server user owns or can write these paths</pre>'
            . '</div></body></html>';
        exit;
    }
}

if (! function_exists('insulaIsServerlessReadOnlyDeploy')) {
    /**
     * Vercel (and similar serverless PHP hosts) ship a read-only filesystem
     * outside of /tmp — the app's own storage/ and bootstrap/cache/ can
     * never be written to there, regardless of permissions. Detect that
     * case so we redirect Laravel's writable paths to /tmp instead of
     * failing the preflight check outright. Vercel sets VERCEL=1 for every
     * function invocation.
     */
    function insulaIsServerlessReadOnlyDeploy(): bool
    {
        return getenv('VERCEL') !== false;
    }
}

if (! function_exists('insulaPrepareServerlessStoragePath')) {
    /**
     * Create a per-invocation writable storage directory under /tmp and
     * expose it via LARAVEL_STORAGE_PATH so bootstrap/app.php can point
     * Laravel's storage_path() at the same location (see useStoragePath()
     * there). Sessions/cache/queue are database-backed in this deployment
     * (see .env.example), so this directory only needs to hold Laravel's
     * own scratch files (compiled views, framework locks) — nothing here
     * needs to persist between invocations.
     */
    function insulaPrepareServerlessStoragePath(): string
    {
        $path = sys_get_temp_dir() . '/insula-storage';

        foreach ([
            $path,
            $path . '/logs',
            $path . '/framework',
            $path . '/framework/cache',
            $path . '/framework/cache/data',
            $path . '/framework/sessions',
            $path . '/framework/views',
        ] as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        putenv('LARAVEL_STORAGE_PATH=' . $path);

        return $path;
    }
}

if (! function_exists('insulaCollectPreflightIssues')) {
    /**
     * @return list<string>
     */
    function insulaCollectPreflightIssues(string $basePath): array
    {
        $issues = [];
        $paths = [
            $basePath . '/../storage',
            $basePath . '/../storage/logs',
            $basePath . '/../storage/framework',
            $basePath . '/../storage/framework/cache',
            $basePath . '/../storage/framework/cache/data',
            $basePath . '/../storage/framework/sessions',
            $basePath . '/../storage/framework/views',
            $basePath . '/../bootstrap/cache',
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                $issues[] = 'Missing directory: ' . $path;
                continue;
            }

            if (! is_writable($path)) {
                $issues[] = 'Not writable: ' . $path;
            }
        }

        $logPath = $basePath . '/../storage/logs/laravel.log';
        if (file_exists($logPath) && ! is_writable($logPath)) {
            $issues[] = 'Not writable: ' . $logPath;
        }

        return $issues;
    }
}

if (insulaIsServerlessReadOnlyDeploy()) {
    // Skip the local-filesystem preflight entirely — /tmp is always
    // writable per Vercel's runtime contract, and insulaPrepareServerlessStoragePath()
    // both creates it and points Laravel at it (see bootstrap/app.php).
    insulaPrepareServerlessStoragePath();
} else {
    $preflightIssues = insulaCollectPreflightIssues(__DIR__);
    if ($preflightIssues !== []) {
        insulaRenderPreflightError($preflightIssues);
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
