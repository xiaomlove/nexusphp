<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Passport\Passport;

class RemoveOauthTokens implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $uid = $event->model?->id ?? 0;
        $modelNames = [
            Passport::$authCodeModel,
            Passport::$tokenModel,
        ];
        foreach ($modelNames as $name) {
            /**
             * @var $model Model
             */
            $model = new $name();
            $model::query()->where("user_id", $uid)->forceDelete();
        }
        do_log(sprintf("success remove user: %d oauth tokens related.", $uid));
    }
}
