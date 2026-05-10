<?php

namespace Database\Seeders;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
     * `uploaded` and `downloaded` (in bytes) are written after the user
     * is created so behavioural specs that look at the rendered ratio
     * — `tooltips.spec.ts` checks for the `<span class="ratio-tip">`
     * wrapper added in PR #60 — see a non-`---` value. Default ratios
     * are chosen to land in different colour buckets:
     *   admin: 10 GB / 5 GB  -> 2.0   (green)
     *   staff: 10 GB / 8 GB  -> 1.25  (yellow/green)
     *   user:  10 GB / 20 GB -> 0.5   (red)
     *
     * @var list<array{username: string, email: string, password: string, class: string, id?: int, uploaded?: int, downloaded?: int, passkey?: string}>
     */
    public const USERS = [
        [
            'username' => 'e2eadmin',
            'email' => 'e2eadmin@example.com',
            'password' => 'E2eAdmin2026',
            'class' => User::CLASS_STAFF_LEADER,
            'id' => 1,
            'uploaded' => 10737418240,
            'downloaded' => 5368709120,
            // 32-char hex; consumed by tests/e2e/announce/announce.spec.ts.
            'passkey' => 'e2eadmine2eadmine2eadmine2eadm00',
        ],
        [
            'username' => 'e2estaff',
            'email' => 'e2estaff@example.com',
            'password' => 'E2eStaff2026',
            'class' => User::CLASS_MODERATOR,
            'uploaded' => 10737418240,
            'downloaded' => 8589934592,
            'passkey' => 'e2estaffe2estaffe2estaffe2est000',
        ],
        [
            'username' => 'e2euser',
            'email' => 'e2euser@example.com',
            'password' => 'E2eUser2026',
            'class' => User::CLASS_USER,
            'uploaded' => 10737418240,
            'downloaded' => 21474836480,
            'passkey' => 'e2eusere2eusere2eusere2euser0000',
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

                $this->applyStats($existing->id, $fixture);

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

            $this->applyStats((int) $user->id, $fixture);
        }
    }

    /**
     * Write `uploaded`/`downloaded` and (optionally) a deterministic
     * `passkey` directly to the row. We bypass `UserRepository::store()`
     * because that helper does not accept stat fields and seeded
     * passkeys are an E2E concern only.
     *
     * @param  array{username: string, uploaded?: int, downloaded?: int, passkey?: string}  $fixture
     */
    private function applyStats(int $userId, array $fixture): void
    {
        $update = [];

        if (isset($fixture['uploaded'])) {
            $update['uploaded'] = $fixture['uploaded'];
        }

        if (isset($fixture['downloaded'])) {
            $update['downloaded'] = $fixture['downloaded'];
        }

        if (isset($fixture['passkey'])) {
            $update['passkey'] = $fixture['passkey'];
        }

        if ($update === []) {
            return;
        }

        DB::table('users')->where('id', $userId)->update($update);

        $this->command?->info(sprintf(
            '    set %s stats: uploaded=%d, downloaded=%d, passkey=%s',
            $fixture['username'],
            $update['uploaded'] ?? 0,
            $update['downloaded'] ?? 0,
            isset($update['passkey']) ? '(set)' : '(unchanged)',
        ));
    }
}
