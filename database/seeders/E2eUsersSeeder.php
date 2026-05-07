<?php

namespace Database\Seeders;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Database\Seeder;

/**
 * Seeds three deterministic users for end-to-end testing:
 *
 *   e2eadmin / E2eAdmin2026   class=CLASS_STAFF_LEADER (16)
 *   e2estaff / E2eStaff2026   class=CLASS_MODERATOR    (13)
 *   e2euser  / E2eUser2026    class=CLASS_USER         (1)
 *
 * The Playwright test suite logs in as these users via
 * `tests/e2e/helpers/api-login.ts` (HTTP POST to `takelogin.php`).
 *
 * Idempotent: running the seeder twice does not fail and does not
 * duplicate users (existing usernames are skipped). Reuses the exact
 * same code path the install wizard uses to create the first
 * administrator (`UserRepository::store()`), so seeded users behave
 * identically to manually-registered ones.
 */
class E2eUsersSeeder extends Seeder
{
    /**
     * Login fixtures consumed by Playwright. Keep in sync with
     * `tests/e2e/fixtures/auth.ts` if you change a username/password.
     *
     * @var list<array{username: string, email: string, password: string, class: string, id?: int}>
     */
    public const USERS = [
        [
            'username' => 'e2eadmin',
            'email' => 'e2eadmin@example.com',
            'password' => 'E2eAdmin2026',
            'class' => User::CLASS_STAFF_LEADER,
            'id' => 1,
        ],
        [
            'username' => 'e2estaff',
            'email' => 'e2estaff@example.com',
            'password' => 'E2eStaff2026',
            'class' => User::CLASS_MODERATOR,
        ],
        [
            'username' => 'e2euser',
            'email' => 'e2euser@example.com',
            'password' => 'E2eUser2026',
            'class' => User::CLASS_USER,
        ],
    ];

    public function run(): void
    {
        $repository = new UserRepository;

        foreach (self::USERS as $fixture) {
            $existing = User::query()
                ->where('username', $fixture['username'])
                ->first(['id', 'username', 'class']);

            if ($existing) {
                $this->command?->info(sprintf(
                    '  skip e2e user %s (id=%d, class=%s)',
                    $existing->username,
                    $existing->id,
                    $existing->class,
                ));

                continue;
            }

            $params = [
                'username' => $fixture['username'],
                'email' => $fixture['email'],
                'password' => $fixture['password'],
                'password_confirmation' => $fixture['password'],
                'class' => $fixture['class'],
            ];

            if (isset($fixture['id'])) {
                $params['id'] = $fixture['id'];
            }

            $user = $repository->store($params);

            $this->command?->info(sprintf(
                '  created e2e user %s (id=%d, class=%s)',
                $user->username,
                $user->id,
                $user->class,
            ));
        }
    }
}
