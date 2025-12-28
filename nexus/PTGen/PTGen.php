<?php

/**
 * get media info base on PT-Gen
 *
 * @since 1.6
 * @see  https://github.com/Rhilip/pt-gen-cfworker
 */
namespace Nexus\PTGen;

use App\Models\Torrent;
use App\Models\TorrentExtra;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;
use Nexus\Imdb\Imdb;

class PTGen
{
    private $apiPoint;

    const SITE_DOUBAN = 'douban';
    const SITE_IMDB = 'imdb';
    const SITE_BANGUMI = 'bangumi';
    const SITE_STEAM = 'steam';
    const SITE_INDIENOVA = 'indienova';
    const SITE_EPIC = 'epic';

    public static array $validSites = [
        self::SITE_IMDB => [
            'url_pattern' => '/(?:https?:\/\/)?(?:www\.)?imdb\.com\/title\/(tt\d+)\/?/',
            'home_page' => 'https://www.imdb.com/',
            'rating_average_img' => 'pic/imdb2.png',
            'rating_pattern_in_desc' => "/IMDb评分.*([\d\.]+)\//iU",
        ],
        self::SITE_DOUBAN => [
            'url_pattern' => '/(?:https?:\/\/)?(?:(?:movie|www)\.)?douban\.com\/(?:subject|movie)\/(\d+)\/?/',
            'home_page' => 'https://www.douban.com/',
            'rating_average_img' => 'pic/douban2.png',
            'rating_pattern_in_desc' => "/豆瓣评分.*([\d\.]+)\//iU",
        ],
        self::SITE_BANGUMI => [
            'url_pattern' => '/(?:https?:\/\/)?(?:bgm\.tv|bangumi\.tv|chii\.in)\/subject\/(\d+)\/?/',
            'home_page' => 'https://bangumi.tv/',
            'rating_average_img' => 'pic/bangumi.jpg',
        ],
        //Banned !
//        self::SITE_STEAM => [
//            'url_pattern' => '/(?:https?:\/\/)?(?:store\.)?steam(?:powered|community)\.com\/app\/(\d+)\/?/',
//            'home_page' => 'https://store.steampowered.com/',
//            'rating_average_img' => 'pic/steam.svg',
//        ],
        self::SITE_INDIENOVA => [
            'url_pattern' => '/(?:https?:\/\/)?indienova\.com\/game\/(\S+)/',
            'home_page' => 'https://indienova.com/',
            'rating_average_img' => 'pic/invienova.jpg',
        ],
        //seems url_pattern has changed
//        self::SITE_EPIC => [
//            'url_pattern' => '/(?:https?:\/\/)?www\.epicgames\.com\/store\/[a-zA-Z-]+\/product\/(\S+)\/\S?/',
//            'home_page' => 'https://store.epicgames.com/',
//            'rating_average_img' => 'pic/epic_game.png',
//        ],
    ];


    public function __construct()
    {
        $setting = get_setting('main');
        $this->setApiPoint($setting['pt_gen_api_point'] ?? '');
    }

    public function getApiPoint(): string
    {
        return $this->apiPoint;
    }

    public function setApiPoint(string $apiPoint)
    {
        $this->apiPoint = trim($apiPoint);
    }

    public function generate(string $url, bool $withoutCache = false): array
    {
        $parsed = $this->parse($url);
        $targetUrl = trim($this->apiPoint, '/');
        if (Str::contains($targetUrl, '?')) {
            $targetUrl .= "&";
        } else {
            $targetUrl .= "?";
        }
        $targetUrl .= sprintf('site=%s&sid=%s&url=%s&key=1', $parsed['site'] , $parsed['id'], urlencode($parsed['url']));
        return $this->request($targetUrl, $withoutCache);
    }

    public function parse(string $url): array
    {
        foreach (self::$validSites as $site => $info) {
            if (preg_match($info['url_pattern'], $url, $matches)) {
                return [
                    'site' => $site,
                    'url' => $matches[0],
                    'id' => $matches[1]
                ];
            }
        }
        throw new PTGenException("invalid url: $url");
    }

