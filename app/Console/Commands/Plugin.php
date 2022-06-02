<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Plugin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plugin {action} {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Plugin management, arguments: action plugin';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $plugin = new \Nexus\Plugin\Plugin();
        $action = $this->argument('action');
        $name = $this->argument('name');
        $mainClass = $plugin->getMainClass($name);
        if (!$mainClass) {
            $this->error("Can not find plugin: $name");
            return 1;
        }
        if ($action == 'install') {
            call_user_func([$mainClass, 'install']);
        } elseif ($action == 'uninstall') {
            call_user_func([$mainClass, 'uninstall']);
        } else {
            $this->error("Not support action: $action");
            return 1;
        }
        $log = sprintf("[%s], %s plugin: %s successfully !", nexus()->getRequestId(), $action, $name);
        $this->info($log);
        do_log($log);
        return 0;
    }
}
