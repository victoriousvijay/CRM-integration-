<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CheckInstalled
{
    /**
     * Redirect to installer if not installed, block installer if installed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $installed = $this->isInstalled();
        $isInstallRoute = $request->is('install*');
        $allowInstallCompletionFlow = (
            $request->is('install/complete')
            && (
                ($request->hasSession() && $request->session()->has('installation_completed'))
                || Session::has('installation_completed')
            )
        );

        // Not installed and not on install route -> redirect to installer
        if (!$installed && !$isInstallRoute) {
            return redirect('/install');
        }

        // Installed and on install route -> redirect to login
        if ($installed && $isInstallRoute && ! $allowInstallCompletionFlow) {
            return redirect('/login');
        }

        return $next($request);
    }

    private function isInstalled(): bool
    {
        // A missing .env only means "not installed" on a host where config
        // actually comes from a file. Platforms like Vercel inject config as
        // real environment variables and have a read-only filesystem, so
        // there is no .env to find — treat a configured APP_KEY as equivalent
        // evidence that the app has been set up.
        if (! File::exists(base_path('.env')) && ! config('app.key')) {
            return false;
        }

        $markerPath = storage_path('installed.lock');

        if (File::exists($markerPath)) {
            return true;
        }

        try {
            if (! Schema::hasTable('tenants') || ! Schema::hasTable('users')) {
                return false;
            }

            if (DB::table('tenants')->exists() && DB::table('users')->exists()) {
                File::put($markerPath, 'Recovered install marker on ' . now()->toDateTimeString());

                return true;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