    private function buildDetailsPageTableRow($torrentId, $ptGenArr, $site): string
    {
        global $lang_details;
        if ($this->isRawPTGen($ptGenArr)) {
            $ptGenFormatted = $ptGenArr['format'];
        } elseif ($this->isIyuu($ptGenArr)) {
            $ptGenFormatted = $ptGenArr['data']['format'];
        } else {
            do_log("Invalid pt gen data", 'error');
            return '';
        }
        $poster = '';
        if (!empty($ptGenArr['poster'])) {
            $poster = $ptGenArr['poster'];
        } elseif (preg_match('/\[img\](.*)\[\/img\]/iU', $ptGenFormatted, $matches)) {
            $poster = $matches[1];
        }
        if ($poster) {
            $prefix = sprintf("[img]%s[/img]\n", $poster);
            $ptGenFormatted = mb_substr($ptGenFormatted, mb_strlen($prefix, 'utf-8') + 1);
        }
        $ptGenFormatted = format_comment($ptGenFormatted);
        $ptGenFormatted .= sprintf(
            '%s %s%s<a href="retriver.php?id=%s&type=1&siteid=%s">%s</a>',
            $lang_details['text_information_updated_at'], $ptGenArr['__updated_at'], $lang_details['text_might_be_outdated'],
            $torrentId, $site, $lang_details['text_here_to_update']
        );
        $titleShowOrHide = $lang_details['title_show_or_hide'] ?? '';
        $id = 'pt-gen-' . $site;
        $posterHtml = "";
        if ($poster) {
            $posterHtml = sprintf('<div id="poster%s"><img src="%s" width="105" onclick="Preview(this);" title="%s"', $id, $poster, $titleShowOrHide);
        }
        $html = <<<HTML
<tr>
    <td class="rowhead">
        <a href="javascript: klappe_ext('{$id}')">
            <span class="nowrap">
                <img id="pic{$id}" class="minus" src="pic/trans.gif" alt="Show/Hide" title="{$titleShowOrHide}" />
                PT-Gen-{$site}
            </span>
        </a>
        $posterHtml
    </td>
    <td class="rowfollow" align="left">
        <div id="k{$id}">
            {$ptGenFormatted}
        </div>
    </td>
</tr>
HTML;
       return $html;
    }

    private function request(string $url, bool $withoutCache = false): array
    {
        $begin = microtime(true);
        $logPrefix = "url: $url";
        $cacheKey = $this->getApiPointResultCacheKey($url);
        if (!$withoutCache) {
            $cache = NexusDB::cache_get($cacheKey);
            if ($cache) {
                do_log("$logPrefix, from cache");
                return $cache;
            }
        }
        do_log("$logPrefix, going to send request...");
        $http = new Client();
        $response = $http->get($url, ['timeout' => 10, 'headers' => ['x-internal-request' => 'true',],]);
        $statusCode = $response->getStatusCode();
        if ($statusCode != 200) {
            $msg = "api point response http status code: $statusCode";
            do_log("$logPrefix, $msg");
            throw new PTGenException($msg);
        }
        $bodyString = (string)$response->getBody();
        if (empty($bodyString)) {
            $msg = "response body empty";
            do_log("$logPrefix, $msg");
            throw new PTGenException($msg);
        }
        $bodyArr = json_decode($bodyString, true);
        if (empty($bodyArr) || !is_array($bodyArr)) {
            $msg = "response body error: $bodyString";
            do_log("$logPrefix, $msg");
            throw new PTGenException($msg);
        }
        if ($this->isRawPTGen($bodyArr) || $this->isIyuu($bodyArr)) {
            NexusDB::cache_put($cacheKey, $bodyArr, 24 * 3600);
            do_log("$logPrefix, success get from api point, use time: " . (microtime(true) - $begin));
            $bodyArr['__updated_at'] = now()->toDateTimeString();
            return $bodyArr;
        } else {
            $msg = "error: " . $bodyArr['error'] ?? '';
            do_log("$logPrefix, response: $bodyString");
            throw new PTGenException($msg);
        }
    }

    public function deleteApiPointResultCache($url)
    {
        NexusDB::cache_del($this->getApiPointResultCacheKey($url));
    }

    private function getApiPointResultCacheKey($url)
    {
        return __METHOD__ . "_$url";
    }

    public function renderUploadPageFormInput($ptGen = ''): string
    {
        $arr = json_decode($ptGen, true);
        $link = is_array($arr) ? $arr['__link'] : $ptGen;
        $y = $this->buildInput("pt_gen", $link, nexus_trans('ptgen.tooltip', ['sites' => $this->buildTooltip()]), nexus_trans('ptgen.btn_get_desc'));
        return tr(nexus_trans('ptgen.label'), $y, 1, '', true);
    }

    private function buildTooltip(): string
    {
        $results = [];
        foreach (self::$validSites as $site => $info) {
            $results[] = sprintf('<a href="%s" target="_blank" /><strong>%s</strong></a>', $info['home_page'], $site);
        }
        return implode(' / ', $results);
    }

