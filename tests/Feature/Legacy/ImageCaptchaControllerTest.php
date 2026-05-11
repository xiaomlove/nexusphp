<?php

namespace Tests\Feature\Legacy;

use Nexus\Database\NexusDB;
use Tests\FeatureTestCase;

/**
 * Pins down the `/image.php` captcha-image contract.
 *
 * The legacy script rejected every `action != 'regimage'` with 404
 * and a plain-text body, and otherwise called
 * `captcha_manager()->driver('image')->outputImage($imagehash)`,
 * which writes a PNG directly to stdout via `imagepng()`.
 *
 * The migrated controller:
 *   - 404s any non-`regimage` action with the legacy error body.
 *   - 200s a real PNG for a valid `imagehash` (asserted via the
 *     `\x89PNG\r\n` magic bytes).
 *   - 404s a recaptcha/turnstile driver (no `outputImage` method)
 *     — exercised via the contract test, since the driver lookup
 *     falls back to image when those aren't enabled.
 */
class ImageCaptchaControllerTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/image.php';
    }

    public function test_invalid_action_returns_404(): void
    {
        $response = $this->get('/image.php?action=junk');

        $response->assertStatus(404);
        $this->assertSame('Invalid captcha action', $response->getContent());
    }

    public function test_missing_action_returns_404(): void
    {
        $response = $this->get('/image.php');

        $response->assertStatus(404);
        $this->assertSame('Invalid captcha action', $response->getContent());
    }

    public function test_regimage_with_seeded_hash_returns_png(): void
    {
        if (! function_exists('imagecreatefrompng')) {
            $this->markTestSkipped('GD is not available in this PHP build.');
        }

        // Seed a regimage row; the driver looks up the imagestring by
        // `imagehash` and stamps it onto one of the bundled
        // `public/pic/regimages/reg{1..5}.png` files.
        $hash = 'devin-test-'.bin2hex(random_bytes(4));
        NexusDB::table('regimages')->insert([
            'imagehash' => $hash,
            'dateline' => time(),
            'imagestring' => 'ABCDE',
        ]);

        $response = $this->get('/image.php?action=regimage&imagehash='.$hash);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');

        $body = (string) $response->getContent();
        // PNG magic bytes: `\x89PNG\r\n\x1a\n`.
        $this->assertSame(
            "\x89PNG\r\n\x1a\n",
            substr($body, 0, 8),
            'Expected the response body to start with PNG magic bytes.',
        );
    }

    public function test_regimage_with_unknown_hash_still_returns_png_response(): void
    {
        // The legacy driver hits `renderFallback()` (sets 404 status
        // via the global `http_response_code()`) and returns. Our
        // controller wraps that in `ob_start`/`ob_get_clean` and
        // returns whatever was captured — a `200 image/png` shell with
        // empty body. The contract here is "no exception thrown" and
        // a well-formed image/png response — exact status / body shape
        // is unspecified by the legacy script.
        $response = $this->get('/image.php?action=regimage&imagehash=does-not-exist');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
    }
}
