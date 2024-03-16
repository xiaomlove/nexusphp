<?php

namespace App\Console\Commands;

use App\Events\TorrentCreated;
use App\Events\UserDestroyed;
use App\Events\UserDisabled;
use App\Events\UserEnabled;
use Illuminate\Console\Command;

class FireEvent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:fire {--name=} {--id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fire a event, options: --name, --id';

    protected array $eventMaps = [
        "torrent_created" => TorrentCreated::class,
        "user_destroyed" => UserDestroyed::class,
        "user_disabled" => UserDisabled::class,
        "user_enabled" => UserEnabled::class,
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->option('name');
        $id = $this->option('id');
        $log = "FireEvent, name: $name, id: $id";
        if (isset($this->eventMaps[$name])) {
            $result = call_user_func([$this->eventMaps[$name], "dispatch"], $id);
            $this->info("$log, success call dispatch, result: " . var_export($result, true));
        } else {
            $this->error("$log, no event match this name");
        }
        return Command::SUCCESS;
    }
}