    public function buildInput($name, $value, $note, $btnText): string
    {
        $btn = '';
        if ($this->apiPoint != '') {
            $btn = '<div><input type="button" class="btn-get-pt-gen" value="'.$btnText.'"></div>';
        }
        $input = <<<HTML
<div style="display: flex">
    <div style="display: flex;flex-direction: column;flex-grow: 1">
        <input type="text" name="$name" value="{$value}" data-pt-gen="$name">
        <span class="medium">{$note}</span>
    </div>
    $btn
</div>
HTML;
        return $input;
    }

    public function renderDetailsPageDescription($torrentId, $torrentPtGenArr): array
    {
        $html = '';
        $jsonArr = [];
        $update = false;
        $torrentPtGenArr = (array)$torrentPtGenArr;
        foreach (self::$validSites as $site => $info) {
            if (empty($torrentPtGenArr[$site]['link'])) {
                continue;
            }
            $link = $torrentPtGenArr[$site]['link'];
            $data = $torrentPtGenArr[$site]['data'] ?? [];
            if (!empty($data)) {
                $jsonArr[$site] = [
                    'link' => $link,
                    'data' => $data,
                ];
                $html .= $this->buildDetailsPageTableRow($torrentId, $data, $site);
            } else {
                try {
                    $ptGenArr = $this->generate($torrentPtGenArr[$site]['link']);
                } catch (\Exception $e) {
                    $log = $e->getMessage() . ", trace: " . $e->getTraceAsString();
                    do_log($log,'error');
                    $ptGenArr = [
                        'format' => $e->getMessage()
                    ];
                }

                $jsonArr[$site] = [
                    'link' => $link,
                    'data' => $ptGenArr,
                ];
                $html .= $this->buildDetailsPageTableRow($torrentId, $ptGenArr, $site);
                if (!$update) {
                    $update = true;
                }
            }
        }
        return ['json_arr' => $jsonArr, 'html' => $html, 'update' => $update];
    }

    public function buildRatingSpan(array $siteIdAndRating): string
    {
        $result = '<td class="embedded" style="text-align: right; width: 40px;padding: 4px"><div style="display: flex;flex-direction: column">';
        $count = 1;
        $ratingIcons = [];
        foreach (self::$validSites as $site => $info) {
            if (!isset($siteIdAndRating[$site])) {
                continue;
            }
            [$ratingValue, $externalId] = $this->normalizeRatingInfo($site, $siteIdAndRating[$site]);
            if ($ratingValue === '' || $ratingValue === null) {
                continue;
            }
            if ($count > 2) {
                //only show the first two
                break;
            }
            $ratingIcons[] = $this->getRatingIcon($site, $ratingValue, $externalId);
            $count++;
        }
        if (empty($ratingIcons)) {
            $ratingIcons[] = $this->getRatingIcon(self::SITE_IMDB, 'N/A');
            $ratingIcons[] = $this->getRatingIcon(self::SITE_DOUBAN, 'N/A');
        }
        $result .= implode("", $ratingIcons)  . '</div></td>';
        return $result;
    }

    public function getRatingIcon($siteId, $rating, $externalId = null): string
    {
        if (is_numeric($rating)) {
            $rating = number_format($rating, 1);
        }
        $spanAttr = '';
        if (!empty($externalId)) {
            if ($siteId === self::SITE_IMDB) {
                $spanAttr = sprintf(' data-imdbid="%s"', htmlspecialchars($externalId, ENT_QUOTES));
            } elseif ($siteId === self::SITE_DOUBAN) {
                $spanAttr = sprintf(' data-doubanid="%s"', htmlspecialchars($externalId, ENT_QUOTES));
            }
        }
        $result = sprintf(
            '<div style="display: flex;align-content: center;justify-content: space-between;padding: 2px 0"><img src="%s" alt="%s" title="%s" style="max-width: 16px;max-height: 16px"/><span%s>%s</span></div>',
            self::$validSites[$siteId]['rating_average_img'], $siteId, $siteId, $spanAttr, $rating
        );
        return $result;
    }

    public function isRawPTGen(array $bodyArr): bool
    {
        return isset($bodyArr['success']) && $bodyArr['success'];
    }

