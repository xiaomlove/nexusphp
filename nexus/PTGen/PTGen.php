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
        self::SITE_DOUBAN => [
            'url_pattern' => '/(?:https?:\/\/)?(?:(?:movie|www)\.)?douban\.com\/(?:subject|movie)\/(\d+)\/?/',
        ],
        self::SITE_IMDB => [
            'url_pattern' => '/(?:https?:\/\/)?(?:www\.)?imdb\.com\/title\/(tt\d+)\/?/',
        ],
        self::SITE_BANGUMI => [
            'url_pattern' => '/(?:https?:\/\/)?(?:bgm\.tv|bangumi\.tv|chii\.in)\/subject\/(\d+)\/?/',
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

    public function generateDouban(string $url): string
    {
        return $this->request($url, self::SITE_DOUBAN);
    }

    public function generateImdb(string $url): string
    {
        return $this->request($url, self::SITE_IMDB);
    }

    public function generateBangumi(string $url): string
    {
        return $this->request($url, self::SITE_BANGUMI);
    }

    private function request(string $url, string $site): string
    {
        $url = $this->buildUrl($url, $site);
        $logPrefix = "url: $url";
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

        return $bodyString;
    }

    private function buildUrl(string $url, string $site): string
    {
        if (!isset(self::$validSites[$site])) {
            throw new PTGenException("not support site: $site, only support: " . implode(", ", array_keys(self::$validSites)));
        }
        $siteInfo = self::$validSites[$site];
        $isIdValid = preg_match($siteInfo['url_pattern'], $url, $matches);
        if (!$isIdValid) {
            throw new PTGenException("invalid url: $url");
        }
        return sprintf('%s/?site=%s&sid=%s', trim($this->apiPoint, '/'), $site , $matches[1]);
    }
}