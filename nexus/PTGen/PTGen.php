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

    const FORMAT_HTML = 1;
    const FORMAT_JSON = 2;

    private static $formatText = [
        self::FORMAT_HTML => 'HTML',
        self::FORMAT_JSON => 'json',
    ];

    const SITE_DOUBAN = 'douban';
    const SITE_IMDB = 'imdb';
    const SITE_BANGUMI = 'bangumi';

    private static $validSites = [
        self::SITE_IMDB => [
            'url_pattern' => '/(?:https?:\/\/)?(?:www\.)?imdb\.com\/title\/(tt\d+)\/?/',
            'home_page' => 'https://www.imdb.com/',
        ],
        self::SITE_DOUBAN => [
            'url_pattern' => '/(?:https?:\/\/)?(?:(?:movie|www)\.)?douban\.com\/(?:subject|movie)\/(\d+)\/?/',
            'home_page' => 'https://www.douban.com/',
        ],
        self::SITE_BANGUMI => [
            'url_pattern' => '/(?:https?:\/\/)?(?:bgm\.tv|bangumi\.tv|chii\.in)\/subject\/(\d+)\/?/',
            'home_page' => 'https://bangumi.tv/',
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

    public function generate(string $url): array
    {
        foreach (self::$validSites as $site => $info) {
            if (preg_match($info['url_pattern'], $url, $matches)) {
                $targetUrl = sprintf('%s/?site=%s&sid=%s', trim($this->apiPoint, '/'), $site , $matches[1]);
                return $this->request($targetUrl);
            }
        }
        throw new PTGenException("invalid url: $url");
    }

    private function buildDetailsPageTableRow($ptGenArr, $site)
    {
        global $lang_details;
        $ptGenFormatted = $ptGenArr['format'];
        $prefix = sprintf("[img]%s[/img]\n", $ptGenArr['poster']);
        $ptGenFormatted = mb_substr($ptGenFormatted, mb_strlen($prefix, 'utf-8') + 1);
        $ptGenFormatted = format_comment($ptGenFormatted);
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
            <img src="{$ptGenArr['poster']}" width="105" onclick="Preview(this);" alt="poster" />
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

    private function request(string $url): array
    {
        global $Cache;
        $logPrefix = "url: $url";
        $cacheKey = __METHOD__ . ":$url";
        $cache = $Cache->get_value($cacheKey);
        if ($cache) {
            do_log("$logPrefix, from cache");
            return $cache;
        }
        $http = new Client();
        $response = $http->get($url, ['timeout' => 10]);
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
        return $bodyArr;
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

    public function renderDetailsPageDescription(array $torrentPtGenArr): array
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
                $html .= $this->buildDetailsPageTableRow($data, $site);
            } else {
                $ptGenArr = $this->generate($torrentPtGenArr[$site]['link']);
                $jsonArr[$site] = [
                    'link' => $link,
                    'data' => $ptGenArr,
                ];
                $html .= $this->buildDetailsPageTableRow($ptGenArr, $site);
                if (!$update) {
                    $update = true;
                }
            }
        }
        return ['json_arr' => $jsonArr, 'html' => $html, 'update' => $update];
    }
}