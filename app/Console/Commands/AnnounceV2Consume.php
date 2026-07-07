<?php

namespace App\Console\Commands;

use App\Tracker\AnnounceV2\AccountingConsumer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;

class AnnounceV2Consume extends Command
{
    protected $signature = 'tracker:announce-v2-consume {--once} {--count=500} {--block=1000} {--claim-idle=60000} {--consumer=}';

    protected $description = 'Consume Redis announce-v2 accounting events and persist coalesced state to MySQL';

    public function handle(): int
    {
        $consumerName = $this->option('consumer') ?: gethostname() . '-' . getmypid() . '-' . Str::random(6);
        $consumer = new AccountingConsumer(NexusDB::redis());
        $consumer->ensureGroups();
        $recovered = $consumer->recoverPending(
            $consumerName, max(1000, (int) $this->option('claim-idle')), max(1, (int) $this->option('count'))
        );
        if ($recovered > 0) {
            $this->line("Recovered $recovered pending announce events");
        }

        do {
            $count = $consumer->consume($consumerName, max(1, (int) $this->option('count')), max(1, (int) $this->option('block')));
            if ($count > 0) {
                $this->line("Persisted $count announce events");
            }
        } while (!$this->option('once'));

        return self::SUCCESS;
    }
}
