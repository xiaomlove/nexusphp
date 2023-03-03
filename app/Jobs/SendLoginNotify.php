<?php

namespace App\Jobs;

use App\Models\LoginLog;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\ToolRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLoginNotify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $thisLoginLogId;

    private int $lastLoginLogId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $thisLoginLogId, int $lastLoginLogId)
    {
        $this->thisLoginLogId = $thisLoginLogId;

        $this->lastLoginLogId = $lastLoginLogId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $thisLoginLog = LoginLog::query()->findOrFail($this->thisLoginLogId);
        $lastLoginLog = LoginLog::query()->findOrFail($this->lastLoginLogId);
        $user = User::query()->findOrFail($thisLoginLog->uid, User::$commonFields);
        $locale = $user->locale;
        $toolRep = new ToolRepository();
        $subject = nexus_trans('message.login_notify.subject', ['site_name' => Setting::get('basic.SITENAME')], $locale);
        $body = nexus_trans('message.login_notify.body', [
            'this_login_time' => $thisLoginLog->created_at,
            'this_ip' => $thisLoginLog->ip,
            'this_location' => sprintf('%s·%s', $thisLoginLog->city, $thisLoginLog->country),
            'last_login_time' => $lastLoginLog->created_at,
            'last_ip' => $lastLoginLog->ip,
            'last_location' => sprintf('%s·%s', $lastLoginLog->city, $lastLoginLog->country),
        ], $locale);
        $result = $toolRep->sendMail($user->email, $subject, $body);
        do_log(sprintf('user: %s login notify result: %s', $user->username, var_export($result, true)));
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        do_log("failed: " . $exception->getMessage() . $exception->getTraceAsString(), 'error');
    }
}
