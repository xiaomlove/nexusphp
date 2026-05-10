<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/searchsuggest.php` (deleted in the same PR).
 *
 * Public AJAX endpoint that powers the search-box autocomplete; the
 * legacy script returned a JSON array of the form
 *   [searchString, [suggestion1, ...], [count1, ...]]
 * which the front-end depends on (see `public/js/jquery.suggest.js`
 * and inline scripts in `index.php`).
 *
 * Empty `q` (or missing) → empty body (legacy semantics: it just
 * `if (isset($_GET['q']) && $_GET['q'] != '')` and echoed nothing
 * otherwise). Matching that keeps callers that expect "no
 * suggestions" to render gracefully.
 */
class SearchSuggestController extends Controller
{
    private const MAX_SUGGESTIONS = 5;

    private const MAX_SUGGESTION_LENGTH = 25;

    public function __invoke(Request $request): JsonResponse|Response
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response('');
        }

        $rows = NexusDB::table('suggest')
            ->where('keywords', 'like', $q.'%')
            ->groupBy('keywords')
            ->orderByDesc(NexusDB::raw('COUNT(*)'))
            ->orderByDesc('keywords')
            ->limit(10)
            ->selectRaw('keywords AS suggest, COUNT(*) AS count')
            ->get();

        $suggestions = [];
        $counts = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            if (strlen((string) $row['suggest']) > self::MAX_SUGGESTION_LENGTH) {
                continue;
            }
            $suggestions[] = (string) $row['suggest'];
            $counts[] = $row['count'].' times';
            if (count($suggestions) >= self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return response()->json([htmlspecialchars($q), $suggestions, $counts]);
    }
}
