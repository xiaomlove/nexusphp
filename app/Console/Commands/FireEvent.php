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
use Nexus\Database\NexusDB;
use Symfony\Component\Console\Command\Command as CommandAlias;

class FireEvent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:fire {--name=} {--idKey=} {--idKeyOld=""}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fire a event, options: --name, --idKey --idKeyOld';

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
        $idKey = $this->option('idKey');
        $idKeyOld = $this->option('idKeyOld');
        $log = "FireEvent, name: $name, idKey: $idKey, idKeyOld: $idKeyOld";
        if (isset($this->eventMaps[$name])) {
            $eventName = $this->eventMaps[$name]['event'];
            $model = unserialize(NexusDB::cache_get($idKey));
            if ($model instanceof Model) {
                $params = [$model];
                if ($idKeyOld) {
                    $modelOld = unserialize(NexusDB::cache_get($idKeyOld));
                    if ($modelOld instanceof Model) {
                        $params[] = $modelOld;
                    } else {
                        $log .= ", invalid idKeyOld";
                    }
                }
                $result = call_user_func_array([$eventName, "dispatch"], $params);
                $log .= ", success call dispatch, result: " . var_export($result, true);
            } else {
                $log .= ", invalid argument to call, it should be instance of: " . Model::class;
            }
        } else {
            $log .= ", no event match this name";
        }
        $this->info($log);
        do_log($log);
        return CommandAlias::SUCCESS;
    }
}
