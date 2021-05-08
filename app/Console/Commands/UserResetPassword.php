<?php

namespace App\Console\Commands;

use App\Repositories\UserRepository;
use Illuminate\Console\Command;

class UserResetPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:reset_password {username} {password} {password_confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset user password, arguments: username password password_comfirmation';

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
        $username = $this->argument('username');
        $password = $this->argument('password');
        $passwordConfirmation = $this->argument('password_confirmation');
        $log = "username: $username, password: $password, passwordConfirmation: $passwordConfirmation";
        $this->info($log);
        do_log($log);

        $rep = new UserRepository();
        $result = $rep->resetPassword($username, $password, $passwordConfirmation);
        $log = sprintf('[%s], %s, result: %s', REQUEST_ID, __METHOD__, var_export($result, true));
        $this->info($log);
        do_log($log);
    }
}
