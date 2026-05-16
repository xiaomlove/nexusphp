<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Replacement for `public/opensearch.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * and `docs/migration-recipe.md`. Emits the static-ish OpenSearch
 * description XML that browsers' built-in search-engine UI
 * discovers via the `<link rel="search">` element in
 * `include/functions.php:2367` (in every `stdhead()` output).
 *
 * The legacy script:
 *   1. Required `include/bittorrent.php` and called `dbconn()`.
 *   2. Defined an inline `data_url($file,$mime)` helper that read
 *      `favicon.ico`, base64-encoded the bytes, and returned a
 *      `data:` URI. Used for one of the two `<Image>` tags below.
 *   3. Built the absolute base URL as
 *      `get_protocol_prefix() . $BASEURL`.
 *   4. Built an attribution string from `$datefounded`, `$SITENAME`
 *      and the current year.
 *   5. Sent `Content-type: text/xml` and a `$Cache->new_page(...)
 *      / get_page / add_whole_row / cache_page / next_row` dance
 *      that kept the rendered XML in the Nexus page-cache for
 *      86 400 s (one day).
 *
 * Replacement contract (this controller):
 *   - Same URL (`GET /opensearch.php`), reachable as a guest just
 *     like the legacy script.
 *   - Same `Content-Type: text/xml; charset=utf-8`.
 *   - Same XML body — `<OpenSearchDescription>` with the same set
 *     of child elements; the `template=` URLs, `<ShortName>`,
 *     `<Description>`, `<Contact>`, `<Image>` pair,
 *     `<moz:SearchForm>`, `<Attribution>` and `<Tags>` are all
 *     reproduced verbatim. HTML-special characters in
 *     `$SITENAME` / `$SLOGAN` / `$SITEEMAIL` are escaped (legacy
 *     escaped some fields but not others — we escape consistently).
 *   - One-day Laravel cache via `Cache::remember(...)` replaces
 *     the bespoke `$Cache->new_page(...)` flow. Different cache
 *     key per scheme+host so HTTPS upgrades and proxied previews
 *     don't pollute each other's payloads.
 */
class OpensearchController extends Controller
{
    private const CACHE_TTL_SECONDS = 86_400;

    public function __invoke(Request $request): Response
    {
        $scheme = $request->getScheme();
        $host = (string) (Setting::getByName('basic.BASEURL', $request->getHttpHost()));
        $host = rtrim($host, '/');
        $base = $scheme.'://'.$host;

        $body = Cache::remember(
            'legacy:opensearch:'.sha1($base),
            self::CACHE_TTL_SECONDS,
            fn (): string => $this->renderDescription($base),
        );

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'text/xml; charset=utf-8',
        ]);
    }

    private function renderDescription(string $base): string
    {
        // `Setting::getByName()` queries the `settings` row directly
        // (no static autoload cache), so per-test overrides via the
        // `updateOrInsert` seeder are visible. The matching
        // `AdRedirectController` makes the same choice for the same
        // reason.
        $siteName = (string) (Setting::getByName('basic.SITENAME') ?? 'NexusPHP');
        $slogan = (string) (Setting::getByName('main.SLOGAN') ?? '');
        $email = (string) (Setting::getByName('main.SITEEMAIL') ?? '');
        $founded = (string) (Setting::getByName('tweak.datefounded') ?? '2007-01-01');
        $project = defined('PROJECTNAME') ? (string) PROJECTNAME : 'NexusPHP';

        $year = (int) substr($founded, 0, 4);
        if ($year <= 0) {
            $year = 2007;
        }
        $now = (int) Carbon::now()->format('Y');
        $attribution = sprintf(
            'Copyright (c) %s %s, all rights reserved',
            $siteName,
            ($now !== $year ? $year.'-' : '').$now,
        );

        $favicon = $this->faviconDataUrl();
        $faviconUrl = $base.'/favicon.ico';

        // Pre-escape everything that lands inside a text-node so the
        // template body stays readable. Attributes (the `template=`
        // URLs) are URL-safe by construction.
        $siteNameEsc = $this->xml($siteName);
        $sloganEsc = $this->xml($slogan);
        $emailEsc = $this->xml($email);
        $attributionEsc = $this->xml($attribution);
        $projectEsc = $this->xml($project);

        $torrentsTemplate = $base.'/torrents.php?search={searchTerms}&amp;page={startPage?}';
        $rssTemplate = $base.'/torrentrss.php?search={searchTerms}&amp;rows={count?}&amp;startindex={startIndex?}';
        $selfTemplate = $base.'/opensearch.php';
        $suggestTemplate = $base.'/searchsuggest.php?q={searchTerms}';
        $searchForm = $base.'/torrents.php';

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/"
xmlns:moz="http://www.mozilla.org/2006/browser/search/">
<ShortName>{$siteNameEsc} Torrents</ShortName>
<Description>Search Torrents at {$siteNameEsc} - {$sloganEsc}.</Description>
<Url type="text/html"
rel="results"
pageOffset="0"
      template="{$torrentsTemplate}" />
<Url type="application/rss+xml"
rel="results"
indexOffset="0"
template="{$rssTemplate}" />
<Url type="application/opensearchdescription+xml"
rel="self"
template="{$selfTemplate}" />
<Url type="application/x-suggestions+json"
rel="suggestions"
template="{$suggestTemplate}" />
<Contact>{$emailEsc}</Contact>
<Tags>Torrents {$projectEsc}</Tags>
<LongName>{$siteNameEsc} Torrents Search</LongName>
<Image height="32" width="32" type="image/x-icon">{$favicon}</Image>
<Image height="32" width="32" type="image/x-icon">{$faviconUrl}</Image>
<moz:SearchForm>{$searchForm}</moz:SearchForm>
<Query role="example" searchTerms="batman" />
<Developer>{$siteNameEsc} Staff</Developer>
<Attribution>{$attributionEsc}</Attribution>
<SyndicationRight>limited</SyndicationRight>
<Language>*</Language>
<InputEncoding>UTF-8</InputEncoding>
<OutputEncoding>UTF-8</OutputEncoding>
</OpenSearchDescription>

XML;
    }

    /**
     * Return the `<Image>` `data:` URI for the site favicon. If the
     * file is missing from the public/ directory (test envs may
     * scrub it) we degrade to an empty `data:` URI so the rest of
     * the XML still validates.
     */
    private function faviconDataUrl(): string
    {
        $path = public_path('favicon.ico');
        if (! is_file($path) || ! is_readable($path)) {
            return 'data:image/x-icon;base64,';
        }
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            return 'data:image/x-icon;base64,';
        }

        return 'data:image/x-icon;base64,'.base64_encode($bytes);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
