<?php

namespace App\Providers;

use App\Events\SeedBoxRecordUpdated;
use App\Events\TorrentCreated;
use App\Events\TorrentUpdated;
use App\Events\UserDestroyed;
use App\Events\UserDisabled;
use App\Listeners\FetchTorrentImdb;
use App\Listeners\RemoveOauthTokens;
use App\Listeners\RemoveSeedBoxRecordCache;
use App\Listeners\SyncTorrentToEs;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        TorrentUpdated::class => [
            SyncTorrentToEs::class,
        ],
        SeedBoxRecordUpdated::class => [
            RemoveSeedBoxRecordCache::class,
        ],
        TorrentCreated::class => [
            FetchTorrentImdb::class,
        ],
        UserDisabled::class => [
            RemoveOauthTokens::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
