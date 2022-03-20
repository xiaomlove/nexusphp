<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class DeleteExpiredToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:delete_expired_token {--uid=} {--days=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete user expired token, options: --uid, --days';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $uid = $this->option('uid');
        $days = $this->option('days');
        if (!is_numeric($days)) {
            $days = 60;
        }
        $query = PersonalAccessToken::query()->where('tokenable_type', User::class);
        if ($uid) {
            $query->where('tokenable_id', $uid);
        }
        $log = sprintf('uid: %s, days: %s', $uid, $days);
        $this->info($log);
        do_log($log);

        $query->where('last_used_at', '<', Carbon::now()->subDays($days));
        $result = $query->delete();
        $log = sprintf('[%s], %s, result: %s, query: %s', nexus()->getRequestId(), __METHOD__, var_export($result, true), last_query());
        $this->info($log);
        do_log($log);
        return 0;
    }
}
