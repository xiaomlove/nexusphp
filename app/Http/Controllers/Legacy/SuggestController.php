<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/suggest.php` (deleted in the same PR).
 *
 * The XML-flavoured cousin of `searchsuggest.php`: same query, but
 * the response is plain text (legacy "OpenSearch suggestions"
 * endpoint, served as `text/xml` with `\r\n`-separated pairs) rather
 * than JSON. Some browsers' built-in OpenSearch integration uses this
 * shape via the site's `<link rel="search">`, so the URL stays
 * stable.
 */
class SuggestController extends Controller
{
    private const MAX_SUGGESTIONS = 5;

    private const MAX_SUGGESTION_LENGTH = 25;

    public function __invoke(Request $request): Response
    {
        // The original endpoint sends `Content-Type: text/xml` and a
        // family of cache-defeat headers; we mirror those so legacy
        // browser integrations behave identically.
        $headers = [
            'Content-Type' => 'text/xml; charset=utf-8',
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').'GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ];

        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response('', Response::HTTP_OK, $headers);
        }

        // The legacy script ran `unesc()` on `$_GET['q']` to strip a
        // round of `addslashes` left over from PHP4 magic_quotes; the
        // request lifecycle today no longer adds those slashes, so
        // `unesc()` is a no-op on modern requests but we keep calling
        // it for symmetry with the rest of the legacy stack.
        $searchstr = unesc($q);

        $rows = NexusDB::table('suggest')
            ->where('keywords', 'like', $searchstr.'%')
            ->groupBy('keywords')
            ->orderByDesc(NexusDB::raw('COUNT(*)'))
            ->orderByDesc('keywords')
            ->limit(10)
            ->selectRaw('keywords AS suggest, COUNT(*) AS count')
            ->get();

        $body = '';
        $emitted = 0;
        foreach ($rows as $row) {
            $row = (array) $row;
            if (strlen((string) $row['suggest']) > self::MAX_SUGGESTION_LENGTH) {
                continue;
            }
            $body .= ($body === '' ? '' : "\r\n").$row['suggest']."\r\n".$row['count'];
            $emitted++;
            if ($emitted >= self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return response($body, Response::HTTP_OK, $headers);
    }
}
