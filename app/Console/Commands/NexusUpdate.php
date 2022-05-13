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
    protected $signature = 'nexus:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update nexusphp after code updated, remember run `composer update` first.';

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
