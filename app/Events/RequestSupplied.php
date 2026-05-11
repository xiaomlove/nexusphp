<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A user supplied a torrent (resreq INSERT) for an open request.
 * Listeners notify the request author so they can confirm or reject
 * the supplied torrent without polling the request page.
 */
class RequestSupplied
{
    use Dispatchable, SerializesModels;

    /**
     * @param  int  $requestId  requests.id
     * @param  int  $requestUserId  requests.userid (author of the request)
     * @param  int  $torrentId  torrent that was supplied
     * @param  int  $supplierId  user that did the supply
     * @param  string  $requestName  requests.request — short title, used in the push body
     */
    public function __construct(
        public int $requestId,
        public int $requestUserId,
        public int $torrentId,
        public int $supplierId,
        public string $requestName = '',
    ) {}
}
