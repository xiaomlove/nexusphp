<?php

use App\Enums\ModelEventEnum;
use App\Enums\Permission\RoutePermissionEnum;
use App\Exceptions\InsufficientPermissionException;
use App\Exceptions\NexusException;
use App\Exceptions\SeedBoxYesException;
use App\Http\Middleware\Locale;
use App\Jobs\FireEvent;
use App\Models\Attachment;
use App\Models\Language;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\TorrentState;
use App\Models\TrackerUrl;
use App\Models\User;
use App\Models\UserMeta;
use App\Repositories\MessageRepository;
use App\Repositories\SeedBoxRepository;
use App\Repositories\ToolRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use GeoIp2\Database\Reader;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;

function get_global_sp_state()
{
    static $global_promotion_state;
    if (is_null($global_promotion_state)) {
        $timeline = TorrentState::resolveTimeline();
        $current = $timeline['current'] ?? null;

        if (is_array($current) && isset($current['global_sp_state'])) {
            $global_promotion_state = $current['global_sp_state'];
        } else {
            $global_promotion_state = Torrent::PROMOTION_NORMAL;
        }
    }

    return $global_promotion_state;
}

// IP Validation
function validip($ip)
{
    if (! ip2long($ip)) { // IPv6
        return true;
    }
    if (! empty($ip) && $ip == long2ip(ip2long($ip))) {
        // reserved IANA IPv4 addresses
        // http://www.iana.org/assignments/ipv4-address-space
        $reserved_ips = [
            ['192.0.2.0', '192.0.2.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['255.255.255.0', '255.255.255.255'],
        ];

        foreach ($reserved_ips as $r) {
            $min = ip2long($r[0]);
            $max = ip2long($r[1]);
            if ((ip2long($ip) >= $min) && (ip2long($ip) <= $max)) {
                return false;
            }
        }

        return true;
    } else {
        return false;
    }
}

function getip($real = true)
{
    if (isset($_SERVER)) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && validip($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP']) && validip($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
    } else {
        if (getenv('HTTP_X_FORWARDED_FOR') && validip(getenv('HTTP_X_FORWARDED_FOR'))) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('HTTP_CLIENT_IP') && validip(getenv('HTTP_CLIENT_IP'))) {
            $ip = getenv('HTTP_CLIENT_IP');
        } else {
            $ip = getenv('REMOTE_ADDR') ?? '';
        }
    }
    $ip = trim(trim($ip), ',');
    if ($real && str_contains($ip, ',')) {
        return strstr($ip, ',', true);
    }

    return $ip;
}

function sqlesc($value)
{
    if (is_null($value)) {
        return 'null';
    }
    $value = "'".mysql_real_escape_string($value)."'";

    return $value;
}

function hash_pad($hash)
{
    if (is_resource($hash)) {
        rewind($hash);
        $hash = stream_get_contents($hash);
    }

    return str_pad($hash, 20);
}

// no need any more...
/*
function strip_magic_quotes($arr)
{
    foreach ($arr as $k => $v)
    {
        if (is_array($v))
        {
            $arr[$k] = strip_magic_quotes($v);
        } else {
            $arr[$k] = stripslashes($v);
        }
    }
    return $arr;
}

if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
{
    if (!empty($_GET)) {
        $_GET = strip_magic_quotes($_GET);
    }
    if (!empty($_POST)) {
        $_POST = strip_magic_quotes($_POST);
    }
    if (!empty($_COOKIE)) {
        $_COOKIE = strip_magic_quotes($_COOKIE);
    }
}
*/

function get_langfolder_list()
{
    // do not access db for speed up, or for flexibility
    //	return array("en", "chs", "cht", "ko", "ja");
    return Language::listAvailable();
}

function printLine($line, $exist = false)
{
    echo '['.date('Y-m-d H:i:s')."] $line<br />";
    if ($exist) {
        exit(0);
    }
}

/**
 * write log, use in both pure nexus and inside laravel
 *
 * @param  string  $level
 */
function do_log($log, $level = 'info', $echo = false)
{
    static $env, $setLogLevel;
    if (is_null($setLogLevel)) {
        $setLogLevel = nexus_env('LOG_LEVEL', 'debug');
    }
    if (is_null($env)) {
        $env = nexus_env('APP_ENV', 'production');
    }
    $logLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
    $setLogLevelKey = array_search($setLogLevel, $logLevels);
    $currentLogLevelKey = array_search($level, $logLevels);
    if ($currentLogLevelKey === false) {
        $level = 'error';
        $log = "[ERROR_LOG_LEVEL] $log";
        $currentLogLevelKey = array_search($level, $logLevels);
    }
    if ($currentLogLevelKey < $setLogLevelKey) {
        return;
    }

    $logFile = getLogFile();
    if (($fd = fopen($logFile, 'a')) === false) {
        $log .= "--------Can not open $logFile";
        $fd = fopen(sys_get_temp_dir().'/nexus.log', 'a');
    }
    $uid = 0;
    if (IN_NEXUS) {
        global $CURUSER;
        $user = $CURUSER;
        $uid = $user['id'] ?? 0;
        $passkey = $user['passkey'] ?? $_REQUEST['passkey'] ?? $_REQUEST['authkey'] ?? '';
    } else {
        try {
            $user = Auth::user();
            $uid = $user->id ?? 0;
            $passkey = $user->passkey ?? request('passkey', request('authkey', ''));
        } catch (Throwable $exception) {
            $passkey = '!IN_NEXUS:'.$exception->getMessage();
        }
    }
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $content = sprintf(
        '[%s] [%s] [%s] [%s] [%s] [%s] %s.%s %s:%s %s%s%s %s%s',
        getDtMillis(true),
        nexus() ? nexus()->getRequestId() : 'NO_REQUEST_ID',
        nexus() ? nexus()->getLogSequence() : 0,
        sprintf('%.3f', microtime(true) - (nexus() ? nexus()->getStartTimestamp() : 0)),
        $uid,
        $passkey,
        $env, strtoupper($level),
        $backtrace[0]['file'] ?? '',
        $backtrace[0]['line'] ?? '',
        $backtrace[1]['class'] ?? '',
        $backtrace[1]['type'] ?? '',
        $backtrace[1]['function'] ?? '',
        $log,
        PHP_EOL
    );
    fwrite($fd, $content);
    fclose($fd);
    if (is_bool($echo) && $echo) {
        echo $content.PHP_EOL;
    }
    if (nexus()) {
        nexus()->incrementLogSequence();
    }
}

function getDtMillis($withTimeZone = false): string
{
    $dt = DateTime::createFromFormat('U.u', sprintf('%.6f', microtime(true)));
    $dt->setTimezone(new DateTimeZone(nexus_env('TIMEZONE', 'UTC')));
    $format = $withTimeZone ? 'Y-m-d\TH:i:s.vP' : 'Y-m-d H:i:s.v';

    return $dt->format($format);
}

