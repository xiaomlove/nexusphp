<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A request was confirmed (requests.finish = 'yes') and the chosen
 * torrents fulfilled it. Listeners push the *torrent owners* — they
 * earned the bonus and want to know their upload paid off.
 */
class RequestFulfilled
{
    use Dispatchable, SerializesModels;

    /**
     * @param  int  $requestId  requests.id
     * @param  int  $requestUserId  requests.userid (author who confirmed)
     * @param  list<int>  $ownerUserIds  Distinct list of torrent owner user IDs
     * @param  string  $requestName  requests.request — short title
     * @param  float  $bonusEach  Bonus paid to each owner (matches legacy split)
     */
    public function __construct(
        public int $requestId,
        public int $requestUserId,
        public array $ownerUserIds,
        public string $requestName = '',
        public float $bonusEach = 0.0,
    ) {}
}
