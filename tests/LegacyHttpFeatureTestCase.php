<?php

namespace Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Base class for Feature tests that exercise legacy procedural PHP scripts in
 * `public/*.php` (announce, login, upload, ...). They cannot be invoked through
 * Laravel's test client because they are not Laravel routes and they exit/die
 * mid-execution, so we run them under a real PHP built-in web server and hit
 * them over HTTP.
 *
 * Lifecycle:
 *   - setUpBeforeClass starts `php -S 127.0.0.1:<port> -t public/` once for
 *     the test class. The same server instance is reused for every test in
 *     the class.
 *   - tearDownAfterClass terminates it.
 *
 * Database isolation:
 *   - We deliberately do NOT use the DatabaseTransactions trait. The built-in
 *     server runs in a separate PHP process with its own MySQL connection, so
 *     it cannot see uncommitted writes made inside the test's transaction.
 *   - Tests must use unique-enough fixture data (random username, random
 *     info_hash, ...) and clean up shared state in `setUp` (loginattempts,
 *     ratelimits) so each test starts from a clean slate.
 *
 * Cache:
 *   - We flush the legacy Redis cache (`$Cache->flushAll`) before each test
 *     so stale Setting / user caches from a previous run don't leak.
 */
abstract class LegacyHttpFeatureTestCase extends TestCase
{
    /** @var resource|null */
    protected static $serverProcess = null;

    protected static int $serverPort = 0;

    protected static ?string $serverLogPath = null;

    /** @var array<int,resource>|null */
    protected static ?array $serverPipes = null;

    protected Client $http;

    protected CookieJar $cookies;

    protected function setUp(): void
    {
        parent::setUp();

        // Force the array driver so Laravel-side cache/session don't try Redis.
        // The legacy `$Cache` singleton in the built-in server uses Redis directly
        // (controlled by REDIS_HOST in .env); that one is intentionally left alone.
        config([
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);

        // Start the server lazily on the first test so the Laravel application
        // is already booted (we need `base_path()` and storage paths to be
        // resolvable). The server is reused for every test in the run and
        // shut down via `register_shutdown_function`.
        static::ensureBuiltinServerStarted();

        $this->cookies = new CookieJar;
        $this->http = new Client([
            'base_uri' => static::serverUrl().'/',
            'http_errors' => false,
            'allow_redirects' => false,
            'timeout' => 10,
            'cookies' => $this->cookies,
            'headers' => [
                'X-Forwarded-For' => '127.0.0.1',
                // PHP's built-in server doesn't populate REQUEST_SCHEME, and
                // `Nexus->getRequestSchema()` enforces a non-null string return
                // type. Provide the forwarded-proto fallback so legacy callers
                // that need a schema (URL builders, avatar helpers, etc.) work.
                'X-Forwarded-Proto' => 'http',
            ],
        ]);

        $this->resetTransientFixtures();
    }

    /**
     * Reset state that legacy scripts mutate across requests: rate-limit rows
     * and any cached singletons that survive between requests inside the
     * built-in server's long-running PHP process.
     */
    protected function resetTransientFixtures(): void
    {
        if (DB::getSchemaBuilder()->hasTable('loginattempts')) {
            DB::table('loginattempts')->delete();
        }
    }

    protected static function serverUrl(): string
    {
        return 'http://127.0.0.1:'.static::$serverPort;
    }

    protected static function ensureBuiltinServerStarted(): void
    {
        if (static::$serverProcess !== null) {
            return;
        }

        static::startBuiltinServer();
        register_shutdown_function([static::class, 'stopBuiltinServer']);
    }

    protected static function startBuiltinServer(): void
    {
        if (static::$serverProcess !== null) {
            return;
        }

        $port = static::pickFreePort();
        $logDir = base_path('storage/logs');
        if (! is_dir($logDir)) {
            mkdir($logDir, 0o775, true);
        }
        static::$serverLogPath = $logDir.'/builtin-server-'.$port.'.log';
        @unlink(static::$serverLogPath);

        // Pass the command as an array so PHP doesn't wrap it in `/bin/sh -c`.
        // With shell wrapping, `proc_terminate` sends the signal to the shell
        // rather than to `php -S`, leaving the server orphaned.
        $cmd = [
            PHP_BINARY,
            '-d', 'display_errors=1',
            '-d', 'log_errors=1',
            '-d', 'error_log='.static::$serverLogPath,
            '-S', '127.0.0.1:'.$port,
            '-t', base_path('public'),
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', static::$serverLogPath, 'a'],
            2 => ['file', static::$serverLogPath, 'a'],
        ];

        static::$serverPipes = [];
        $process = proc_open($cmd, $descriptors, static::$serverPipes, base_path());
        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start built-in PHP server');
        }
        static::$serverProcess = $process;
        static::$serverPort = $port;

        if (! static::waitForServerReady($port, 10.0)) {
            $log = static::$serverLogPath && is_readable(static::$serverLogPath)
                ? @file_get_contents(static::$serverLogPath)
                : '<no log>';
            static::stopBuiltinServer();
            throw new RuntimeException("Built-in PHP server did not become ready on 127.0.0.1:$port within 10s.\n$log");
        }
    }