function getDtMicro($withTimeZone = false): string
{
    $dt = DateTime::createFromFormat('U.u', sprintf('%.6f', microtime(true)));
    $dt->setTimezone(new DateTimeZone(nexus_env('TIMEZONE', 'UTC')));
    $format = $withTimeZone ? 'Y-m-d\TH:i:s.uP' : 'Y-m-d H:i:s.u';

    return $dt->format($format);
}

function getLogFile($append = '')
{
    static $logFiles = [];
    if (isset($logFiles[$append])) {
        return $logFiles[$append];
    }
    $std = ['php://stdout', 'php://stderr'];
    $logFileFromDotEnv = nexus_env('LOG_FILE');
    if ($logFileFromDotEnv && in_array($logFileFromDotEnv, $std)) {
        return $logFiles[$append] = $logFileFromDotEnv;
    }
    $path = getenv('NEXUS_LOG_DIR', true);
    if (in_array($path, $std)) {
        return $logFiles[$append] = $path;
    }
    $fromEnv = true;
    if ($path === false) {
        $fromEnv = false;
        $path = sys_get_temp_dir();
    }
    $logFile = rtrim($path, '/').'/nexus.log';
    if (! $fromEnv && $logFileFromDotEnv) {
        $logFile = $logFileFromDotEnv;
    }
    $lastDotPos = strrpos($logFile, '.');
    if ($lastDotPos !== false) {
        $prefix = substr($logFile, 0, $lastDotPos);
        $suffix = substr($logFile, $lastDotPos);
    } else {
        $prefix = $logFile;
        $suffix = '';
    }
    $name = $prefix;
    if ($append) {
        $name .= "-$append";
    }
    if (isRunningInConsole()) {
        $scriptUserInfo = posix_getpwuid(posix_getuid());
        $name .= sprintf('-cli-%s', $scriptUserInfo['name']);
    }
    $name .= '-'.date('Y-m-d');

    return $logFiles[$append] = $name.$suffix;

}

function nexus_config($key, $default = null)
{
    if (! IN_NEXUS) {
        return config($key, $default);
    }
    static $configs;
    if (is_null($configs)) {
        // get all configuration from config file
        //		$files = glob(ROOT_PATH . 'config/*.php');
        $files = [
            ROOT_PATH.'config/nexus.php',
            ROOT_PATH.'config/emoji.php',
            ROOT_PATH.'config/captcha.php',
            ROOT_PATH.'config/clickhouse.php',
        ];
        foreach ($files as $file) {
            $basename = basename($file);
            if ($basename == 'allconfig.php') {
                // exclude the NexusPHP default config file
                continue;
            }
            $values = require $file;
            $configPrefix = strstr($basename, '.php', true);
            $configs[$configPrefix] = $values;
        }
    }

    return arr_get($configs, $key, $default);
}

/**
 * get setting for given name and prefix
 *
 * @date 2021/1/11
 *
 * @param  null  $name
 * @param  null  $default
 */
function get_setting($name = null, $default = null): mixed
{
    static $settings;
    if (is_null($settings)) {
        $settings = NexusDB::remember('nexus_settings_in_nexus', 600, function () {
            // get all settings from database
            return Setting::getFromDb();
        });
    }
    if (is_null($name)) {
        return $settings;
    }

    return arr_get($settings, $name, $default);
}

function get_setting_from_db($name = null, $default = null)
{
    static $final;
    if (is_null($final)) {
        $final = Setting::getFromDb();
    }
    if (is_null($name)) {
        return $final;
    }

    return arr_get($final, $name, $default);
}

function nexus_env($key = null, $default = null)
{
    static $env;
    if (is_null($env)) {
        $envFile = dirname(__DIR__).'/.env';
        $env = readEnvFile($envFile);
    }
    if (is_null($key)) {
        return $env;
    }

    return $env[$key] ?? $default;
}

function readEnvFile($envFile)
{
    if (! file_exists($envFile)) {
        if (php_sapi_name() == 'cli') {
            return [];
        }
        throw new RuntimeException("env file : $envFile is not exists in the root path.");
    }
    $env = [];
    $fp = fopen($envFile, 'r');
    if ($fp === false) {
        throw new RuntimeException(".env file: $envFile is not readable.");
    }
    while (($line = fgets($fp)) !== false) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos <= 0) {
            continue;
        }
        if (mb_substr($line, 0, 1, 'utf-8') == '#') {
            continue;
        }
        $lineKey = normalize_env(mb_substr($line, 0, $pos, 'utf-8'));
        $lineValue = normalize_env(mb_substr($line, $pos + 1, null, 'utf-8'));
        $env[$lineKey] = $lineValue;
    }

    return $env;
}

function normalize_env($value)
{
    $value = trim($value);
    $toStrip = ['\'', '"'];
    if (in_array(mb_substr($value, 0, 1, 'utf-8'), $toStrip)) {
        $value = mb_substr($value, 1, null, 'utf-8');
    }
    if (in_array(mb_substr($value, -1, null, 'utf-8'), $toStrip)) {
        $value = mb_substr($value, 0, -1, 'utf-8');
    }
    switch (strtolower($value)) {
        case 'true':
            return true;
        case 'false':
            return false;
        case 'null':
            return null;
        default:
            return $value;
    }
}

/**
 * Get an item from an array using "dot" notation.
 *
 * reference to Laravel
 *
 * @date 2021/1/14
 *
 * @param  null  $default
 * @return mixed|null
 */
function arr_get($array, $key, $default = null)
{
    if (strpos($key, '.') === false) {
        return $array[$key] ?? $default;
    }
    foreach (explode('.', $key) as $segment) {
        if (isset($array[$segment])) {
            $array = $array[$segment];
        } else {
            return $default;
        }
    }

    return $array;
}

/**
 * From Laravel
 *
 * Set an array item to a given value using "dot" notation.
 *
 * If no key is given to the method, the entire array will be replaced.
 *
 * @param  array  $array
 * @param  string|null  $key
 * @param  mixed  $value
 * @return array
 */
function arr_set(&$array, $key, $value)
{
    if (is_null($key)) {
        return $array = $value;
    }

    $keys = explode('.', $key);

    foreach ($keys as $i => $key) {
        if (count($keys) === 1) {
            break;
        }

        unset($keys[$i]);

        // If the key doesn't exist at this depth, we will just create an empty array
        // to hold the next value, allowing us to create the arrays to hold final
        // values at the correct depth. Then we'll keep digging into the array.
        if (! isset($array[$key]) || ! is_array($array[$key])) {
            $array[$key] = [];
        }

        $array = &$array[$key];
    }

    $array[array_shift($keys)] = $value;

    return $array;
}

function isHttps(): bool
{
    if (isRunningInConsole()) {
        $securityLogin = get_setting('security.securelogin');
        if ($securityLogin != 'no') {
            return true;
        }

        return false;
    }

    return nexus()->getRequestSchema() == 'https';
}

function getSchemeAndHttpHost(bool $fromConfig = false): string
{
    if (isRunningInConsole() || $fromConfig) {
        $host = get_setting('basic.BASEURL');
    } else {
        $host = nexus()->getRequestHost();
    }
    $isHttps = isHttps();
    $protocol = $isHttps ? 'https' : 'http';

    return "$protocol://".$host;
}

