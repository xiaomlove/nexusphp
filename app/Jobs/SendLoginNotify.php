<?php

namespace App\Jobs;

use App\Models\LoginLog;
use App\Models\NexusModel;
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

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $thisLoginLogId)
    {
        $this->thisLoginLogId = $thisLoginLogId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        /** @var NexusModel $thisLoginLog */
        $thisLoginLog = LoginLog::query()->findOrFail($this->thisLoginLogId);
        $log = "handling login log: " . $thisLoginLog->toJson();
        if (!$thisLoginLog->country || !$thisLoginLog->city) {
            do_log("$log, this login log no country or city");
            return;
        }
        $lastLoginLog = LoginLog::query()
            ->where('uid', $thisLoginLog->uid)
            ->where("id", "<", $thisLoginLog->id)
            ->orderBy('id', 'desc')
            ->first();
        if (!$lastLoginLog) {
            do_log("$log, no last login log");
            return;
        }
        $log .= sprintf(", last login: ", $lastLoginLog->toJson());
        if (!$lastLoginLog->country || !$lastLoginLog->city) {
            do_log("$log, last login log no country or city");
            return;
        }
        if ($thisLoginLog->country == $lastLoginLog->country && $thisLoginLog->city == $lastLoginLog->city) {
            do_log("$log, country and city are equals");
            return;
        }
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
        do_log(sprintf(
            '%s, user: %s login notify result: %s',
            $log, $user->username, var_export($result, true)
        ));

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
