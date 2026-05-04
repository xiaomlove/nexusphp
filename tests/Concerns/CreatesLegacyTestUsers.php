<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Helpers for building user rows that the legacy login/upload scripts can
 * authenticate against. We avoid `User::factory()` because the existing
 * factory generates `secret` and `passhash` from two independent calls to
 * `mksecret()` (the secret stored in the row doesn't match the secret used
 * to hash the password) and pulls a stylesheet ID through cached settings.
 *
 * We create users with plain attribute arrays to keep the tests readable
 * and to ensure the `passhash` matches the `secret` we store.
 */
trait CreatesLegacyTestUsers
{
    /**
     * Build a confirmed user that will accept legacy md5 password login
     * (auth_key empty so `takelogin.php` falls into the `!auth_key` branch).
     *
     * @param  array<string,mixed>  $overrides
     */
    protected function createLegacyUser(string $password = 'p4ssw0rd-test', array $overrides = []): User
    {
        $secret = bin2hex(random_bytes(10));
        $username = $overrides['username'] ?? 'tu_'.bin2hex(random_bytes(4));
        unset($overrides['username']);

        $attributes = array_merge([
            'username' => $username,
            'email' => $username.'@example.test',
            'secret' => $secret,
            'editsecret' => '',
            'passhash' => md5($secret.$password.$secret),
            'auth_key' => '',
            'status' => User::STATUS_CONFIRMED,
            'enabled' => 'yes',
            'class' => User::CLASS_USER,
            'added' => Carbon::now()->toDateTimeString(),
            'passkey' => bin2hex(random_bytes(16)),
            'stylesheet' => 1,
            'uploadpos' => 'yes',
        ], $overrides);

        return User::create($attributes);
    }

    /**
     * Pending account, used to exercise the "account unconfirmed" branch.
     *
     * @param  array<string,mixed>  $overrides
     */
    protected function createPendingUser(string $password = 'p4ssw0rd-test', array $overrides = []): User
    {
        return $this->createLegacyUser($password, array_merge([
            'status' => User::STATUS_PENDING,
        ], $overrides));
    }
}