function getBaseUrl()
{
    $url = getSchemeAndHttpHost();
    if (! isRunningInConsole()) {
        $requestUri = $_SERVER['REQUEST_URI'];
        $pos = strpos($requestUri, '?');
        if ($pos !== false) {
            $url .= substr($requestUri, 0, $pos);
        } else {
            $url .= $requestUri;
        }
    }

    return trim($url, '/');
}

function nexus_json_encode($data)
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function api(...$args)
{
    do_log('api begin');
    if (func_num_args() < 3) {
        // 参数少于3个时，默认为错误状态。
        $ret = -1;
        $msg = isset($args[0]) ? $args[0] : 'ERROR';
        $data = isset($args[1]) ? $args[1] : [];
    } else {
        $ret = $args[0];
        $msg = $args[1];
        $data = $args[2];
    }
    if ($data instanceof JsonResource) {
        $data = $data->response()->getData(true);
    }
    do_log('api after prepare data');
    //    dd($data);
    $time = (float) number_format(microtime(true) - nexus()->getStartTimestamp(), 3);
    $count = null;
    $resultKey = 'ret';
    $msgKey = 'msg';
    $format = $_REQUEST['__format'] ?? '';
    if (in_array($format, ['layui-table', 'data-table'])) {
        $resultKey = 'code';
        $count = $data['meta']['total'] ?? 0;
        if (isset($data['data'])) {
            $data = $data['data'];
        }
    }
    $results = [
        $resultKey => (int) $ret,
        $msgKey => (string) $msg,
        'data' => $data,
        'time' => $time,
        'rid' => nexus()->getRequestId(),
    ];
    if ($format == 'layui-table') {
        $results['count'] = $count;
    }
    if ($format == 'data-table') {
        $results['draw'] = intval($_REQUEST['draw'] ?? 1);
        $results['recordsTotal'] = $count;
        $results['recordsFiltered'] = $count;
    }
    if (! IN_NEXUS && config('app.debug')) {
        $results['queries'] = last_query(true);
    }
    do_log('api end');

    return $results;
}

function success(...$args)
{
    $ret = 0;
    $msg = 'OK';
    $data = [];
    $argumentCount = func_num_args();
    if ($argumentCount == 1) {
        $data = $args[0];
    } elseif ($argumentCount == 2) {
        $msg = $args[0];
        $data = $args[1];
    }
    do_log('success before api');

    return api($ret, $msg, $data);
}

function fail(...$args)
{
    $ret = -1;
    $msg = 'ERROR';
    $data = [];
    $argumentCount = func_num_args();
    if ($argumentCount == 1) {
        $data = $args[0];
    } elseif ($argumentCount == 2) {
        $msg = $args[0];
        $data = $args[1];
    }

    return api($ret, $msg, $data);
}

function last_query($all = false, $format = 'json')
{
    try {
        $connectionName = NexusDB::getConnectionName();
        $connection = IN_NEXUS
            ? Manager::connection($connectionName)
            : DB::connection($connectionName);
    } catch (Throwable $e) {
        return $all === 'COUNT' ? 0 : ($all ? [] : '');
    }
    if ($all === 'COUNT') {
        try {
            return count($connection->getQueryLog());
        } catch (Throwable $e) {
            return 0;
        }
    }
    try {
        $queries = $connection->getRawQueryLog();
    } catch (Throwable $e) {
        $queries = [];
    }
    if ($all) {
        return $queries;
    }
    if (empty($queries)) {
        return '';
    }
    $last = last($queries);
    if ($format === 'json') {
        return nexus_json_encode($last);
    }

    return $last;
}

function format_datetime($datetime, $format = 'Y-m-d H:i')
{
    if (empty($datetime)) {
        return null;
    }
    try {
        $carbonTime = Carbon::parse($datetime);

        return $carbonTime->format($format);
    } catch (Exception) {
        do_log("Invalid datetime: $datetime", 'error');

        return $datetime;
    }
}

function nexus_trans($key, $replace = [], $locale = null)
{
    return Nexus\Nexus::trans($key, $replace, $locale);
}

function isRunningInConsole(): bool
{
    return ! RUNNING_IN_OCTANE && php_sapi_name() == 'cli';
}

function isRunningOnWindows(): bool
{
    return ! RUNNING_IN_OCTANE && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

function command_exists($command): bool
{
    return ! (trim(exec("command -v $command")) == '');
}

function get_tracker_schema_and_host($trackerUrlId, $combine = false): array|string
{
    /*
    global $https_announce_urls, $announce_urls;
    $httpsAnnounceUrls = array_filter($https_announce_urls);
    $log = "cookie: " . json_encode($_COOKIE) . ", https_announce_urls: " . json_encode($httpsAnnounceUrls);
    if (
        (isset($_COOKIE["c_secure_tracker_ssl"]) && $_COOKIE["c_secure_tracker_ssl"] == base64("yeah"))
        || !empty($httpsAnnounceUrls)
        || isHttps()
    ) {
        $log .= ", c_secure_tracker_ssl = base64('yeah'): " . base64("yeah") . ", or not empty https_announce_urls, or isHttps()";
        $tracker_ssl = true;
    }  else {
        $tracker_ssl = false;
    }
    $log .= ", tracker_ssl: $tracker_ssl";

    if ($tracker_ssl == true){
        $ssl_torrent = "https://";
        if ($https_announce_urls[0] != "") {
            $log .= ", https_announce_urls not empty, use it";
            $base_announce_url = $https_announce_urls[0];
        } else {
            $log .= ", https_announce_urls empty, use announce_urls[0]";
            $base_announce_url = $announce_urls[0];
        }
    } else {
        $ssl_torrent = "http://";
        $base_announce_url = $announce_urls[0];
    }
    do_log($log);
    if ($combine) {
        return $ssl_torrent . $base_announce_url;
    }
    */
    $log = "tracker_url_id: $trackerUrlId, combine: $combine";
    $url = TrackerUrl::getById($trackerUrlId);
    if (empty($url)) {
        $ssl_torrent = isHttps() ? 'https://' : 'http://';
        $base_announce_url = sprintf(
            '%s/%s',
            trim(Setting::getBaseUrl(), '/'), trim(DEFAULT_TRACKER_URI, '/')
        );
        $log .= ', ById no value';
    } else {
        $ssl_torrent = parse_url($url, PHP_URL_SCHEME).'://';
        $base_announce_url = substr($url, strlen($ssl_torrent));
        $log .= ', ById has value';
    }
    do_log("$log, ssl_torrent: $ssl_torrent, base_announce_url: $base_announce_url");
    if ($combine) {
        return $ssl_torrent.$base_announce_url;
    }

    return compact('ssl_torrent', 'base_announce_url');
}

function get_hr_ratio($uped, $downed)
{
    if ($downed > 0) {
        $ratio = $uped / $downed;
        $color = get_ratio_color($ratio);
        if ($ratio > 10000) {
            $ratio = 'Inf.';
        } else {
            $ratio = number_format($ratio, 3);
        }

        if ($color) {
            $ratio = '<font color="'.$color.'">'.$ratio.'</font>';
        }
    } elseif ($uped > 0) {
        $ratio = 'Inf.';
    } else {
        $ratio = '---';
    }

    return $ratio;
}

function get_row_count($table, $suffix = '')
{
    $rows = NexusDB::select("SELECT COUNT(*) AS c FROM $table $suffix");

    return (int) ($rows[0]['c'] ?? 0);
}

function get_user_row($id)
{
    global $Cache, $CURUSER;
    static $userRows = [];
    static $curuserRowUpdated = false;
    static $neededColumns = [
        'id', 'noad', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded', 'last_access', 'username', 'donor',
        'donoruntil', 'leechwarn', 'warned', 'title', 'downloadpos', 'parked', 'clientselect', 'showclienterror',
    ];
    if (isset($userRows[$id])) {
        return $userRows[$id];
    }
    $cacheKey = 'user_'.$id.'_content';
    $row = NexusDB::remember($cacheKey, 3600, function () use ($id, $neededColumns) {
        $user = User::query()->with([
            'wearing_medals' => function ($query) {
                $query->orderBy('user_medals.priority', 'desc')
                    ->orderBy('user_medals.id', 'desc')
                    ->limit(get_setting('system.maximum_number_of_medals_can_be_worn', 3));
            },
        ])->find($id, $neededColumns);
        if (! $user) {
            return null;
        }
        $arr = $user->toArray();
        // Rainbow ID
        $userRep = new UserRepository;
        $metas = $userRep->listMetas($id, UserMeta::META_KEY_PERSONALIZED_USERNAME);
        if ($metas->isNotEmpty()) {
            $arr['__is_rainbow'] = 1;
        } else {
            $arr['__is_rainbow'] = 0;
        }
        $arr['__is_donor'] = is_donor($arr);

        return apply_filter('user_row', $arr);
    });

    if (! $row) {
        return false;
    } else {
        return $userRows[$id] = $row;
    }
}

function get_user_class()
{
    if (IN_NEXUS) {
        global $CURUSER;

        return $CURUSER['class'] ?? '';
    }

    return auth()->user()->class;
}

function get_user_id()
{
    if (IN_NEXUS) {
        global $CURUSER;

        return $CURUSER['id'] ?? 0;
    }

    return auth()->user()->id ?? 0;
}

function get_pure_username()
{
    if (IN_NEXUS) {
        global $CURUSER;

        return $CURUSER['username'] ?? '';
    }

    return auth()->user()->username ?? '';
}

function nexus()
{
    return Nexus\Nexus::instance();
}

function site_info()
{
    $setting = Setting::get('basic');
    $siteInfo = [
        'site_name' => $setting['SITENAME'],
        'base_url' => getSchemeAndHttpHost(),
    ];

    return $siteInfo;
}

function isIPV4($ip)
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
}

