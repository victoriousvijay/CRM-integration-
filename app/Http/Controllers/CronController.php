<?php

namespace App\Http\Controllers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Runs the Laravel scheduler and drains a batch of queued jobs on every
 * invocation. Exists because Vercel has no persistent process to run
 * `php artisan schedule:work` / `queue:work` — instead Vercel Cron hits this
 * endpoint on a schedule (see vercel.json's "crons" entry) and each hit does
 * one pass of both.
 *
 * If you deploy on a host that CAN run persistent processes (a VPS, Fly,
 * Railway, etc.) prefer the real `schedule:work` + `queue:work` daemons
 * instead — this endpoint is a Vercel-specific workaround, documented in
 * DEPLOYMENT.md.
 */
class CronController extends Controller
{
    public function run(Request $request, Schedule $schedule)
    {
        // Vercel sends "Authorization: Bearer $CRON_SECRET" when it invokes a
        // scheduled Cron Job, provided CRON_SECRET is set —
        // https://vercel.com/docs/cron-jobs/manage-cron-jobs#securing-cron-jobs
        //
        // Refuse when no secret is configured rather than running for anyone.
        // This endpoint runs the whole scheduler and drains the queue, so left
        // open it is both a way to burn a plan's compute on demand and a way to
        // fire the business actions the schedule owns — digests, notifications,
        // lead assignment, paid AI calls.
        $secret = config('app.cron_secret');

        if (blank($secret)) {
            Log::warning('Cron endpoint refused: CRON_SECRET is not configured. See DEPLOYMENT.md.');

            abort(403);
        }

        if (! hash_equals("Bearer {$secret}", (string) $request->header('Authorization'))) {
            abort(403);
        }

        $schedule->dueEvents(app())->each(function ($event) {
            $event->run(app());
        });

        \Illuminate\Support\Facades\Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--max-time' => 50,
            '--tries' => 3,
        ]);

        return response()->json(['success' => true, 'ran_at' => now()->toIso8601String()]);
    }
}
