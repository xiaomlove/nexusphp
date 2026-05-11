<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/image.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Original legacy flow:
 *
 *     $action    = $_GET['action']    ?? '';
 *     $imagehash = $_GET['imagehash'] ?? '';
 *     if ($action !== 'regimage') {
 *         http_response_code(404);
 *         exit('Invalid captcha action');
 *     }
 *     $driver = captcha_manager()->driver('image');
 *     if (!method_exists($driver, 'outputImage')) {
 *         http_response_code(404);
 *         exit('Captcha driver does not support image rendering');
 *     }
 *     $driver->outputImage($imagehash);
 *
 * The migrated controller preserves the contract exactly:
 *   - GET `/image.php?action=regimage&imagehash=...` returns a PNG.
 *   - Any other `action` value → 404 + plain-text body.
 *   - A captcha driver that doesn't render images (recaptcha,
 *     turnstile) → 404 + plain-text body.
 *
 * `ImageCaptchaDriver::outputImage()` writes the PNG directly to
 * stdout (via `header('Content-type: image/png'); imagepng($im)`),
 * so we wrap it in `ob_start()` / `ob_get_clean()` and surface the
 * captured bytes as a Laravel `Response` with the right
 * Content-Type header. That keeps middleware visibility (Sentry,
 * structured logs) without forcing the driver to grow a
 * "return-binary" API.
 */
class ImageCaptchaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if ($request->query('action') !== 'regimage') {
            return new Response('Invalid captcha action', 404);
        }

        $driver = captcha_manager()->driver('image');
        if (! method_exists($driver, 'outputImage')) {
            return new Response('Captcha driver does not support image rendering', 404);
        }

        $imagehash = (string) $request->query('imagehash', '');

        ob_start();
        try {
            $driver->outputImage($imagehash);
        } finally {
            $body = (string) ob_get_clean();
        }

        return new Response($body, 200, ['Content-Type' => 'image/png']);
    }
}
