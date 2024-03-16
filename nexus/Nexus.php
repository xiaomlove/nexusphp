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

    private static array $translationNamespaces = [];

    private static array $translations = [];

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

    public function incrementLogSequence(): void
    {
        $this->logSequence++;
    }

    private function getFirst(string $result): string
    {
        if (str_contains($result, ",")) {
            return strstr($result, ",", true);
        }
        return $result;
    }

    public function getRequestSchema(): string
    {
        $schema = $this->retrieveFromServer(['HTTP_X_FORWARDED_PROTO', 'REQUEST_SCHEME', 'HTTP_SCHEME']);
        if (empty($schema)) {
            $https = $this->retrieveFromServer(['HTTPS']);
            if ($https == 'on') {
                $schema = 'https';
            }
        }
        return $this->getFirst($schema);
    }

    public function getRequestHost(): string
    {
        $host = $this->retrieveFromServer(['HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'host'], true);
        return $this->getFirst(strval($host));
    }

    public function getRequestIp(): string
    {
        $ip = $this->retrieveFromServer(['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'x-forwarded-for', 'HTTP_REMOTE_ADDR', 'REMOTE_ADDR'], true);
        return $this->getFirst($ip);
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
        $log = "position: $position, key: $key";
        if ($key === null) {
            $key = md5($append);
            $log .= ", md5 key: $key";
        }
        if ($position == 'header') {
            if (!isset(self::$appendHeaders[$key])) {
                self::$appendHeaders[$key] = $append;
            } else {
                do_log("$log, [DUPLICATE]");
            }
        } elseif ($position == 'footer') {
            if (!isset(self::$appendFooters[$key])) {
                self::$appendFooters[$key] = $append;
            } else {
                do_log("$log, [DUPLICATE]");
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

    public static function addTranslationNamespace($path, $namespace)
    {
        if (empty($namespace)) {
            throw new \InvalidArgumentException("namespace can not be empty");
        }
        self::$translationNamespaces[$namespace] = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public static function trans($key, $replace = [], $locale = null)
    {
        if (!IN_NEXUS) {
            return trans($key, $replace, $locale);
        }
        if (empty(self::$translations)) {
            //load from default lang dir
            $langDir = ROOT_PATH . 'resources/lang/';
            self::loadTranslations($langDir);
            //load from namespace
            foreach (self::$translationNamespaces as $namespace => $path) {
                self::loadTranslations($path, $namespace);
            }
        }
        return self::getTranslation($key, $replace, $locale);
    }

    private static function loadTranslations($path, $namespace = null)
    {
        do_log("path: $path, namespace: $namespace", 'debug');
        $files = glob($path . '*/*');
        foreach ($files as $file) {
            if (!is_file($file)) {
                do_log("file: $file, is not file", 'debug');
                continue;
            }
            if (!is_readable($file)) {
                do_log("[TRANSLATION_FILE_NOT_READABLE], $file");
            }
            $values = require $file;
            $setKey = substr($file, strlen($path));
            if (substr($setKey, -4) == '.php') {
                $setKey = substr($setKey, 0, -4);
            }
            $setKey = str_replace('/', '.', $setKey);
            if ($namespace !== null) {
                $setKey = "$namespace.$setKey";
            }
            do_log("path: $path, namespace: $namespace, file: $file, setKey: $setKey", 'debug');
            arr_set(self::$translations, $setKey, $values);
        }
    }

    private static function getTranslation($key, $replace = [], $locale = null)
    {
        if (!$locale) {
            $lang = get_langfolder_cookie();
            $locale = \App\Http\Middleware\Locale::$languageMaps[$lang] ?? 'en';
        }
        $getKey = self::getTranslationGetKey($key, $locale);
        $result = arr_get(self::$translations, $getKey);
        if (empty($result) && $locale != 'en') {
            do_log("original getKey: $getKey can not get any translations", 'error');
            $getKey = self::getTranslationGetKey($key, 'en');
            $result = arr_get(self::$translations, $getKey);
        }
        if (!empty($replace)) {
            $search = array_map(function ($value) {return ":$value";}, array_keys($replace));
            $result = str_replace($search, array_values($replace), $result);
        }
        do_log("key: $key, replace: " . nexus_json_encode($replace) . ", locale: $locale, getKey: $getKey, result: $result", 'debug');
        return $result;
    }

    private static function getTranslationGetKey($key, $locale): string
    {
        $namespace = strstr($key, '::', true);
        if ($namespace !== false) {
            $getKey = sprintf('%s.%s.%s', $namespace, $locale, substr($key, strlen($namespace) + 2));
        } else {
            $getKey = $locale . "." . $key;
        }
//        do_log("key: $key, locale: $locale, namespace: $namespace, getKey: $getKey", 'debug');
        return $getKey;
    }


}
