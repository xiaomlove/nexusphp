<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Console\Command;

class UserGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:generate {--num=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate some user. options: --num=?';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $num = $this->option("num");
        $log = "num: $num";
        if (!$num) {
            $this->error("$log, no num!");
            return Command::SUCCESS;
        }
        $size = 1000;
        $total = 0;
        do {
            if ($num < $size) {
                $size = $num;
            }
            if ($total + $size > $num) {
                $size = $num - $total;
            }
            User::factory($size)->create();
            $total += $size;
            $this->info("$log, success create $size !");
        } while($total < $num);
        $this->info("$log, total: $total, ALL DONE!");
        return Command::SUCCESS;
    }
}
