<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Replacement for `public/special.php` (deleted in the same PR).
 *
 * The legacy file was a one-line `require "torrents.php"` shim — it
 * exists so the `/special.php` URL renders the same listing as
 * `/torrents.php?special=1`. Replacing it with a redirect keeps the
 * URL alive without paying the cost of routing through a `require`
 * (and lets Laravel observe the request normally).
 */
class SpecialController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $query = $request->query();
        $query['special'] = $query['special'] ?? 1;

        return redirect()->away('/torrents.php?'.http_build_query($query));
    }
}
