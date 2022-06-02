<?php
namespace Nexus;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nexus\Plugin\Hook;

final class Nexus
{
    private string $requestId;

    private int $logSequence = 0;

    private float $startTimestamp;

    private string $script;

    private string $platform;

    private static bool $booted = false;

    private static ?Nexus $instance = null;

    private static array $appendHeaders = [];

    private static array $appendFooters = [];

    const PLATFORM_USER = 'user';
    const PLATFORM_ADMIN = 'admin';
    const PLATFORM_TRACKER = 'tracker';
    const PLATFORMS = [self::PLATFORM_USER, self::PLATFORM_ADMIN, self::PLATFORM_TRACKER];

    private function __construct()
    {

    }

    private function __clone()
    {

    }

    public static function instance()
    {
        return self::$instance;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getStartTimestamp(): float
    {
        return $this->startTimestamp;
    }


    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getScript(): string
    {
        return $this->script;
    }

    public function getLogSequence(): int
    {
        return $this->logSequence;
    }

    public function isPlatformValid(): bool
    {
        return in_array($this->platform, self::PLATFORMS);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->platform == self::PLATFORM_ADMIN;
    }

    public function isPlatformUser(): bool
    {
        return $this->platform == self::PLATFORM_USER;
    }

    public function isScriptAnnounce(): bool
    {
        return $this->script == 'announce';
    }

    public function incrementLogSequence()
    {
        $this->logSequence++;
    }

    public function getRequestSchema()
    {
        $schema = $this->retrieveFromServer(['HTTP_X_FORWARDED_PROTO', 'REQUEST_SCHEME', 'HTTP_SCHEME']);
        if (empty($schema)) {
            $https = $this->retrieveFromServer(['HTTPS']);
            if ($https == 'on') {
                $schema = 'https';
            }
        }
        return $schema;
    }

    public function getRequestHost(): string
    {
        $host = $this->retrieveFromServer(['HTTP_HOST', 'host', ], true);
        return (string)$host;
    }

    public function getRequestIp()
    {
        return $this->retrieveFromServer(['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'x-forwarded-for', 'HTTP_REMOTE_ADDR', 'REMOTE_ADDR'], true);
    }

    private function retrieveFromServer(array $fields, bool $includeHeader = false)
    {
        if ($this->runningInOctane()) {
            $servers = request()->server();
            $headers = request()->header();
        } else {
            $servers = $_SERVER;
            $headers = getallheaders();
        }
        foreach ($fields as $field) {
            $result = $servers[$field] ?? null;
            if ($result && in_array($field, ['HTTP_X_FORWARDED_FOR', 'x-forwarded-for'])) {
                $result = preg_split('/[,\s]+/', $result);
            }
            if (is_array($result)) {
                $result = Arr::first($result);
            }
            if ($result !== null && $result !== '') {
                return $result;
            }
            if ($includeHeader) {
                $result = $headers[$field] ?? null;
                if (is_array($result)) {
                    $result = Arr::first($result);
                }
                if ($result !== null && $result !== '') {
                    return $result;
                }
            }
        }
    }

    private function runningInOctane(): bool
    {
        if (defined('RUNNING_IN_OCTANE') && RUNNING_IN_OCTANE) {
            return true;
        }
        return false;
    }

    private function generateRequestId(): string
    {
        $prefix = ($_SERVER['SCRIPT_FILENAME'] ?? '') . implode('', $_SERVER['argv'] ?? []);
        $prefix = substr(md5($prefix), 0, 4);
        // 4 + 23 = 27 characters, after replace '.', 26
        $requestId = str_replace('.', '', uniqid($prefix, true));
        $requestId .= bin2hex(random_bytes(3));
        return $requestId;
    }

    public static function boot()
    {
        if (self::$booted) {
//            file_put_contents('/tmp/reset.log', "booted\n",FILE_APPEND);
            return;
        }
//        file_put_contents('/tmp/reset.log', "booting\n",FILE_APPEND);
        $instance = new self();
        $instance->setStartTimestamp();
        $instance->setRequestId();
        $instance->setScript();
        $instance->setPlatform();
        self::$instance = $instance;
        self::$booted = true;
    }

    public static function flush()
    {
        self::$booted = false;
    }

    private function setRequestId()
    {
        $requestId = $this->retrieveFromServer(['HTTP_X_REQUEST_ID', 'REQUEST_ID', 'Request-Id', 'request-id'], true);
        if (empty($requestId)) {
            $requestId = $this->generateRequestId();
        }
        $this->requestId = (string)$requestId;
    }

    private function setScript()
    {
        $script = $this->retrieveFromServer(['SCRIPT_FILENAME', 'SCRIPT_NAME', 'Script', 'script'], true);
        if (str_contains($script, '.')) {
            $script = strstr(basename($script), '.', true);
        }
        $this->script = (string)$script;
    }

    private function setStartTimestamp()
    {
        $this->startTimestamp = microtime(true);
    }

    private function setPlatform()
    {
        $this->platform = (string)$this->retrieveFromServer(['HTTP_PLATFORM', 'Platform', 'platform'], true);
    }

    public static function js(string $js, string $position, bool $isFile, $key = null)
    {
        if ($isFile) {
            $append = sprintf('<script type="text/javascript" src="%s"></script>', $js);
        } else {
            $append = sprintf('<script type="text/javascript">%s</script>', $js);
        }
        self::appendJsCss($append, $position, $key);
    }

    public static function css(string $css, string $position, bool $isFile, $key = null)
    {
        if ($isFile) {
            $append = sprintf('<link rel="stylesheet" href="%s" type="text/css">', $css);
        } else {
            $append = sprintf('<style type="text/css">%s</style>', $css);
        }
        self::appendJsCss($append, $position, $key);
    }

    private static function appendJsCss($append, $position, $key = null)
    {
        if ($key === null) {
            $key = md5($append);
        }
        if ($position == 'header') {
            if (!isset(self::$appendHeaders[$key])) {
                self::$appendHeaders[$key] = $append;
            }
        } elseif ($position == 'footer') {
            if (!isset(self::$appendFooters[$key])) {
                self::$appendFooters[$key] = $append;
            }
        } else {
            throw new \InvalidArgumentException("Invalid position: $position");
        }
    }

    public static function getAppendHeaders(): array
    {
        return self::$appendHeaders;
    }

    public static function getAppendFooters(): array
    {
        return self::$appendFooters;
    }



}