function isIPV6($ip)
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
}

function add_filter($name, $function, $priority = 10, $argc = 1)
{
    global $hook;
    $hook->addFilter($name, $function, $priority, $argc);
}

function apply_filter($name, ...$args)
{
    global $hook;

    //    do_log("[APPLY_FILTER]: $name");
    return $hook->applyFilter(...func_get_args());
}

function add_action($name, $function, $priority = 10, $argc = 1)
{
    global $hook;
    $hook->addAction($name, $function, $priority, $argc);
}

function do_action($name, ...$args)
{
    global $hook;

    //    do_log("[DO_ACTION]: $name");
    return $hook->doAction(...func_get_args());
}

function isIPSeedBoxFromASN($ip, $exceptionWhenYes = false): bool
{
    $redis = NexusDB::redis();
    $key = 'nexus_asn';
    $notFoundCacheValue = '__NOT_FOUND__';
    try {
        static $reader;
        $database = nexus_env('GEOIP2_ASN_DATABASE');
        if (! file_exists($database) || ! is_readable($database)) {
            do_log("GEOIP2_ASN_DATABASE: $database not exists or not readable", 'debug');

            return false;
        }
        if (is_null($reader)) {
            $reader = new Reader($database);
        }
        $asnObj = $reader->asn($ip);
        $asn = $asnObj->autonomousSystemNumber;
        if ($asn <= 0) {
            return false;
        }
        $cacheResult = $redis->hGet($key, $asn);
        if ($cacheResult !== false) {
            if ($cacheResult === $notFoundCacheValue) {
                return false;
            } else {
                return true;
            }
        }
        $row = NexusDB::getOne('seed_box_records', "asn = $asn", 'id');
        if (! empty($row)) {
            $redis->hSet($key, $asn, $row['id']);
        } else {
            $redis->hSet($key, $asn, $notFoundCacheValue);
        }
    } catch (Throwable $throwable) {
        do_log("ip: $ip, ".$throwable->getMessage());
        $redis->hSet($key, $asn, $notFoundCacheValue);
    }
    $result = ! empty($row);
    if ($result && $exceptionWhenYes) {
        throw new SeedBoxYesException($row['id']);
    }

    return $result;
}

function isIPSeedBox($ip, $uid): bool
{
    return SeedBoxRepository::isSeedBoxFromUserRecords($uid, $ip)['result'];

    /*
    $key = "nexus_is_ip_seed_box:ip:$ip:uid:$uid";
    $cacheData = \Nexus\Database\NexusDB::cache_get($key);
    if (in_array($cacheData, [0, 1, '0', '1'], true) && !$withoutCache) {
        do_log("$key, get result from cache: $cacheData(" . gettype($cacheData) . ")");
        return (bool)$cacheData;
    }
    //check from asn
    $res = isIPSeedBoxFromASN($ip, $exceptionWhenYes);
    if (!empty($res)) {
        \Nexus\Database\NexusDB::cache_put($key, 1, 300);
        do_log("$key, get result from asn, true");
        return true;
    }

    $ipObject = \PhpIP\IP::create($ip);
    $ipNumeric = $ipObject->numeric();
    $ipVersion = $ipObject->getVersion();
    //check allow list first, not consider specific user
    $checkSeedBoxAllowedSql = sprintf(
        'select id from seed_box_records where `ip_begin_numeric` <= "%s" and `ip_end_numeric` >= "%s" and `version` = %s and `status` = %s and `is_allowed` = 1 and asn = 0 limit 1',
        $ipNumeric, $ipNumeric, $ipVersion, \App\Models\SeedBoxRecord::STATUS_ALLOWED
    );
    $res = \Nexus\Database\NexusDB::select($checkSeedBoxAllowedSql);
    if (!empty($res)) {
        \Nexus\Database\NexusDB::cache_put($key, 0, 300);
        do_log("$key, get result from database, is_allowed = 1, false");
        return false;
    }
    $checkSeedBoxAdminSql = sprintf(
        'select id from seed_box_records where `ip_begin_numeric` <= "%s" and `ip_end_numeric` >= "%s" and `type` = %s and `version` = %s and `status` = %s and `is_allowed` = 0 and asn = 0 limit 1',
        $ipNumeric, $ipNumeric, \App\Models\SeedBoxRecord::TYPE_ADMIN, $ipVersion, \App\Models\SeedBoxRecord::STATUS_ALLOWED
    );
    $res = \Nexus\Database\NexusDB::select($checkSeedBoxAdminSql);
    if (!empty($res)) {
        \Nexus\Database\NexusDB::cache_put($key, 1, 300);
        do_log("$key, get result from admin, true");
        if ($exceptionWhenYes) {
            throw new \App\Exceptions\SeedBoxYesException($res[0]['id']);
        }
        return true;
    }
    if ($uid !== null) {
        $checkSeedBoxUserSql = sprintf(
            'select id from seed_box_records where `ip_begin_numeric` <= "%s" and `ip_end_numeric` >= "%s" and `uid` = %s and `type` = %s and `version` = %s and `status` = %s and `is_allowed` = 0 and asn = 0  limit 1',
            $ipNumeric, $ipNumeric, $uid, \App\Models\SeedBoxRecord::TYPE_USER, $ipVersion, \App\Models\SeedBoxRecord::STATUS_ALLOWED
        );
        $res = \Nexus\Database\NexusDB::select($checkSeedBoxUserSql);
        if (!empty($res)) {
            \Nexus\Database\NexusDB::cache_put($key, 1, 300);
            do_log("$key, get result from user, true");
            if ($exceptionWhenYes) {
                throw new \App\Exceptions\SeedBoxYesException($res[0]['id']);
            }
            return true;
        }
    }
    \Nexus\Database\NexusDB::cache_put($key, 0, 300);
    do_log("$key, no result, false");
    return false;
    */
}

