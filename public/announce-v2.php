<?php

declare(strict_types=1);

require dirname(__DIR__) . '/include/bittorrent_announce_v2.php';

use App\Tracker\AnnounceV2\AnnounceFailure;
use App\Tracker\AnnounceV2\AnnounceRequest;
use App\Tracker\AnnounceV2\AnnounceService;

header('Content-Type: text/plain; charset=utf-8');
header('Pragma: no-cache');
header('Connection: close');

try {
    $request = AnnounceRequest::fromGlobals($_GET, $_SERVER);
    $service = new AnnounceService($Cache->getRedis());
    echo $service->handle($request);
} catch (AnnounceFailure $e) {
    echo AnnounceService::failure($e->getMessage(), $e->retryAfter);
} catch (InvalidArgumentException $e) {
    echo AnnounceService::failure($e->getMessage(), 1800);
} catch (Throwable $e) {
    do_log('[ANNOUNCE_V2] ' . $e->getMessage(), 'error');
    echo AnnounceService::failure('Temporary tracker failure', 60);
}

