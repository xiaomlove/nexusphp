<?php

namespace App\Listeners;

use App\Models\Setting;
use App\Repositories\SearchRepository;
use App\Repositories\ToolRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SyncTorrentToEs implements ShouldQueue
{

    public $tries = 3;

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
        $id = $event->torrentId;
        $searchRep = new SearchRepository();
        $result = $searchRep->updateTorrent($id);
        do_log("result: " . var_export($result, true));

    }

    /**
     * handle failed
     *
     * @param  object  $event
     * @return void
     */
    public function failed($event, \Throwable $exception)
    {
        $toolRep = new ToolRepository();
        $to = Setting::get('main.SITEEMAIL');
        $subject = sprintf('Event: %s listener: %s handle error', get_class($event), __CLASS__);
        $body = sprintf("%s\n%s", $exception->getMessage(), $exception->getTraceAsString());
        try {
            $result = $toolRep->sendMail($to, $subject, $body);
            if ($result === false) {
                do_log("$subject send mail fail", 'alert');
            }
        } catch (\Throwable $exception) {
            do_log("$subject send mail fail: " . $exception->getMessage() . $exception->getTraceAsString(), 'alert');
        }
    }
}