function getDataTraffic(array $torrent, array $queries, array $user, $peer, $snatch, $promotionInfo)
{
    if (! isset($user['__is_donor'])) {
        throw new InvalidArgumentException("user no '__is_donor' field");
    }
    $log = sprintf(
        'torrent: %s, owner: %s, user: %s, peerUploaded: %s, peerDownloaded: %s, queriesUploaded: %s, queriesDownloaded: %s',
        $torrent['id'], $torrent['owner'], $user['id'], $peer['uploaded'] ?? '', $peer['downloaded'] ?? '', $queries['uploaded'], $queries['downloaded']
    );
    if (! empty($peer)) {
        $realUploaded = max(bcsub($queries['uploaded'], $peer['uploaded']), 0);
        $realDownloaded = max(bcsub($queries['downloaded'], $peer['downloaded']), 0);
        $log .= ", [PEER_EXISTS], realUploaded: $realUploaded, realDownloaded: $realDownloaded, [SP_STATE]";
        $spStateGlobal = get_global_sp_state();
        $spStateNormal = Torrent::PROMOTION_NORMAL;
        if (! empty($promotionInfo) && isset($promotionInfo['__ignore_global_sp_state'])) {
            $log .= ', use promotionInfo';
            $spStateReal = $promotionInfo['sp_state'];
        } elseif ($spStateGlobal != $spStateNormal) {
            $log .= ', use global';
            $spStateReal = $spStateGlobal;
        } else {
            $log .= ', use torrent individual';
            $spStateReal = $torrent['sp_state'];
        }
        if (! isset(Torrent::$promotionTypes[$spStateReal])) {
            $log .= ", spStateReal = $spStateReal, invalid, reset to: $spStateNormal";
            $spStateReal = $spStateNormal;
        }
        $uploaderRatio = get_setting('torrent.uploaderdouble');
        $log .= ", uploaderRatio: $uploaderRatio";
        if ($torrent['owner'] == $user['id'] && $uploaderRatio != 1) {
            // uploader, use the bigger one
            $upRatio = max($uploaderRatio, Torrent::$promotionTypes[$spStateReal]['up_multiplier']);
            $log .= ", [IS_UPLOADER] && uploaderRatio != 1, upRatio: $upRatio";
        } else {
            $upRatio = Torrent::$promotionTypes[$spStateReal]['up_multiplier'];
            $log .= ", [IS_NOT_UPLOADER] || uploaderRatio == 1, upRatio: $upRatio";
        }
        /**
         * VIP do not calculate downloaded
         *
         * @since 1.7.13
         */
        if ($user['class'] == User::CLASS_VIP) {
            $downRatio = 0;
            $log .= ", [IS_VIP], downRatio: $downRatio";
        } else {
            $downRatio = Torrent::$promotionTypes[$spStateReal]['down_multiplier'];
            $log .= ", [IS_NOT_VIP], downRatio: $downRatio";
        }
    } else {
        $realUploaded = $queries['uploaded'];
        $realDownloaded = $queries['downloaded'];
        /**
         * If peer not exits, user increment = 0;
         */
        $upRatio = 0;
        $downRatio = 0;
        $log .= ", [PEER_NOT_EXISTS], realUploaded: $realUploaded, realDownloaded: $realDownloaded, upRatio: $upRatio, downRatio: $downRatio";
    }
    $uploadedIncrementForUser = $realUploaded * $upRatio;
    $downloadedIncrementForUser = $realDownloaded * $downRatio;
    $log .= ", uploadedIncrementForUser: $uploadedIncrementForUser, downloadedIncrementForUser: $downloadedIncrementForUser";

    /**
     * check seed box rule
     */
    $isSeedBoxRuleEnabled = get_setting('seed_box.enabled') == 'yes';
    $log .= ", isSeedBoxRuleEnabled: $isSeedBoxRuleEnabled, user class: {$user['class']}, __is_donor: {$user['__is_donor']}";
    if ($isSeedBoxRuleEnabled && $torrent['owner'] != $user['id'] && ! ($user['class'] >= User::CLASS_VIP || $user['__is_donor'])) {
        $isIPSeedBox = isIPSeedBox($queries['ip'], $user['id']);
        $log .= ", isIPSeedBox: $isIPSeedBox";
        if ($isIPSeedBox) {
            $isSeedBoxNoPromotion = get_setting('seed_box.no_promotion') == 'yes';
            $log .= ", isSeedBoxNoPromotion: $isSeedBoxNoPromotion";
            if ($isSeedBoxNoPromotion) {
                $uploadedIncrementForUser = $realUploaded;
                $downloadedIncrementForUser = $realDownloaded;
                $log .= ', isIPSeedBox && isSeedBoxNoPromotion, increment for user = real';
            }
            $maxUploadedTimes = get_setting('seed_box.max_uploaded');
            $maxUploadedDurationSeconds = get_setting('seed_box.max_uploaded_duration', 0) * 3600;
            $torrentTTL = time() - strtotime($torrent['added']);
            $timeRangeValid = ($maxUploadedDurationSeconds == 0) || ($torrentTTL < $maxUploadedDurationSeconds);
            $log .= ", maxUploadedTimes: $maxUploadedTimes, maxUploadedDurationSeconds: $maxUploadedDurationSeconds, timeRangeValid: $timeRangeValid";
            if ($maxUploadedTimes > 0 && $timeRangeValid) {
                $log .= ', [LIMIT_UPLOADED]';
                if (! empty($snatch) && isset($torrent['size']) && $snatch['uploaded'] >= $torrent['size'] * $maxUploadedTimes) {
                    $log .= ", snatchUploaded({$snatch['uploaded']}) >= torrentSize({$torrent['size']}) * times($maxUploadedTimes), uploadedIncrementForUser = 0";
                    $uploadedIncrementForUser = 0;
                } else {
                    $log .= ", snatchUploaded({$snatch['uploaded']}) < torrentSize({$torrent['size']}) * times($maxUploadedTimes), uploadedIncrementForUser do not change to 0";
                }
            } else {
                $log .= ', [NOT_LIMIT_UPLOADED]';
            }
        }
    }

    $result = [
        'uploaded_increment' => $realUploaded,
        'uploaded_increment_for_user' => $uploadedIncrementForUser,
        'downloaded_increment' => $realDownloaded,
        'downloaded_increment_for_user' => $downloadedIncrementForUser,
    ];
    do_log("$log, result: ".json_encode($result), 'info');

    return $result;
}

