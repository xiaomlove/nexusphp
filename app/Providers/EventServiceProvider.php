<?php

namespace App\Providers;

use App\Events\ForumPostAdded;
use App\Events\HitAndRunCreated;
use App\Events\HitAndRunUpdated;
use App\Events\MessageCreated;
use App\Events\RequestFulfilled;
use App\Events\RequestSupplied;
use App\Events\SeedBoxRecordUpdated;
use App\Events\TorrentCreated;
use App\Events\TorrentDeleted;
use App\Events\TorrentPromotionChanged;
use App\Events\TorrentUpdated;
use App\Events\UserDisabled;
use App\Listeners\ClearTorrentCache;
use App\Listeners\DeductUserBonusWhenTorrentDeleted;
use App\Listeners\FetchTorrentImdb;
use App\Listeners\FetchTorrentPTGen;
use App\Listeners\RemoveOauthTokens;
use App\Listeners\RemoveSeedBoxRecordCache;
use App\Listeners\SendEmailNotificationWhenTorrentCreated;
use App\Listeners\SendPushOnForumPost;
use App\Listeners\SendPushOnFreeEvent;
use App\Listeners\SendPushOnHitAndRun;
use App\Listeners\SendPushOnMention;
use App\Listeners\SendPushOnMessage;
use App\Listeners\SendPushOnRequest;
use App\Listeners\SyncTorrentToElasticsearch;
use App\Listeners\SyncTorrentToMeilisearch;
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
        SeedBoxRecordUpdated::class => [
            RemoveSeedBoxRecordCache::class,
        ],
        TorrentUpdated::class => [
            FetchTorrentImdb::class,
            FetchTorrentPTGen::class,
            SyncTorrentToElasticsearch::class,
            SyncTorrentToMeilisearch::class,
        ],
        TorrentCreated::class => [
            FetchTorrentImdb::class,
            FetchTorrentPTGen::class,
            SyncTorrentToElasticsearch::class,
            SyncTorrentToMeilisearch::class,
            SendEmailNotificationWhenTorrentCreated::class,
            ClearTorrentCache::class,
        ],
        TorrentDeleted::class => [
            DeductUserBonusWhenTorrentDeleted::class,
        ],
        UserDisabled::class => [
            RemoveOauthTokens::class,
        ],
        MessageCreated::class => [
            SendPushOnMessage::class,
            [SendPushOnMention::class, 'handleMessage'],
        ],
        ForumPostAdded::class => [
            SendPushOnForumPost::class,
            [SendPushOnMention::class, 'handleForumPost'],
        ],
        TorrentPromotionChanged::class => [
            SendPushOnFreeEvent::class,
        ],
        HitAndRunCreated::class => [
            [SendPushOnHitAndRun::class, 'handleCreated'],
        ],
        HitAndRunUpdated::class => [
            [SendPushOnHitAndRun::class, 'handleUpdated'],
        ],
        RequestSupplied::class => [
            [SendPushOnRequest::class, 'handleSupplied'],
        ],
        RequestFulfilled::class => [
            [SendPushOnRequest::class, 'handleFulfilled'],
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
