<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the phone sees between tapping the icon and the first page.
 *
 * The bug this guards against is silent: the manifest declared one edge-to-edge
 * icon as `any maskable`, so Android cropped the wordmark off the launch screen
 * and nothing anywhere reported an error. Only a screenshot showed it.
 */
class PwaLaunchTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manifest_ships_a_padded_icon_for_launchers_that_crop(): void
    {
        $icons = collect($this->get('/manifest.json')->assertOk()->json('icons'));

        // A launcher may crop a maskable icon to any shape inside the canvas.
        // Declaring the full-bleed artwork maskable is what cut the wordmark.
        $this->assertTrue(
            $icons->every(fn ($icon) => in_array($icon['purpose'], ['any', 'maskable'], true)),
            'An icon must declare one purpose or the other, never both from the same file.'
        );

        foreach (['any', 'maskable'] as $purpose) {
            $sizes = $icons->where('purpose', $purpose)->pluck('sizes')->all();

            $this->assertContains('192x192', $sizes, "No 192px `{$purpose}` icon.");
            $this->assertContains('512x512', $sizes, "No 512px `{$purpose}` icon.");
        }

        foreach ($icons as $icon) {
            $path = public_path(parse_url($icon['src'], PHP_URL_PATH));

            $this->assertFileExists($path, "The manifest points at a file that is not there: {$icon['src']}");
        }
    }

    public function test_the_launch_background_matches_the_icon_so_it_is_not_a_square_on_white(): void
    {
        $this->get('/manifest.json')->assertOk()->assertJsonPath('background_color', '#000000');
    }

    public function test_the_splash_animation_is_present_but_hidden_until_the_script_allows_it(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        // Rendered hidden and unhidden only for a standalone launch: in a browser
        // tab this server-rendered app reloads on every click, and an animation
        // that replayed each time would be worse than none.
        $this->assertStringContainsString('id="valt-splash" hidden', $html);
        $this->assertStringContainsString('display-mode: standalone', $html);
        $this->assertStringContainsString('valt-splash-played', $html);
    }
}
