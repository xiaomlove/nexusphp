<?php

namespace App\Console\Commands\Install;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InitData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install:init_data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize data.';

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
        DB::transaction(function () {
            $this->call('db:seed');
        });
        $this->info("[INIT DATA] success.");
        return 0;
    }
}
