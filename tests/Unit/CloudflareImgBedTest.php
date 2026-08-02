<?php

namespace Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Nexus\Attachment\Drivers\CloudflareImgBed;
use PHPUnit\Framework\TestCase;

class CloudflareImgBedTest extends TestCase
{
    private string $imagePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imagePath = tempnam(sys_get_temp_dir(), 'imgbed_');
        file_put_contents($this->imagePath, 'image contents');
    }

    protected function tearDown(): void
    {
        @unlink($this->imagePath);
        parent::tearDown();
    }

    public function testUploadUsesApiTokenAndConfiguredChannel(): void
    {
        $history = [];
        $driver = $this->driver(
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                [
                    'src' => '/file/generated.jpg',
                    'publicUrl' => 'https://cdn.example.com/generated.jpg',
                ],
            ])),
            [
                'upload_api_endpoint' => 'https://img.example.com/upload?existing=value',
                'upload_token' => 'secret-token',
                'base_url' => 'https://cdn.example.com/',
                'upload_channel' => 'cfr2',
                'channel_name' => 'primary',
                'upload_folder' => 'nexusphp/images',
            ],
            $history
        );

        $url = $driver->upload($this->imagePath);

        self::assertSame('https://cdn.example.com/generated.jpg', $url);
        self::assertSame('https://cdn.example.com', $driver->getBaseUrl());
        self::assertSame('cloudflare_imgbed', $driver->getDriverName());
        self::assertCount(1, $history);

        $request = $history[0]['request'];
        self::assertSame('Bearer secret-token', $request->getHeaderLine('Authorization'));
        parse_str($request->getUri()->getQuery(), $query);
        self::assertSame('value', $query['existing']);
        self::assertSame('full', $query['returnFormat']);
        self::assertSame('cfr2', $query['uploadChannel']);
        self::assertSame('primary', $query['channelName']);
        self::assertSame('nexusphp/images', $query['uploadFolder']);
    }

    public function testUploadResolvesRelativeSrcAgainstEndpointOrigin(): void
    {
        $history = [];
        $driver = $this->driver(
            new Response(200, ['Content-Type' => 'application/json'], '[{"src":"/file/generated.jpg","publicUrl":""}]'),
            ['upload_api_endpoint' => 'https://img.example.com/upload'],
            $history
        );

        self::assertSame('https://img.example.com/file/generated.jpg', $driver->upload($this->imagePath));
        self::assertSame('https://img.example.com', $driver->getBaseUrl());
        self::assertSame('', $history[0]['request']->getHeaderLine('Authorization'));
    }

    private function driver(Response $response, array $settings, array &$history): CloudflareImgBed
    {
        $mock = new MockHandler([$response]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);

        return new class($client, $settings) extends CloudflareImgBed {
            public function __construct(Client $client, private readonly array $settings)
            {
                parent::__construct($client);
            }

            protected function setting(string $name, mixed $default = ''): mixed
            {
                return $this->settings[$name] ?? $default;
            }
        };
    }
}
