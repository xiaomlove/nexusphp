<?php

namespace App\Console\Commands;

use App\Events\NewsCreated;
use App\Events\TorrentCreated;
use App\Events\TorrentDeleted;
use App\Events\TorrentUpdated;
use App\Events\UserDestroyed;
use App\Events\UserDisabled;
use App\Events\UserEnabled;
use App\Models\News;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

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
        "torrent_created" => ['event' => TorrentCreated::class, 'model' => Torrent::class],
        "torrent_updated" => ['event' => TorrentUpdated::class, 'model' => Torrent::class],
        "torrent_deleted" => ['event' => TorrentDeleted::class, 'model' => Torrent::class],
        "user_destroyed" => ['event' => UserDestroyed::class, 'model' => User::class],
        "user_disabled" => ['event' => UserDisabled::class, 'model' => User::class],
        "user_enabled" => ['event' => UserEnabled::class, 'model' => User::class],
        "news_created" => ['event' => NewsCreated::class, 'model' => News::class],
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
            $eventName = $this->eventMaps[$name]['event'];
            $modelName = $this->eventMaps[$name]['model'];
            /** @var Model $model */
            $model = new $modelName();
            $result = call_user_func([$eventName, "dispatch"], $model::query()->find($id));
            $this->info("$log, success call dispatch, result: " . var_export($result, true));
        } else {
            $this->error("$log, no event match this name");
        }
        return Command::SUCCESS;
    }
}