    public function isIyuu(array $bodyArr): bool
    {
        $version = (string)($bodyArr['version'] ?? '');
        switch ($version) {
            case '2.0.0':
                return isset($bodyArr['ret'])
                    && intval($bodyArr['ret']) === 200
                    && isset($bodyArr['data']['format'])
                    && is_string($bodyArr['data']['format'])
                    && $bodyArr['data']['format'] !== '';
            default:
                return false;
        }
    }

    public function listRatings(array $ptGenData, string $imdbLink, string $desc = ''): array
    {
        $results = [];
        $log = "";
        $sharedFallbackLinks = [];
        if (!empty($ptGenData['__link']) && is_string($ptGenData['__link'])) {
            $sharedFallbackLinks[] = $ptGenData['__link'];
        }
        //First, get from PTGen
        foreach (self::$validSites as $site => $info) {
            if (!isset($ptGenData[$site])) {
                continue;
            }
            $siteEntry = $ptGenData[$site];
            $data = is_array($siteEntry) && isset($siteEntry['data']) ? $siteEntry['data'] : [];
            $log .= ", handling site: $site";
            $rating = '';
            if (isset($data['__rating'])) {
                //__rating is new add
                $rating = $data['__rating'];
                $log .= ", from __rating";
            } else {
                // from original structure fetch
                if ($this->isRawPTGen($data)) {
                    $log .= ", isRawPTGen";
                    $rating = $this->getRawPTGenRating($data, $site);
                } elseif ($this->isIyuu($data)) {
                    $log .= ", isIyuu";
                    $pattern = $info['rating_pattern_in_desc'] ?? null;
                    if ($pattern && preg_match($pattern,$data['data']['format'], $matches)) {
                        $rating = $matches[1];
                    }
                }
            }

            $fallbackLinks = $sharedFallbackLinks;
            if ($site === self::SITE_IMDB && !empty($imdbLink)) {
                $fallbackLinks[] = $imdbLink;
            }
            $externalId = $this->extractExternalId($site, $siteEntry, $fallbackLinks);

            $allowEmptyRatingWithId = in_array($site, [self::SITE_IMDB, self::SITE_DOUBAN], true);
            if (($rating === '' || $rating === null) && !($allowEmptyRatingWithId && $externalId)) {
                $log .= ", can't get rating";
                continue;
            }
            if (($rating === '' || $rating === null) && $allowEmptyRatingWithId && $externalId) {
                $rating = 'N/A';
                $log .= ", missing rating but found id: $externalId";
            } else {
                $log .= ", get rating: $rating";
            }

            $results[$site] = [
                'rating' => $rating,
                'id' => $externalId,
            ];
        }
        //Second, imdb can get from imdb api
        if (!isset($results[self::SITE_IMDB]) && !empty($imdbLink)) {
            $imdb = new Imdb();
            $imdbRating = $imdb->getRating($imdbLink);
            $externalId = $this->extractExternalId(self::SITE_IMDB, $imdbLink);
            $results[self::SITE_IMDB] = [
                'rating' => $imdbRating,
                'id' => $externalId,
            ];
            $log .= ", again 'imdb' from: $imdbLink -> $imdbRating";
        }
        //Otherwise, get from desc
        if (!empty($desc)) {
            foreach (self::$validSites as $site => $info) {
                if (isset($results[$site])) {
                    continue;
                }
                if (empty($info['rating_pattern_in_desc'])) {
                    continue;
                }
                $pattern = $info['rating_pattern_in_desc'];
                $log .= ", at last, trying to get '$site' from desc with pattern: $pattern";
                if (preg_match($pattern, $desc, $matches)) {
                    $log .= ", get " . $matches[1];
                    $externalId = $this->extractExternalId($site, $ptGenData[$site] ?? []);
                    $results[$site] = [
                        'rating' => $matches[1],
                        'id' => $externalId,
                    ];
                } else {
                    $log .= ", not match";
                }
            }
        }
        do_log($log);
        return $results;
    }

    private function normalizeRatingInfo(string $siteId, $value): array
    {
        $rating = '';
        $externalId = null;
        if (is_array($value)) {
            $rating = $value['rating'] ?? '';
            $externalId = $value['id'] ?? null;
        } else {
            $rating = $value;
        }
        if (($rating === '' || $rating === null) && in_array($siteId, [self::SITE_IMDB, self::SITE_DOUBAN], true) && $externalId) {
            $rating = 'N/A';
        }
        return [$rating, $externalId];
    }

