<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cron endpoint runs the whole scheduler and drains the queue, and it sits
 * outside the auth middleware because Vercel Cron cannot hold a login session.
 * Its only protection is the shared secret, so that check is worth pinning.
 */
class CronEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_when_no_secret_is_configured(): void
    {
        config(['app.cron_secret' => null]);

        $this->get('/internal/cron/schedule')->assertForbidden();
    }

    public function test_it_refuses_a_wrong_or_missing_secret(): void
    {
        config(['app.cron_secret' => 'the-real-secret']);

        $this->get('/internal/cron/schedule')->assertForbidden();

        $this->withHeader('Authorization', 'Bearer wrong-secret')
            ->get('/internal/cron/schedule')
            ->assertForbidden();
    }

    public function test_it_runs_for_the_configured_secret(): void
    {
        config(['app.cron_secret' => 'the-real-secret']);

        $this->withHeader('Authorization', 'Bearer the-real-secret')
            ->get('/internal/cron/schedule')
            ->assertOk()
            ->assertJson(['success' => true]);
    }
}
