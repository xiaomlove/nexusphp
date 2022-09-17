<?php

namespace App\Console\Commands;

use App\Repositories\PluginRepository;
use Illuminate\Console\Command;
use Nexus\Plugin\BasePlugin;

class PluginCronjob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plugin:cronjob {--action=} {--id=} {--force=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Plugin install / update / delete cronjob handler';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $action = $this->option('action');
        $id = $this->option('id');
        $force = $this->option('force');
        $pluginRep = new PluginRepository();
        $pluginRep->cronjob($action, $id, $force);
        $log = sprintf("[%s], action: %s, id: %s, force: %s run done !", nexus()->getRequestId(), $action, $id, $force);
        $this->info($log);
        do_log($log);
        return 0;
    }
}
