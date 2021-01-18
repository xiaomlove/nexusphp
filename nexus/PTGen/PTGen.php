<?php

/**
 * get media info base on PT-Gen
 *
 * @since 1.6
 * @see  https://github.com/Rhilip/pt-gen-cfworker
 */
namespace Nexus\PTGen;

use GuzzleHttp\Client;

class PTGen
{
    private $apiPoint;

    const SITE_DOUBAN = 'douban';
    const SITE_IMDB = 'imdb';
    const SITE_BANGUMI = 'bangumi';

    private static $validSites = [
        self::SITE_IMDB => [
            'url_pattern' => '/(?:https?:\/\/)?(?:www\.)?imdb\.com\/title\/(tt\d+)\/?/',
            'home_page' => 'https://www.imdb.com/',
            'rating_average_img' => 'pic/imdb2.png',
        ],
        self::SITE_DOUBAN => [
            'url_pattern' => '/(?:https?:\/\/)?(?:(?:movie|www)\.)?douban\.com\/(?:subject|movie)\/(\d+)\/?/',
            'home_page' => 'https://www.douban.com/',
            'rating_average_img' => 'pic/douban2.png',
        ],
        self::SITE_BANGUMI => [
            'url_pattern' => '/(?:https?:\/\/)?(?:bgm\.tv|bangumi\.tv|chii\.in)\/subject\/(\d+)\/?/',
            'home_page' => 'https://bangumi.tv/',
            'rating_average_img' => 'pic/douban2.png',
        ],
    ];


    public function __construct()
    {
        $this->setApiPoint('https://ptgen.rhilip.info');
    }

    public function getApiPoint(): string
    {
        return $this->apiPoint;
    }

    public function setApiPoint(string $apiPoint)
    {
        $this->apiPoint = $apiPoint;
    }

    public function generate(string $url, bool $withoutCache = false): array
    {
        $parsed = $this->parse($url);
        $targetUrl = sprintf('%s/?site=%s&sid=%s', trim($this->apiPoint, '/'), $parsed['site'] , $parsed['id']);
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

    private function buildDetailsPageTableRow($torrentId, $ptGenArr, $site)
    {
        global $lang_details;
        $ptGenFormatted = $ptGenArr['format'];
        $poster = '';
        if (!empty($ptGenArr['poster'])) {
            $poster = $ptGenArr['poster'];
            $prefix = sprintf("[img]%s[/img]\n", $poster);
            $ptGenFormatted = mb_substr($ptGenFormatted, mb_strlen($prefix, 'utf-8') + 1);
        }
        $ptGenFormatted = format_comment($ptGenFormatted);
        $ptGenFormatted .= sprintf(
            '%s%s%s<a href="retriver.php?id=%s&type=1&siteid=%s">%s</a>',
            $lang_details['text_information_updated_at'], !empty($ptGenArr['generate_at']) ? date('Y-m-d H:i:s', intval($ptGenArr['generate_at'] / 1000)) : '', $lang_details['text_might_be_outdated'],
            $torrentId, $site, $lang_details['text_here_to_update']
        );
        $titleShowOrHide = $lang_details['title_show_or_hide'] ?? '';
        $id = 'pt-gen-' . $site;
        $html = <<<HTML
<tr>
    <td class="rowhead">
        <a href="javascript: klappe_ext('{$id}')">
            <span class="nowrap">
                <img id="pic{$id}" class="minus" src="pic/trans.gif" alt="Show/Hide" title="{$titleShowOrHide}" />
                PT-Gen-{$site}
            </span>
        </a>
        <div id="poster{$id}">
            <img src="{$poster}" width="105" onclick="Preview(this);" alt="poster" />
        </div>
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
        global $Cache;
        $begin = microtime(true);
        $logPrefix = "url: $url";
        $cacheKey = $this->getApiPointResultCacheKey($url);
        if (!$withoutCache) {
            $cache = $Cache->get_value($cacheKey);
            if ($cache) {
                do_log("$logPrefix, from cache");
                return $cache;
            }
        }
        $http = new Client();
        $response = $http->get($url, ['timeout' => 5]);
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
        if (!isset($bodyArr['success']) || !$bodyArr['success']) {
            $msg = "error: " . $bodyArr['error'] ?? '';
            do_log("$logPrefix, response: $bodyString");
            throw new PTGenException($msg);
        }
        $Cache->cache_value($cacheKey, $bodyArr, 24 * 3600);
        do_log("$logPrefix, success get from api point, use time: " . (microtime(true) - $begin));
        return $bodyArr;
    }

    public function deleteApiPointResultCache($url)
    {
        global $Cache;
        $Cache->delete_value($this->getApiPointResultCacheKey($url));
    }

    private function getApiPointResultCacheKey($url)
    {
        return __METHOD__ . "_$url";
    }

    public function renderUploadPageFormInput($ptGen = '')
    {
        global $lang_functions;
        $html = '';
        $ptGen = (array)json_decode($ptGen, true);
        foreach (self::$validSites as $site => $info) {
            $value = $ptGen[$site]['link'] ?? '';
            $x = $lang_functions["row_pt_gen_{$site}_url"];
            $y = "<input type=\"text\" style=\"width: 650px;\" name=\"pt_gen[{$site}][link]\" value=\"{$value}\" /><br /><font class=\"medium\">".$lang_functions["text_pt_gen_{$site}_url_note"]."</font>";
            $html .= tr($x, $y, 1);
        }
        return $html;
    }

    public function renderDetailsPageDescription($torrentId, array $torrentPtGenArr): array
    {
        $html = '';
        $jsonArr = [];
        $update = false;
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

    public function renderTorrentsPageAverageRating(array $ptGenData)
    {
        $result = '<td class="embedded" style="text-align: right; width: 40px;padding-right: 5px"><div style="display: flex;flex-direction: column">';
        $count = 1;
        foreach (self::$validSites as $site => $info) {
            $rating = $ptGenData[$site]['data']["{$site}_rating_average"] ?? '';
            if (empty($rating)) {
                continue;
            }
            if ($count > 2) {
                //only show the first two
                break;
            }
            $result .= sprintf(
                '<div style="display: flex;align-content: center;justify-content: space-between;padding: 2px 0"><img src="%s" alt="%s" title="%s" style="max-width: 16px;max-height: 16px"/><span>%s</span></div>',
                $info['rating_average_img'], $site, $site, $rating
            );
            $count++;
        }
        $result .= '</div></td>';
        return $result;
    }
}