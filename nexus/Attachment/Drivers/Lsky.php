<?php
namespace Nexus\Attachment\Drivers;

use GuzzleHttp\Psr7;
use Nexus\Attachment\Storage;

class Lsky extends Storage {

    function upload(string $filepath): string
    {
        $api = get_setting("image_hosting_lsky.upload_api_endpoint");
        $token = get_setting("image_hosting_lsky.upload_token");
        $logPrefix = "filepath: $filepath, api: $api";
        $httpClient = new \GuzzleHttp\Client();
        $response = $httpClient->request('POST', $api, [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $token),
            ],
            'multipart' => [
                [
                    'name'     => 'file',
                    'contents' => Psr7\Utils::tryFopen($filepath, 'r')
                ]
            ]
        ]);
        $statusCode = $response->getStatusCode();
        $logPrefix .= ", status code: $statusCode";
        if ($statusCode != 200) {
            do_log("$logPrefix, statusCode != 200", "error");
            throw new \Exception("Unable to upload file, status code {$statusCode}");
        }
        $stringBody = (string)$response->getBody();
        $logPrefix .= ", body: $stringBody";
        $result = json_decode($stringBody, true);
        if (!is_array($result)) {
            do_log("$logPrefix, can not parse to array", "error");
            throw new \Exception("Unable to parse response body");
        }
        if (!isset($result["status"])) {
            do_log("$logPrefix, no status", "error");
            throw new \Exception("Unable to parse response body, no status");
        }
        if ($result["status"] !== true) {
            do_log("$logPrefix, status != true", "error");
            throw new \Exception("upload fail: " . $result["message"]);
        }
        if (!isset($result["data"]["links"]["url"])) {
            do_log("$logPrefix, no links url", "error");
            throw new \Exception("upload fail: no links url");
        }

        return $result["data"]["links"]["url"];
    }

    function getBaseUrl(): string
    {
        return get_setting("image_hosting_lsky.base_url");
    }

    function getDriverName(): string
    {
        return static::DRIVER_LSKY;
    }
}
