<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Base class for tests that need a real database.
 *
 * Feature tests run against a fresh MySQL instance with all migrations
 * applied. Each test runs inside a transaction that gets rolled back on
 * teardown, so individual tests stay isolated without paying the cost of
 * re-running 200+ migrations between them.
 *
 * The CI workflow (`phpunit-feature` job) provisions the MySQL service,
 * runs migrations once, and then invokes `phpunit --testsuite=Feature`.
 * Locally, point a MySQL instance at the connection in `.env.testing`
 * and run `php artisan migrate --env=testing` before invoking PHPUnit.
 */
abstract class FeatureTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // `config/cache.php` and `config/session.php` hardcode their
        // default driver to `redis`, ignoring CACHE_DRIVER / SESSION_DRIVER.
        // Forcing the array driver here keeps Feature tests independent of
        // a running Redis instance for everything that doesn't explicitly
        // exercise Redis.
        config([
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
    }
}
