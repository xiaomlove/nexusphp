<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Install\Update;

class NexusUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nexus:update {--tag=} {--keep_tmp} {--include_composer}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update nexusphp after code updated, remember run `composer update` first. Options: --tag=, --keep_tmp, --include_composer';

    private $update;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->update = new Update();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        define('WITH_LARAVEL', true);
        require ROOT_PATH . 'nexus/Database/helpers.php';
        $tag = $this->option('tag');
        $keepTmp = $this->option('keep_tmp');
        $includeComposer = $this->option('include_composer');
        $includes = [];
        if ($includeComposer) {
            $includes[] = 'composer';
        }

        //Step 1
        $step = $this->update->currentStep();
        $log = sprintf('Step %s, %s...', $step, $this->update->getStepName($step));
        $this->doLog($log);
        $requirements = $this->update->listRequirementTableRows();
        $fails = $requirements['fails'];
        if (!empty($fails)) {
            foreach ($fails as $value) {
                $this->doLog("Error: " . nexus_json_encode($value), 'error');
            }
            return 0;
        }
        $this->update->gotoStep(++$step);

        //Download
        if ($tag !== null) {
            if ($tag === 'dev') {
                $url = "https://github.com/xiaomlove/nexusphp/archive/refs/heads/php8.zip";
            } else {
                $url = "https://api.github.com/repos/xiaomlove/nexusphp/tarball/v$tag";
            }
            $this->doLog("Specific tag: '$tag', download from '$url' and extra code, includes: " . implode(', ', $includes));
            $tmpPath = $this->update->downAndExtractCode($url, $includes);
        }
        if ($includeComposer) {
            $requireCommand = 'composer';
            if (!command_exists($requireCommand)) {
                $this->doLog("Error: require $requireCommand");
                return 0;
            }
            $command = "composer install";
            $log = "Running $command ...";
            $this->doLog($log);
            $this->update->executeCommand($command);
        }

        //Step 2
        $log = sprintf('Step %s, %s, cli skip...', $step, $this->update->getStepName($step));
        $this->doLog($log);
        $this->update->gotoStep(++$step);

        //Step 3
        $log = sprintf('Step %s, %s, cli skip...', $step, $this->update->getStepName($step));
        $this->doLog($log);
        $this->update->gotoStep(++$step);

        //Step 4
        $log = sprintf('Step %s, %s...', $step, $this->update->getStepName($step));
        $this->doLog($log);
        $settingTableRows = $this->update->listSettingTableRows();
        $settings = $settingTableRows['settings'];
        $symbolicLinks = $settingTableRows['symbolic_links'];
        $fails = $settingTableRows['fails'];
        $mysqlInfo = $this->update->getMysqlVersionInfo();
        $redisInfo = $this->update->getRedisVersionInfo();

        if (!empty($fails)) {
            foreach ($fails as $value) {
                $this->doLog("Error: " . nexus_json_encode($value), 'error');
            }
            return 0;
        }
        if (!$mysqlInfo['match']) {
            $this->doLog("Error: MySQL version: {$mysqlInfo['version']} is too low, please use the newest version of 5.7 or above.", 'error');
            return 0;
        }
        if (!$redisInfo['match']) {
            $this->doLog("Error: Redis version: {$mysqlInfo['version']} is too low, please use 2.0.0 or above.", 'error');
            return 0;
        }
        $this->doLog("going to createSymbolicLinks...");
        $this->update->createSymbolicLinks($symbolicLinks);
        $this->doLog("createSymbolicLinks done!");

        $this->doLog("going to saveSettings...");
        $this->update->saveSettings($settings);
        $this->doLog("saveSettings done!");

        $this->doLog("going to runExtraQueries...");
        $this->update->runExtraQueries();
        $this->doLog("runExtraQueries done!");

        $this->doLog("going to runMigrate...");
        $this->update->runMigrate();
        $this->doLog("runMigrate done!");

        $this->doLog("going to runExtraMigrate...");
        $this->update->runExtraMigrate();
        $this->doLog("runExtraMigrate done!");

        $this->doLog("All done!");

        if (isset($tmpPath)) {
            if (!$keepTmp) {
                $this->doLog("Delete tmp files in: $tmpPath");
                $this->update->executeCommand("rm -rf " . rtrim($tmpPath, '/'));
            } else {
                $this->doLog("Keep tmp files in: $tmpPath");
            }
        }

        return 0;
    }

    private function doLog($log, string $level = 'info')
    {
        $this->update->doLog($log);
        $this->{$level}(sprintf(
            '[%s] [%s] %s',
            date('Y-m-d H:i:s'), $this->update->currentStep(), $log
        ));
    }
}