function clear_user_cache($uid, $passkey = '')
{
    do_log("clear_user_cache, uid: $uid, passkey: $passkey");
    NexusDB::cache_del("user_{$uid}_content");
    NexusDB::cache_del("user_{$uid}_roles");
    NexusDB::cache_del("announce_user_passkey_$uid"); // announce.php
    NexusDB::cache_del(Setting::DIRECT_PERMISSION_CACHE_KEY_PREFIX.$uid);
    NexusDB::cache_del("user_role_ids:$uid");
    NexusDB::cache_del("direct_permissions:$uid");
    if ($passkey) {
        NexusDB::cache_del('user_passkey_'.$passkey.'_content'); // announce.php
        NexusDB::cache_del('user_passkey_'.$passkey.'_rss'); // torrentrss.php
    }
    $userInfo = User::query()->find($uid, User::$commonFields);
    if ($userInfo) {
        fire_event('user_updated', $userInfo);
    }
}

function clear_setting_cache()
{
    do_log('clear_setting_cache');
    NexusDB::cache_del('nexus_settings_in_laravel');
    NexusDB::cache_del('nexus_settings_in_nexus');
    NexusDB::cache_del('setting_protected_forum');
    $channel = nexus_env('CHANNEL_NAME_SETTING');
    if (! empty($channel)) {
        NexusDB::redis()->publish($channel, 'update');
    }
}

/**
 * @see functions.php::get_category_row(), genrelist()
 */
function clear_category_cache()
{
    do_log('clear_category_cache');
    NexusDB::cache_del('category_content');
    $searchBoxList = SearchBox::query()->get(['id']);
    foreach ($searchBoxList as $item) {
        NexusDB::cache_del("category_list_mode_{$item->id}");
    }

}

/**
 * @see functions.php::searchbox_item_list()
 */
function clear_taxonomy_cache($table)
{
    do_log("clear_taxonomy_cache: $table");
    $list = SearchBox::query()->get(['id']);
    foreach ($list as $item) {
        NexusDB::cache_del("{$table}_list_mode_{$item->id}");
    }
    NexusDB::cache_del("{$table}_list_mode_0");
}

function clear_staff_message_cache()
{
    do_log('clear_staff_message_cache');
    MessageRepository::updateStaffMessageCountCache(false);
}

/**
 * @see functions.php::get_searchbox_value()
 */
function clear_search_box_cache()
{
    do_log('clear_search_box_cache');
    NexusDB::cache_del('search_box_content');
}

/**
 * @see functions.php::get_category_icon_row()
 */
function clear_icon_cache()
{
    do_log('clear_icon_cache');
    NexusDB::cache_del('category_icon_content');
}

function clear_inbox_count_cache($uid)
{
    do_log('clear_inbox_count_cache');
    foreach (Arr::wrap($uid) as $id) {
        NexusDB::cache_del('user_'.$id.'_inbox_count');
        NexusDB::cache_del('user_'.$id.'_unread_message_count');
    }
}

function clear_agent_allow_deny_cache()
{
    do_log('clear_agent_allow_deny_cache');
    $allowCacheKey = nexus_env('CACHE_KEY_AGENT_ALLOW', 'all_agent_allows');
    $denyCacheKey = nexus_env('CACHE_KEY_AGENT_DENY', 'all_agent_denies');
    foreach (['', ':php', ':go'] as $suffix) {
        NexusDB::cache_del($allowCacheKey.$suffix);
        NexusDB::cache_del($denyCacheKey.$suffix);
    }
}

/**
 * @see announce.php
 *
 * @return void
 */
function clear_torrent_cache($infoHash)
{
    do_log('clear_torrent_cache');
    NexusDB::cache_del('torrent_hash_'.$infoHash.'_content');
    NexusDB::cache_del("torrent_not_exists:$infoHash");
}

function user_can($permission, $fail = false, $uid = 0): bool
{
    $log = "permission: $permission, fail: $fail, user: $uid";
    static $userCanCached = [];
    static $sequence = 0;
    if ($uid == 0) {
        $uid = get_user_id();
        $log .= ", set current uid: $uid";
    }
    if ($uid <= 0) {
        if ($fail) {
            goto FAIL;
        }
        do_log("$log, unauthenticated, false");

        return false;
    }
    if (! $fail && isset($userCanCached[$permission][$uid])) {
        return $userCanCached[$permission][$uid];
    }
    $userInfo = get_user_row($uid);
    $class = $userInfo['class'];
    $log .= ", userClass: $class";
    if ($class == User::CLASS_STAFF_LEADER) {
        do_log("$log, CLASS_STAFF_LEADER, true");
        $userCanCached[$permission][$uid] = true;

        return true;
    }
    $userAllPermissions = ToolRepository::listUserAllPermissions($uid);
    $result = isset($userAllPermissions[$permission]);
    if ($sequence == 0) {
        $sequence++;
        $log .= ', userAllPermissions: '.json_encode($userAllPermissions);
    }
    $log .= ", result: $result";
    if (! $fail || $result) {
        do_log($log);
        $userCanCached[$permission][$uid] = $result;

        return $result;
    }
    FAIL:
    do_log("$log, [FAIL]");
    if (IN_NEXUS && ! IN_TRACKER) {
        global $lang_functions;
        $requireClass = get_setting("authority.$permission");
        if (isset(User::$classes[$requireClass])) {
            stderr($lang_functions['std_sorry'], $lang_functions['std_permission_denied_only'].get_user_class_name($requireClass, false, true, true).sprintf($lang_functions['std_or_above_can_view'], Setting::getSiteName()), false);
        } else {
            stderr($lang_functions['std_error'], $lang_functions['std_permission_denied']);
        }
    }
    throw new InsufficientPermissionException;
}

function assert_has_permission(bool $permissionCheckResult): void
{
    if (! $permissionCheckResult) {
        throw new InsufficientPermissionException;
    }
}

function is_donor(array $userInfo): bool
{
    return $userInfo['donor'] == 'yes' && ($userInfo['donoruntil'] === null || $userInfo['donoruntil'] == '0000-00-00 00:00:00' || $userInfo['donoruntil'] >= date('Y-m-d H:i:s'));
}