    protected static function stopBuiltinServer(): void
    {
        if (static::$serverProcess === null) {
            return;
        }

        $status = proc_get_status(static::$serverProcess);
        if ($status['running'] ?? false) {
            // SIGTERM. fall back to proc_terminate(SIGKILL) if it's still alive.
            proc_terminate(static::$serverProcess, 15);
            $deadline = microtime(true) + 3.0;
            while (microtime(true) < $deadline) {
                $status = proc_get_status(static::$serverProcess);
                if (! ($status['running'] ?? false)) {
                    break;
                }
                usleep(50_000);
            }
            $status = proc_get_status(static::$serverProcess);
            if ($status['running'] ?? false) {
                proc_terminate(static::$serverProcess, 9);
            }
        }

        if (is_array(static::$serverPipes)) {
            foreach (static::$serverPipes as $pipe) {
                if (is_resource($pipe)) {
                    @fclose($pipe);
                }
            }
        }

        @proc_close(static::$serverProcess);
        static::$serverProcess = null;
        static::$serverPipes = null;
    }

    private static function pickFreePort(): int
    {
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($sock === false) {
            throw new RuntimeException('socket_create failed');
        }
        if (! socket_bind($sock, '127.0.0.1', 0)) {
            socket_close($sock);
            throw new RuntimeException('socket_bind failed');
        }
        $port = 0;
        socket_getsockname($sock, $addr, $port);
        socket_close($sock);

        return (int) $port;
    }

    private static function waitForServerReady(int $port, float $timeout): bool
    {
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $errno = 0;
            $errstr = '';
            $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
            if (is_resource($conn)) {
                fclose($conn);

                return true;
            }
            usleep(100_000);
        }

        return false;
    }

    protected function assertCookieSet(string $name, string $message = ''): void
    {
        foreach ($this->cookies->toArray() as $cookie) {
            if (($cookie['Name'] ?? null) === $name) {
                $this->addToAssertionCount(1);

                return;
            }
        }
        $this->fail($message !== ''
            ? $message
            : "Expected cookie [$name] to be set but jar was empty or missing it.");
    }

    protected function assertCookieNotSet(string $name, string $message = ''): void
    {
        foreach ($this->cookies->toArray() as $cookie) {
            if (($cookie['Name'] ?? null) === $name) {
                $this->fail($message !== ''
                    ? $message
                    : "Cookie [$name] should NOT have been set.");
            }
        }
        $this->addToAssertionCount(1);
    }

    /**
     * Read tail of the built-in server log to surface failure context in test
     * assertions. Useful in `assertSame(..., $response->getStatusCode(), $this->serverLogTail())`.
     */
    protected function serverLogTail(int $lines = 30): string
    {
        if (! static::$serverLogPath || ! is_readable(static::$serverLogPath)) {
            return '';
        }
        $content = @file_get_contents(static::$serverLogPath);
        if ($content === false) {
            return '';
        }
        $rows = preg_split('/\r?\n/', trim($content));
        $rows = array_slice($rows, -$lines);

        return "\nbuilt-in server log tail:\n".implode("\n", $rows);
    }
}
