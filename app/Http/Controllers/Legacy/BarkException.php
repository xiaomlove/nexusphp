<?php

namespace App\Http\Controllers\Legacy;

/**
 * Internal exception used by `TakeUploadController` and
 * `TakeEditController` to bubble legacy `bark($msg); exit;` calls
 * out of the controller's logic body so the controller's `__invoke`
 * can render the same `genbark()` error envelope as the legacy
 * scripts without a mid-request `exit`.
 *
 * The legacy contract was: any validation failure rendered an HTTP
 * 200 page with a `genbark()` envelope (legacy chrome + the message)
 * and terminated the FPM worker with `exit`. Inside the Laravel
 * pipeline `exit` skips middleware, so the controllers throw this
 * exception instead and the catch-block calls `genbark()` with the
 * output buffered into a `Response`.
 */
class BarkException extends \RuntimeException {}
