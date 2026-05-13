<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CheckQueueFailedJobs;
use App\Jobs\SaveIpLogCacheToDB;
use App\Jobs\UpdateIsSeedBoxFromUserRecordsCache;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use PHPUnit\Framework\TestCase;

/**
 * Pins down the queue-side overlap protection for the three scheduled
 * `ShouldQueue` jobs that previously could be processed concurrently by
 * Horizon workers on a hot-restart.
 *
 * The schedule-level `withoutOverlapping()` (in `app/Console/Kernel.php`)
 * stops the *dispatch* from re-firing; the `WithoutOverlapping` middleware
 * tested here stops the *execution* — both layers are necessary because
 * a job pushed onto the queue but not yet picked up will not block the
 * scheduler lock and so a Horizon hot-restart can pull two copies off
 * the same queue in quick succession.
 */
class SchedulerOverlapProtectionTest extends TestCase
{
    /**
     * @return iterable<string, array{0: object, 1: string}>
     */
    public static function jobsWithExpectedLockKey(): iterable
    {
        yield 'CheckQueueFailedJobs' => [
            new CheckQueueFailedJobs,
            'check-queue-failed-jobs',
        ];

        yield 'SaveIpLogCacheToDB' => [
            new SaveIpLogCacheToDB,
            'save-ip-log-cache-to-db',
        ];

        yield 'UpdateIsSeedBoxFromUserRecordsCache' => [
            new UpdateIsSeedBoxFromUserRecordsCache,
            'update-is-seedbox-from-user-records-cache',
        ];
    }

    /**
     * @dataProvider jobsWithExpectedLockKey
     */
    public function test_scheduled_job_exposes_without_overlapping_middleware(
        object $job,
        string $expectedLockKey,
    ): void {
        $this->assertTrue(
            method_exists($job, 'middleware'),
            sprintf('%s must expose a middleware() method for queue-side overlap protection.', $job::class),
        );

        /** @var array<int, object> $middleware */
        $middleware = $job->middleware();

        $this->assertNotEmpty(
            $middleware,
            sprintf('%s::middleware() must return at least one middleware.', $job::class),
        );

        $withoutOverlapping = null;
        foreach ($middleware as $m) {
            if ($m instanceof WithoutOverlapping) {
                $withoutOverlapping = $m;
                break;
            }
        }

        $this->assertNotNull(
            $withoutOverlapping,
            sprintf('%s::middleware() must contain a WithoutOverlapping middleware.', $job::class),
        );

        // The lock key is what Horizon uses to detect concurrent runs of
        // the same scheduled job — assert it's stable so a future rename
        // doesn't silently break the protection.
        $this->assertSame(
            $expectedLockKey,
            $withoutOverlapping->key,
            sprintf('%s middleware lock key drifted (expected %s).', $job::class, $expectedLockKey),
        );
    }
}