/**
 * @deprecated
 *
 * @return false|int|mixed|string|null
 *
 * @throws NexusException
 *
 * @see download.php
 */
function get_passkey_by_authkey($authkey)
{
    return NexusDB::remember("authkey2passkey:$authkey", 3600 * 24, function () use ($authkey) {
        $arr = explode('|', $authkey);
        if (count($arr) != 3) {
            throw new InvalidArgumentException("Invalid authkey: $authkey, format error");
        }
        $uid = $arr[1];
        $torrentRep = new TorrentRepository;
        $decrypted = $torrentRep->checkTrackerReportAuthKey($authkey);
        if (empty($decrypted)) {
            throw new InvalidArgumentException("Invalid authkey: $authkey");
        }
        $userInfo = NexusDB::remember("announce_user_passkey_$uid", 3600, function () use ($uid) {
            return User::query()->where('id', $uid)->first(['id', 'passkey']);
        });

        return $userInfo->passkey;
    });
}

function executeCommand($command, $format = 'string', $artisan = false, $exception = true): string|array
{
    $append = ' 2>&1';
    if (! str_ends_with($command, $append)) {
        $command .= $append;
    }
    if ($artisan) {
        $phpPath = nexus_env('PHP_PATH') ?: 'php';
        $webRoot = rtrim(ROOT_PATH, '/');
        $command = "$phpPath $webRoot/artisan $command";
    }
    do_log("command: $command");
    $result = exec($command, $output, $result_code);
    $outputString = implode("\n", $output);
    $log = sprintf('result_code: %s, result: %s, output: %s', $result_code, $result, $outputString);
    if ($result_code != 0) {
        do_log($log, 'error');
        if ($exception) {
            throw new RuntimeException($outputString);
        }
    } else {
        do_log($log);
    }

    return $format == 'string' ? $outputString : $output;
}

function has_role_work_seeding($uid)
{
    $result = apply_filter('user_has_role_work_seeding', false, $uid);
    do_log("uid: $uid, result: $result");

    return $result;
}

function filter_src($src)
{
    $path = parse_url($src, PHP_URL_PATH);
    if (empty($path)) {
        return $src;
    }
    $host = parse_url($src, PHP_URL_HOST);
    $currentHost = parse_url(getSchemeAndHttpHost(), PHP_URL_HOST);
    if (! empty($host) && $host != $currentHost) {
        return $src;
    }
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $guessScriptFilename = sprintf('%s/%s', $_SERVER['DOCUMENT_ROOT'], trim($path, '/'));
        if (! file_exists($guessScriptFilename)) {
            return $src;
        }
    }
    // only allow these
    $imgExtensions = implode('|', Attachment::IMG_EXTENSIONS);
    $allowSuffixPattern = "/\.($imgExtensions)/i";
    if (preg_match($allowSuffixPattern, $path)) {
        return $src;
    }
    $allowScriptPattern = "/(forums|details|offers)\.php/i";
    if (preg_match($allowScriptPattern, $path)) {
        return $src;
    }
    // log danger, deny directly
    $dangerScriptsPattern = "/(logout|login|ajax|announce|scrape|adduser|modtask|freeleech|take.*)\.php/i";
    if (preg_match($dangerScriptsPattern, $path)) {
        $msg = sprintf("[DANGER_URL]: $src [%s]", nexus()->getRequestId());
        do_log($msg, 'alert');
        write_log($msg, 'mod');
    }
    do_log("[NOT_ALLOW_SRC]: $src with path: $path");

    return '';
}

// here must retrieve the real time info, no cache!!!
function get_snatch_info($torrentId, $userId)
{
    $row = NexusDB::table('snatched')
        ->where('torrentid', (int) $torrentId)
        ->where('userid', (int) $userId)
        ->orderByDesc('id')
        ->limit(1)
        ->first();

    return $row ? (array) $row : false;
}

/**
 * 完整的 Laravel 事件, 在 php 端有监听者的需要触发. 同样会执行 publish_model_event()
 */
function fire_event(string $name, Model $model, ?Model $oldModel = null): void
{
    if (! isset(ModelEventEnum::$eventMaps[$name])) {
        throw new InvalidArgumentException("Event $name is not a valid event enumeration");
    }
    if (IN_NEXUS) {
        $prefix = 'fire_event:';
        $idKey = $prefix.Str::random();
        $idKeyOld = '';
        NexusDB::cache_put($idKey, serialize($model->toArray()), 3600 * 24 * 30);
        if ($oldModel) {
            $idKeyOld = $prefix.Str::random();
            NexusDB::cache_put($idKeyOld, serialize($oldModel->toArray()), 3600 * 24 * 30);
        }
        //        executeCommand("event:fire --name=$name --idKey=$idKey --idKeyOld=$idKeyOld", "string", true, false);
        Nexus\Nexus::dispatchQueueJob(new FireEvent($name, $idKey, $idKeyOld));
        do_log("success fire_event in nexus, name: $name, idKey: $idKey, idKeyOld: $idKeyOld");
    } else {
        $eventClass = ModelEventEnum::$eventMaps[$name]['event'];
        if (str_ends_with($name, '_deleted')) {
            // if deleted from database, can not pass model instance, use array
            $params = [$model->toArray()];
            if ($oldModel) {
                $params[] = $oldModel->toArray();
            }
        } else {
            $params = [$model];
            if ($oldModel) {
                $params[] = $oldModel;
            }
        }
        call_user_func_array([$eventClass, 'dispatch'], $params);
        publish_model_event($name, $model->id, $model->toJson());
        do_log("success fire_event in laravel, name: $name, id: $model->id, oldId: ".($oldModel ? $oldModel->id : ''));
    }
}

/**
 * 仅仅是往 redis 发布事件, php 端无监听者仅在其他平台有需要的触发这个即可, 较轻量
 */
function publish_model_event(string $event, int $id, string $json = ''): void
{
    $channel = nexus_env('CHANNEL_NAME_MODEL_EVENT');
    if (! empty($channel)) {
        NexusDB::redis()->publish($channel, json_encode(['event' => $event, 'id' => $id, 'json' => $json]));
    } else {
        do_log("event: $event, id: $id, channel: $channel, channel is empty!", 'error');
    }
}

function convertNamespaceToSnake(string $str): string
{
    return str_replace(['\\', '::'], ['_', '.'], $str);
}

function get_user_locale(int $uid): string
{
    $sql = "select language.site_lang_folder from users inner join language on users.lang = language.id where users.id = $uid limit 1";
    $result = NexusDB::select($sql);
    if (empty($result) || empty($result[0]['site_lang_folder'])) {
        return 'en';
    }

    return Locale::$languageMaps[$result[0]['site_lang_folder']] ?? $result[0]['site_lang_folder'];
}

function send_admin_success_notification(string $msg = ''): void
{
    Notification::make()->success()->title($msg ?: 'Success!')->send();
}

function send_admin_fail_notification(string $msg = ''): void
{
    Notification::make()->danger()->title($msg ?: 'Fail!')->send();
}

function ability(RoutePermissionEnum $permission): string
{
    return sprintf('ability:%s', $permission->value);
}

