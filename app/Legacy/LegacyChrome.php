<?php

namespace App\Legacy;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * Renders the legacy site chrome — the HTML envelope that
 * `stdhead()` / `stdfoot()` produce in `include/functions.php` —
 * as plain strings, so a modern Laravel controller can wrap its
 * own output in the same chrome without `require`-ing the legacy
 * include chain at the call site.
 *
 * Used by `resources/views/layouts/legacy.blade.php`. A controller
 * that wants to render a small page in the legacy look-and-feel
 * does:
 *
 *     return view('layouts.legacy', [
 *         'title' => 'Logout',
 *         'content' => '<p>You are logged out.</p>',
 *     ]);
 *
 * Phase 1.3 of the strangler-fig migration (see
 * `docs/legacy-strategy.md`). Phase 5 will replace this whole
 * service with native Blade partials, at which point
 * `include/functions.php` can finally drop `stdhead()`/`stdfoot()`.
 */
class LegacyChrome
{
    private bool $bootstrapped = false;

    public function __construct(private readonly Application $app) {}

    /**
     * Capture the HTML that the configured legacy "head" function
     * writes to the output buffer.
     *
     * Defaults to global `stdhead()` from `include/functions.php`;
     * the function name is overridable via `legacy.chrome.head_function`
     * so tests can substitute a fake without colliding with the real
     * `stdhead` (which `bootstrap/app.php` already loads globally).
     */
    public function head(string $title = '', bool $msgalert = true): string
    {
        return $this->capture(function () use ($title, $msgalert): void {
            $this->bootstrap();
            $function = $this->resolveCallable('legacy.chrome.head_function', 'stdhead');
            $function($title, $msgalert);
        });
    }

    /**
     * Capture the HTML that the configured legacy "foot" function
     * writes to the output buffer. See `head()` for the override hook.
     */
    public function foot(): string
    {
        return $this->capture(function (): void {
            $this->bootstrap();
            $function = $this->resolveCallable('legacy.chrome.foot_function', 'stdfoot');
            $function();
        });
    }

    /**
     * Resolve the configured chrome function — must be the name of a
     * defined global function. Throws if the config is malformed or
     * the function is unknown after bootstrap.
     *
     * @return callable(mixed...):void
     */
    private function resolveCallable(string $configKey, string $default): callable
    {
        $name = config($configKey, $default);
        if (! is_string($name) || $name === '') {
            throw new RuntimeException(
                "config({$configKey}) must be a non-empty string; got "
                .var_export($name, true)
            );
        }
        if (! function_exists($name)) {
            throw new RuntimeException(
                "Legacy chrome function `{$name}` is not defined after "
                .'bootstrapping config(legacy.bootstrap_file).'
            );
        }

        return $name;
    }

    /**
     * Pull the legacy bootstrap into scope on first use. Subsequent
     * calls are no-ops — `bittorrent.php` uses `require_once` for
     * its own includes and the function/constant definitions inside
     * blow up on a second include.
     */
    private function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }
        $file = config('legacy.bootstrap_file');
        if (! is_string($file) || ! is_file($file)) {
            throw new RuntimeException(
                'config(legacy.bootstrap_file) does not point at a readable file: '
                .var_export($file, true)
            );
        }
        require_once $file;
        $this->bootstrapped = true;
    }

    /**
     * Run `$callable` with output buffering on and return whatever it
     * wrote. Drops the buffer cleanly on exception so a half-rendered
     * fragment cannot leak into the next response.
     *
     * @param  callable():void  $callable
     */
    private function capture(callable $callable): string
    {
        ob_start();
        try {
            $callable();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
