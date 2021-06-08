<?php

namespace App\Console\Commands\Install;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Migrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate tables.';

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
            $this->call('migrate');
        });
        $this->info("[MIGRATE TABLE] success.");
        return 0;
    }
}