function get_challenge_key(string $challenge): string
{
    return 'challenge:'.$challenge;
}

function get_user_from_cookie(array $cookie, $isArray = true): array|User|null
{
    $log = 'cookie: '.json_encode($cookie);
    $result = get_user_id_and_signature_from_cookie($cookie);
    if (empty($result)) {
        return null;
    }
    $id = $result['user_id'];
    $tokenJson = $result['token_json'];
    $signature = $result['signature'];
    $log .= ", uid = $id";
    $isAjax = nexus()->isAjax();
    $selfEnableBonus = Setting::getSelfEnableBonus();
    // only in nexus web can self-enable, and require bonus > 0
    $shouldIgnoreEnabled = IN_NEXUS && ! $isAjax && $selfEnableBonus > 0;
    if ($isArray) {
        $userQuery = NexusDB::table('users')
            ->where('id', (int) $id)
            ->where('status', 'confirmed');
        if (! $shouldIgnoreEnabled) {
            $userQuery->where('enabled', 'yes');
        }
        $row = $userQuery->first();
        $row = $row ? (array) $row : null;
        if (! $row) {
            do_log("$log, user not exists");

            return null;
        }
        $authKey = $row['auth_key'];
        unset($row['auth_key'], $row['passhash']);
    } else {
        $row = User::query()->find($id);
        if (! $row) {
            do_log("$log, user not exists");

            return null;
        }
        $checkFields = ['status'];
        if (! $shouldIgnoreEnabled) {
            $checkFields[] = 'enabled';
        }
        $row->checkIsNormal($checkFields);
        $authKey = $row->auth_key;
    }
    $expectedSignature = hash_hmac('sha256', $tokenJson, $authKey);
    if (! hash_equals($expectedSignature, $signature)) {
        do_log("$log, !hash_equals, expectedSignature: $expectedSignature, actualSignature: $signature");

        return null;
    }

    return $row;
}

function get_user_id_and_signature_from_cookie(array $cookie): ?array
{
    $log = 'cookie: '.json_encode($cookie);
    if (empty($cookie['c_secure_pass'])) {
        do_log("$log, param not enough");

        return null;
    }
    $base64Decoded = base64_decode($cookie['c_secure_pass']);
    if (empty($base64Decoded)) {
        do_log("$log, invalid c_secure_pass");

        return null;
    }
    $log .= ', base64 decoded: '.$base64Decoded;
    $tokenJsonAndSignature = explode('.', $base64Decoded);
    if (count($tokenJsonAndSignature) != 2) {
        do_log("$log, invalid c_secure_pass base64_decoded");

        return null;
    }
    $tokenJson = $tokenJsonAndSignature[0];
    $signature = $tokenJsonAndSignature[1];
    if (empty($tokenJson) || empty($signature)) {
        do_log("$log, no tokenJson or signature");

        return null;
    }
    $tokenData = json_decode($tokenJson, true);
    if (! isset($tokenData['user_id'])) {
        do_log("$log, no user_id");

        return null;
    }
    if (! isset($tokenData['expires']) || $tokenData['expires'] < time()) {
        do_log("$log, signature expired");

        return null;
    }

    return [
        'user_id' => $tokenData['user_id'],
        'token_json' => $tokenJson,
        'signature' => $signature,
    ];
}

function render_password_hash_js(string $formId, string $passwordOriginalClass, string $passwordHashedName, bool $passwordRequired, string $passwordConfirmClass = 'password_confirmation', string $usernameName = 'username'): void
{
    $tipTooShort = nexus_trans('signup.password_too_short');
    $tipTooLong = nexus_trans('signup.password_too_long');
    $tipEqualUsername = nexus_trans('signup.password_equals_username');
    $tipNotMatch = nexus_trans('signup.passwords_unmatched');
    $passwordValidateJS = '';
    if ($passwordRequired) {
        $passwordValidateJS = <<<JS
if (password.length < 6) {
    layer.alert("$tipTooShort")
    return
}
if (password.length > 40) {
    layer.alert("$tipTooLong")
    return
}
JS;
    }
    $formVar = 'jqForm'.md5($formId);
    $js = <<<JS
var $formVar = jQuery("#{$formId}");
$formVar.on("click", "input[type=button]", function() {
    let jqUsername = $formVar.find("[name={$usernameName}]")
    let jqPassword = $formVar.find(".{$passwordOriginalClass}")
    let jqPasswordConfirm = $formVar.find(".{$passwordConfirmClass}")
    let password = jqPassword.val()
    $passwordValidateJS
    if (jqUsername.length > 0 && jqUsername.val() === password) {
        layer.alert("$tipEqualUsername")
        return
    }
    if (jqPasswordConfirm.length > 0 && password !== jqPasswordConfirm.val()) {
        layer.alert("$tipNotMatch")
        return
    }
    if (password !== "") {
        const passwordHashed = sha256(password)
        $formVar.find("input[name={$passwordHashedName}]").val(passwordHashed)
        $formVar.submit()
    } else {
        $formVar.submit()
    }
})
JS;
    Nexus\Nexus::js('js/crypto-js.js', 'footer', true);
    Nexus\Nexus::js($js, 'footer', false);
}

function render_password_challenge_js(string $formId, string $usernameName, string $passwordOriginalClass): void
{
    $formVar = 'jqForm'.md5($formId);
    $js = <<<JS
var $formVar = jQuery("#{$formId}");
$formVar.on("click", "input[type=button]", function() {
    let useChallengeResponseAuthentication = $formVar.find("input[name=response]").length > 0
    if (!useChallengeResponseAuthentication) {
        return $formVar.submit()
    }
    let jqUsername = $formVar.find("[name={$usernameName}]")
    let jqPassword = $formVar.find(".{$passwordOriginalClass}")
    let username = jqUsername.val()
    let password = jqPassword.val()
    login(username, password, $formVar)
})
async function login(username, password, jqForm) {
    try {
        jQuery('body').loading({stoppable: false});
        const challengeResponse = await fetch('/api/challenge', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username: username })
        });
        jQuery('body').loading('stop');

        const challengeData = await challengeResponse.json();
        if (challengeData.ret !== 0) {
            layer.alert(challengeData.msg)
            return
        }

        const clientHashedPassword = sha256(password);

        const serverSideHash = sha256(challengeData.data.secret + clientHashedPassword);

        const clientResponse = hmacSha256(challengeData.data.challenge, serverSideHash);
        jqForm.find("input[name=response]").val(clientResponse)
        jqForm.submit()
    } catch (error) {
        console.error(error);
        layer.alert(error.toString())
    }
}
JS;
    Nexus\Nexus::js('vendor/jquery-loading/jquery.loading.min.js', 'footer', true);
    Nexus\Nexus::js('js/crypto-js.js', 'footer', true);
    Nexus\Nexus::js($js, 'footer', false);
}

function nexus_escape($data): array|string
{
    if (is_array($data)) {
        return array_map('nexus_escape', $data);
    }

    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function is_fpm_mode(): bool
{
    return php_sapi_name() === 'fpm-fcgi';
}