    private function extractExternalId(string $site, $siteEntry, array $fallbackLinks = []): ?string
    {
        if (!isset(self::$validSites[$site])) {
            return null;
        }
        $pattern = self::$validSites[$site]['url_pattern'] ?? null;
        $candidates = $fallbackLinks;
        $candidates = array_merge($candidates, $this->collectStringValues($siteEntry));
        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }
            $candidateId = $this->matchExternalId($site, $pattern, $candidate);
            if ($candidateId) {
                return $candidateId;
            }
        }
        return null;
    }

    private function collectStringValues($data): array
    {
        $results = [];
        $this->appendStringValues($data, $results);
        return $results;
    }

    private function appendStringValues($value, array &$results): void
    {
        if (is_string($value)) {
            $results[] = $value;
            return;
        }
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $item) {
            $this->appendStringValues($item, $results);
        }
    }

    private function matchExternalId(string $site, ?string $pattern, string $candidate): ?string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }
        if ($pattern && preg_match($pattern, $candidate, $matches) && !empty($matches[1])) {
            return $matches[1];
        }
        if ($site === self::SITE_IMDB) {
            if (preg_match('/^tt\d+$/i', $candidate)) {
                return strtolower($candidate);
            }
            if (preg_match('/^\d+$/', $candidate)) {
                return 'tt' . str_pad($candidate, 7, '0', STR_PAD_LEFT);
            }
        } else {
            if (preg_match('/^\d+$/', $candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    public function updateTorrentPtGen(int $id): bool|array
    {
        $now = Carbon::now();
        $log = "updateTorrentPtGen, torrent: " . $id;
        $torrent = Torrent::query()->find($id);
        if (empty($torrent)) {
            do_log("$log, Torrent not found");
            return false;
        }
        $extra = $torrent->extra;
        $arr = $extra->pt_gen;
        if (is_array($arr)) {
            if (!empty($arr['__updated_at'])) {
                $log .= ", updated_at: " . $arr['__updated_at'];
                $updatedAt = Carbon::parse($arr['__updated_at']);
                $diffInDays = $now->diffInDays($updatedAt);
                $log .= ", diffInDays: $diffInDays";
                if ($diffInDays < 30) {
                    do_log("$log, less 30 days, don't update");
                    return false;
                }
            }
            $link = $this->getLink($arr);
        } else {
            $link = $arr;
        }
        if (empty($link)) {
            do_log("$log, no link...");
            return false;
        }
        $ptGenInfo = [];
        foreach (self::$validSites as $site => $siteConfig) {
            if (!preg_match($siteConfig['url_pattern'], $link, $matches)) {
                continue;
            }
            try {
                $response = $this->generate($matches[0], true);
                $ptGenInfo[$site]['data'] = $response;
            } catch (\Exception $exception) {
                do_log("$log, site: $site can not be updated: " . $exception->getMessage(), 'error');
            }
        }
        if (empty($ptGenInfo)) {
            do_log("$log, no pt gen info updated");
            return false;
        }
        $siteIdAndRating = $this->listRatings($ptGenInfo, $torrent->url, $extra->descr);
        foreach ($siteIdAndRating as $key => $value) {
            if (!isset($ptGenInfo[$key]['data']) || !is_array($ptGenInfo[$key]['data'])) {
                continue;
            }
            $ratingValue = is_array($value) ? ($value['rating'] ?? '') : $value;
            $ptGenInfo[$key]['data']["__rating"] = $ratingValue;
            if (is_array($value) && isset($value['id']) && $value['id'] !== '') {
                $ptGenInfo[$key]['data']["__id"] = $value['id'];
            }
        }
        $ptGenInfo['__link'] = $link;
        $ptGenInfo['__updated_at'] = $now->toDateTimeString();
        TorrentExtra::query()->where('torrent_id', $id)->update(['pt_gen' => $ptGenInfo]);
        do_log("$log, success update");
        return $ptGenInfo;
    }

    public function getLink(array $ptGenInfo)
    {
        if (isset($ptGenInfo['__link'])) {
            //new
            return $ptGenInfo['__link'];
        }
        $result = '';
        foreach ($ptGenInfo as $item) {
            if (!empty($item['link'])) {
                //old, use the last one
                $result = $item['link'];
            }
        }
        return $result;
    }

    private function getRawPTGenRating(array $ptGenInfo, $site)
    {
        $key = $site . "_rating_average";
        if (isset($ptGenInfo[$key])) {
            return $ptGenInfo[$key];
        }
        if ($site == self::SITE_INDIENOVA) {
            $parts = preg_split('/[\s:]+/', $ptGenInfo['rate']);
            return Arr::last($parts);
        }
        return '';
    }

}
