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
    protected $signature = 'user:reset_password {uid} {password} {password_confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset user password, arguments: uid password password_comfirmation';

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
        $uid = $this->argument('uid');
        $password = $this->argument('password');
        $passwordConfirmation = $this->argument('password_confirmation');
        $log = "uid: $uid, password: $password, passwordConfirmation: $passwordConfirmation";
        $this->info($log);
        do_log($log);

        $rep = new UserRepository();
        $result = $rep->resetPassword($uid, $password, $passwordConfirmation);
        $log = sprintf('[%s], %s, result: %s', nexus()->getRequestId(), __METHOD__, var_export($result, true));
        $this->info($log);
        do_log($log);
    }
}
