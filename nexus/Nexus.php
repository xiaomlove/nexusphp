<?php
namespace Nexus;

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
            $tmp = $this->retrieveFromServer(['HTTPS']);
            if ($tmp == 'on') {
                $schema = 'https';
            }
        }
        return $schema;
    }

    public function getRequestIp()
    {
        $ip = $this->retrieveFromServer(['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_REMOTE_ADDR']);
        if (empty($ip)) {
            $ip = request()->getClientIp();
        }
        return $ip;
    }

    private function retrieveFromServer(array $fields)
    {
        if ($this->runningInOctane()) {
            $servers = request()->server();
        } else {
            $servers = $_SERVER;
        }
        foreach ($fields as $field) {
            if (!empty($servers[$field])) {
                do_log("got from $field");
                return $servers[$field];
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
        $requestId = '';
        $names = ['HTTP_X_REQUEST_ID', 'REQUEST_ID', 'Request-Id', 'request-id'];
        if ($this->runningInOctane()) {
            $request = request();
            foreach ($names as $name) {
                $requestId = $request->server($name, $request->header($name));
                if (!empty($requestId)) {
                    break;
                }
            }
        } else {
            foreach ($names as $name) {
                $requestId = $_SERVER[$name] ?? '';
                if (!empty($requestId)) {
                    break;
                }
            }
        }
        if (empty($requestId)) {
            $requestId = $this->generateRequestId();
        }
        $this->requestId = $requestId;
    }

    private function setScript()
    {
        if ($this->runningInOctane()) {
            $request = request();
            $script = $request->header('script_filename', '');
        } else {
            $script = strstr(basename($_SERVER['SCRIPT_FILENAME']), '.', true);
        }
        $this->script = $script;
    }

    private function setStartTimestamp()
    {
        $this->startTimestamp = microtime(true);
    }

    private function setPlatform()
    {
        if ($this->runningInOctane()) {
            $request = request();
            $platform = $request->header('platform', '');
        } else {
            $platform = $_SERVER['HTTP_PLATFORM'] ?? '';
        }
        $this->platform = $platform;
    }

    public static function js(string $js, string $position, bool $isFile)
    {
        if ($isFile) {
            $append = sprintf('<script type="text/javascript" src="%s"></script>', $js);
        } else {
            $append = sprintf('<script type="text/javascript">%s</script>', $js);
        }
        if ($position == 'header') {
            self::$appendHeaders[] = $append;
        } elseif ($position == 'footer') {
            self::$appendFooters[] = $append;
        } else {
            throw new \InvalidArgumentException("Invalid position: $position");
        }
    }

    public static function css(string $css, string $position, bool $isFile)
    {
        if ($isFile) {
            $append = sprintf('<link rel="stylesheet" href="%s" type="text/css">', $css);
        } else {
            $append = sprintf('<style type="text/css">%s</style>', $css);
        }
        if ($position == 'header') {
            self::$appendHeaders[] = $append;
        } elseif ($position == 'footer') {
            self::$appendFooters[] = $append;
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
