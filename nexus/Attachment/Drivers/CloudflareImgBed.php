<?php
namespace Nexus\Attachment\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Nexus\Attachment\Storage;

class CloudflareImgBed extends Storage
{
    private ?ClientInterface $httpClient;

    public function __construct(?ClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient;
    }

    function upload(string $filepath): string
    {
        $api = trim((string) $this->setting('upload_api_endpoint'));
        if ($api === '') {
            throw new \RuntimeException('CloudFlare ImgBed upload endpoint is required.');
        }

        $uri = new Uri($api);
        if (!in_array(strtolower($uri->getScheme()), ['http', 'https'], true) || $uri->getHost() === '') {
            throw new \RuntimeException('Invalid CloudFlare ImgBed upload endpoint.');
        }

        $uri = Uri::withQueryValue($uri, 'returnFormat', 'full');
        $querySettings = [
            'uploadChannel' => 'upload_channel',
            'channelName' => 'channel_name',
            'uploadFolder' => 'upload_folder',
        ];
        foreach ($querySettings as $queryName => $settingName) {
            $value = trim((string) $this->setting($settingName));
            if ($value !== '') {
                $uri = Uri::withQueryValue($uri, $queryName, $value);
            }
        }

        $headers = [];
        $token = trim((string) $this->setting('upload_token'));
        if ($token !== '') {
            $headers['Authorization'] = "Bearer $token";
        }

        try {
            $response = $this->httpClient()->request('POST', $uri, [
                'headers' => $headers,
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => Psr7\Utils::tryFopen($filepath, 'r'),
                        'filename' => basename($filepath),
                    ],
                ],
                'connect_timeout' => 10,
                'timeout' => 60,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            do_log(sprintf('CloudFlare ImgBed upload request failed: %s', $e->getMessage()), 'error');
            throw new \RuntimeException('CloudFlare ImgBed upload request failed.', 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        if ($statusCode < 200 || $statusCode >= 300) {
            do_log(sprintf(
                'CloudFlare ImgBed upload failed, status code: %d, body: %s',
                $statusCode,
                mb_substr($body, 0, 2000)
            ), 'error');
            throw new \RuntimeException("CloudFlare ImgBed upload failed, status code $statusCode.");
        }

        try {
            $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            do_log('CloudFlare ImgBed returned invalid JSON.', 'error');
            throw new \RuntimeException('CloudFlare ImgBed returned invalid JSON.', 0, $e);
        }

        $first = is_array($result) ? ($result[0] ?? null) : null;
        if (!is_array($first)) {
            throw new \RuntimeException('CloudFlare ImgBed response does not contain an uploaded file.');
        }

        $url = trim((string) ($first['publicUrl'] ?? ''));
        if ($url === '') {
            $url = trim((string) ($first['src'] ?? ''));
        }
        if ($url === '') {
            throw new \RuntimeException('CloudFlare ImgBed response does not contain a file URL.');
        }

        return $this->absoluteUrl($uri, $url);
    }

    function getBaseUrl(): string
    {
        $baseUrl = trim((string) $this->setting('base_url'));
        if ($baseUrl !== '') {
            return rtrim($baseUrl, '/');
        }

        $api = trim((string) $this->setting('upload_api_endpoint'));
        if ($api === '') {
            return '';
        }
        $uri = new Uri($api);
        return (string) $uri->withPath('')->withQuery('')->withFragment('');
    }

    function getDriverName(): string
    {
        return static::DRIVER_CLOUDFLARE_IMGBED;
    }

    protected function setting(string $name, mixed $default = ''): mixed
    {
        return get_setting("image_hosting_cloudflare_imgbed.$name", $default);
    }

    private function httpClient(): ClientInterface
    {
        return $this->httpClient ??= new Client();
    }

    private function absoluteUrl(Uri $endpoint, string $url): string
    {
        $result = new Uri($url);
        if ($result->getScheme() !== '') {
            return (string) $result;
        }

        $origin = $endpoint->withPath('/')->withQuery('')->withFragment('');
        return (string) UriResolver::resolve($origin, $result);
    }
}
