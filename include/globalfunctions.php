<?php

function get_global_sp_state()
{
	static $global_promotion_state;
	$cacheKey = \App\Models\Setting::TORRENT_GLOBAL_STATE_CACHE_KEY;
	if (!$global_promotion_state) {
        $row = \Nexus\Database\NexusDB::remember($cacheKey, 600, function () use ($cacheKey) {
            return \Nexus\Database\NexusDB::getOne('torrents_state', 1);
        });
        if (is_array($row) && isset($row['deadline']) && $row['deadline'] < date('Y-m-d H:i:s')) {
            //expired
            $global_promotion_state = \App\Models\Torrent::PROMOTION_NORMAL;
        } elseif (is_array($row) && isset($row['begin']) && $row['begin'] > date('Y-m-d H:i:s')) {
            //Not begin
            $global_promotion_state = \App\Models\Torrent::PROMOTION_NORMAL;
        } elseif (is_array($row)) {
            $global_promotion_state = $row["global_sp_state"];
        } else {
            $global_promotion_state = $row;
        }
	}
	return $global_promotion_state;
}

// IP Validation
function validip($ip)
{
	if (!ip2long($ip)) //IPv6
		return true;
	if (!empty($ip) && $ip == long2ip(ip2long($ip)))
	{
		// reserved IANA IPv4 addresses
		// http://www.iana.org/assignments/ipv4-address-space
		$reserved_ips = array (
		array('192.0.2.0','192.0.2.255'),
		array('192.168.0.0','192.168.255.255'),
		array('255.255.255.0','255.255.255.255')
		);

		foreach ($reserved_ips as $r)
		{
			$min = ip2long($r[0]);
			$max = ip2long($r[1]);
			if ((ip2long($ip) >= $min) && (ip2long($ip) <= $max)) return false;
		}
		return true;
	}
	else return false;
}

function getip() {
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

	return $ip;
}

function sql_query($query)
{
	$begin = microtime(true);
	global $query_name;
	$result = mysql_query($query);
	$end = microtime(true);
	$query_name[] = [
		'query' => $query,
		'time' => sprintf('%.3f ms', ($end - $begin) * 1000),
	];
	return $result;
}

function sqlesc($value) {
	if (is_null($value)) {
		return 'null';
	}
	$value = "'" . mysql_real_escape_string($value) . "'";
	return $value;
}

function hash_pad($hash) {
    return str_pad($hash, 20);
}

function hash_where($name, $hash) {
//	$shhash = preg_replace('/ *$/s', "", $hash);
//	return "($name = " . sqlesc($hash) . " OR $name = " . sqlesc($shhash) . ")";
//	return sprintf("$name in (%s, %s)", sqlesc($hash), sqlesc($shhash));
    return "$name = " . sqlesc($hash);
}

//no need any more...
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
	//do not access db for speed up, or for flexibility
	return array("en", "chs", "cht", "ko", "ja");
}

function printLine($line, $exist = false)
{
	echo "[" . date('Y-m-d H:i:s') . "] $line<br />";
	if ($exist) {
		exit(0);
	}
}

function nexus_dd($vars)
{
    echo '<pre>';
    array_map(function ($var) {
        var_dump($var);
    }, func_get_args());
    echo '</pre>';
    exit(0);
}

/**
 * write log, use in both pure nexus and inside laravel
 *
 * @param $log
 * @param string $level
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
        $fd = fopen(sys_get_temp_dir() . '/nexus.log', 'a');
	}
	$uid = 0;
    if (IN_NEXUS) {
        global $CURUSER;
        $user = $CURUSER;
        $uid = $user['id'] ?? 0;
        $passkey = $user['passkey'] ?? $_REQUEST['passkey'] ?? $_REQUEST['authkey'] ?? '';
    } else {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $uid = $user->id ?? 0;
            $passkey = $user->passkey ?? request('passkey', request('authkey', ''));
        } catch (\Throwable $exception) {
            $passkey = "!IN_NEXUS:" . $exception->getMessage();
        }
    }
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $content = sprintf(
        "[%s] [%s] [%s] [%s] [%s] [%s] %s.%s %s:%s %s%s%s %s%s",
        date('Y-m-d H:i:s'),
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
        echo $content . PHP_EOL;
    }
    if (nexus()) {
        nexus()->incrementLogSequence();
    }
}

function getLogFile($append = '')
{
    static $logFiles = [];
    if (isset($logFiles[$append])) {
        return $logFiles[$append];
    }
    $config = nexus_config('nexus');
    $path = getenv('NEXUS_LOG_DIR', true);
    $fromEnv = true;
    if ($path === false) {
        $fromEnv = false;
        $path = sys_get_temp_dir();
    }
    $logFile = rtrim($path, '/') . '/nexus.log';
    if (!$fromEnv && !empty($config['log_file'])) {
        $logFile = $config['log_file'];
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
    $logFile = sprintf('%s-%s%s', $name, date('Y-m-d'), $suffix);
    return $logFiles[$append] = $logFile;

}

function nexus_config($key, $default = null)
{
    if (!IN_NEXUS) {
        return config($key, $default);
    }
    static $configs;
    if (is_null($configs)) {
        //get all configuration from config file
//		$files = glob(ROOT_PATH . 'config/*.php');
        $files = [
            ROOT_PATH . 'config/nexus.php',
            ROOT_PATH . 'config/emoji.php',
        ];
        foreach ($files as $file) {
            $basename = basename($file);
            if ($basename == 'allconfig.php') {
                //exclude the NexusPHP default config file
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
 * @param null $name
 * @param null $default
 * @return mixed
 */
function get_setting($name = null, $default = null): mixed
{
	static $settings;
	if (is_null($settings)) {
        $settings = \Nexus\Database\NexusDB::remember("nexus_settings_in_nexus", 600, function () {
            //get all settings from database
            return \App\Models\Setting::getFromDb();
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
        $final = \App\Models\Setting::getFromDb();
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
        $envFile = dirname(__DIR__) . '/.env';
        $env = readEnvFile($envFile);
    }
    if (is_null($key)) {
        return $env;
    }
    return $env[$key] ?? $default;
}

function readEnvFile($envFile)
{
    if (!file_exists($envFile)) {
        if (php_sapi_name() == 'cli') {
            return [];
        }
        throw new \RuntimeException("env file : $envFile is not exists in the root path.");
    }
    $env = [];
    $fp = fopen($envFile, 'r');
    if ($fp === false) {
        throw new \RuntimeException(".env file: $envFile is not readable.");
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
	if (in_array(mb_substr($value, -1, null,'utf-8'), $toStrip)) {
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
 * @param $array
 * @param $key
 * @param null $default
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
    $schema = nexus()->getRequestSchema();
    return $schema == 'https';
}


function getSchemeAndHttpHost()
{
    global $BASEURL;
    if (isRunningInConsole()) {
        return $BASEURL;
    }
    $isHttps = isHttps();
    $protocol = $isHttps ? 'https' : 'http';
    $host = nexus()->getRequestHost();
    return "$protocol://" . $host;
}

function getBaseUrl()
{
    $url = getSchemeAndHttpHost();
    $requestUri = $_SERVER['REQUEST_URI'];
    $pos = strpos($requestUri, '?');
    if ($pos !== false) {
        $url .= substr($requestUri, 0, $pos);
    } else {
        $url .= $requestUri;
    }
    return trim($url, '/');
}


function nexus_json_encode($data)
{
    return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

function api(...$args)
{
    if (func_num_args() < 3) {
        //参数少于3个时，默认为错误状态。
        $ret = -1;
        $msg = isset($args[0]) ? $args[0] : 'ERROR';
        $data = isset($args[1]) ? $args[1] : [];
    } else {
        $ret = $args[0];
        $msg = $args[1];
        $data = $args[2];
    }
    if ($data instanceof \Illuminate\Http\Resources\Json\ResourceCollection || $data instanceof \Illuminate\Http\Resources\Json\JsonResource) {
        $data = $data->response()->getData(true);
        if (isset($data['data']) && count($data) == 1) {
            //单纯的集合，无分页等其数据
            $data = $data['data'];
        }
    }
    $time = (float)number_format(microtime(true) - nexus()->getStartTimestamp(), 3);
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
        $resultKey => (int)$ret,
        $msgKey => (string)$msg,
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

function last_query($all = false)
{
    static $connection, $pdo;
    if (is_null($connection)) {
        if (IN_NEXUS) {
            $connection = \Illuminate\Database\Capsule\Manager::connection(\Nexus\Database\NexusDB::ELOQUENT_CONNECTION_NAME);
        } else {
            $connection = \Illuminate\Support\Facades\DB::connection(config('database.default'));
        }
        $pdo = $connection->getPdo();
    }
    $queries = $connection->getQueryLog();
    if (!$all) {
        $queries = [last($queries)];
    }
    $queryFormatted = [];
    foreach ($queries as $query) {
        $sqlWithPlaceholders = str_replace(['%', '?'], ['%%', '%s'], $query['query']);
        $bindings = $query['bindings'];
        $realSql = $sqlWithPlaceholders;
        if (count($bindings) > 0) {
            $realSql = vsprintf($sqlWithPlaceholders, array_map([$pdo, 'quote'], $bindings));
        }
        $queryFormatted[] = $realSql;
    }
    if ($all) {
        return nexus_json_encode($queryFormatted);
    }
    return $queryFormatted[0];
}

function format_datetime($datetime, $format = 'Y-m-d H:i')
{
    if (empty($datetime)) {
        return '';
    }
    try {
        $carbonTime = \Carbon\Carbon::parse($datetime);
        return $carbonTime->format($format);
    } catch (\Exception) {
        do_log("Invalid datetime: $datetime", 'error');
        return $datetime;
    }
}

function nexus_trans($key, $replace = [], $locale = null)
{
    return \Nexus\Nexus::trans($key, $replace, $locale);
}

function isRunningInConsole(): bool
{
    return !RUNNING_IN_OCTANE && php_sapi_name() == 'cli';
}

function isRunningOnWindows(): bool
{
    return !RUNNING_IN_OCTANE && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

function command_exists($command): bool
{
    return !(trim(exec("command -v $command")) == '');
}

function get_tracker_schema_and_host($combine = false): array|string
{
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
    return compact('ssl_torrent', 'base_announce_url');
}


function get_hr_ratio($uped, $downed)
{
    if ($downed > 0) {
        $ratio = $uped / $downed;
        $color = get_ratio_color($ratio);
        if ($ratio > 10000) $ratio = 'Inf.';
        else
            $ratio = number_format($ratio, 3);

        if ($color)
            $ratio = "<font color=\"" . $color . "\">" . $ratio . "</font>";
    } elseif ($uped > 0)
        $ratio = 'Inf.';
    else
        $ratio = "---";

    return $ratio;
}

function get_row_count($table, $suffix = "")
{
    $r = sql_query("SELECT COUNT(*) FROM $table $suffix") or sqlerr(__FILE__, __LINE__);
    $a = mysql_fetch_row($r);
    return $a[0];
}

function get_user_row($id)
{
    global $Cache, $CURUSER;
    static $userRows = [];
    static $curuserRowUpdated = false;
    static $neededColumns = array(
        'id', 'noad', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded', 'last_access', 'username', 'donor',
        'donoruntil', 'leechwarn', 'warned', 'title', 'downloadpos', 'parked', 'clientselect', 'showclienterror',
    );
    if (isset($userRows[$id])) return $userRows[$id];
    $cacheKey = 'user_'.$id.'_content';
    $row = \Nexus\Database\NexusDB::remember($cacheKey, 3600, function () use ($id, $neededColumns) {
        $user = \App\Models\User::query()->with(['wearing_medals'])->find($id, $neededColumns);
        if (!$user) {
            return null;
        }
        $arr = $user->toArray();
        //Rainbow ID
        $userRep = new \App\Repositories\UserRepository();
        $metas = $userRep->listMetas($id, \App\Models\UserMeta::META_KEY_PERSONALIZED_USERNAME);
        if ($metas->isNotEmpty()) {
            $arr['__is_rainbow'] = 1;
        } else {
            $arr['__is_rainbow'] = 0;
        }
        $arr['__is_donor'] = is_donor($arr);
        return apply_filter("user_row", $arr);
    });

//	if ($CURUSER && $id == $CURUSER['id']) {
//		$row = array();
//		foreach($neededColumns as $column) {
//			$row[$column] = $CURUSER[$column];
//		}
//		if (!$curuserRowUpdated) {
//			$Cache->cache_value('user_'.$CURUSER['id'].'_content', $row, 900);
//			$curuserRowUpdated = true;
//		}
//	} elseif (!$row = $Cache->get_value('user_'.$id.'_content')){
//		$res = sql_query("SELECT ".implode(',', $neededColumns)." FROM users WHERE id = ".sqlesc($id)) or sqlerr(__FILE__,__LINE__);
//		$row = mysql_fetch_array($res);
//		$Cache->cache_value('user_'.$id.'_content', $row, 900);
//	}

    if (!$row)
        return false;
    else return $userRows[$id] = $row;
}

function get_user_class()
{
    if (IN_NEXUS) {
        global $CURUSER;
        return $CURUSER["class"] ?? '';
    }
    return auth()->user()->class;
}

function get_user_id()
{
    if (IN_NEXUS) {
        global $CURUSER;
        return $CURUSER["id"] ?? 0;
    }
    return auth()->user()->id ?? 0;
}

function nexus()
{
    return \Nexus\Nexus::instance();
}

function site_info()
{
    $setting = \App\Models\Setting::get('basic');
    $siteInfo = [
        'site_name' => $setting['SITENAME'],
        'base_url' => getSchemeAndHttpHost(),
    ];
    return $siteInfo;
}

function isIPV4 ($ip)
{
    return filter_var($ip,FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
}

function isIPV6 ($ip)
{
    return filter_var($ip,FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
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

function isIPSeedBox($ip, $uid, $withoutCache = false): bool
{
    $key = "nexus_is_ip_seed_box:ip:$ip:uid:$uid";
    $cacheData = \Nexus\Database\NexusDB::cache_get($key);
    if (in_array($cacheData, [0, 1, '0', '1'], true) && !$withoutCache) {
        do_log("$key, get result from cache: $cacheData(" . gettype($cacheData) . ")");
        return (bool)$cacheData;
    }
    $ipObject = \PhpIP\IP::create($ip);
    $ipNumeric = $ipObject->numeric();
    $ipVersion = $ipObject->getVersion();
    //check allow list first, not consider specific user
    $checkSeedBoxAllowedSql = sprintf(
        'select id from seed_box_records where `ip_begin_numeric` <= "%s" and `ip_end_numeric` >= "%s" and `version` = %s and `status` = %s and `is_allowed` = 1 limit 1',
        $ipNumeric, $ipNumeric, $ipVersion, \App\Models\SeedBoxRecord::STATUS_ALLOWED
    );
    $res = \Nexus\Database\NexusDB::select($checkSeedBoxAllowedSql);
    if (!empty($res)) {
        \Nexus\Database\NexusDB::cache_put($key, 1, 300);
        do_log("$key, get result from database, is_allowed = 1, false");
        return false;
    }
    $checkSeedBoxAdminSql = sprintf(
        'select id from seed_box_records where `ip_begin_numeric` <= "%s" and `ip_end_numeric` >= "%s" and `type` = %s and `version` = %s and `status` = %s and `is_allowed` = 0 limit 1',
        $ipNumeric, $ipNumeric, \App\Models\SeedBoxRecord::TYPE_ADMIN, $ipVersion, \App\Models\SeedBoxRecord::STATUS_ALLOWED
    );
    $res = \Nexus\Database\NexusDB::select($checkSeedBoxAdminSql);
    if (!empty($res)) {
        \Nexus\Database\NexusDB::cache_put($key, 1, 300);
        do_log("$key, get result from admin, true");
        return true;
    }
    if ($uid !== null) {
        $checkSeedBoxUserSql = sprintf(
            'select id from seed_box_records where `ip_begin_numeric` <= "%s" and `ip_end_numeric` >= "%s" and `uid` = %s and `type` = %s and `version` = %s and `status` = %s and `is_allowed` = 0  limit 1',
            $ipNumeric, $ipNumeric, $uid, \App\Models\SeedBoxRecord::TYPE_USER, $ipVersion, \App\Models\SeedBoxRecord::STATUS_ALLOWED
        );
        $res = \Nexus\Database\NexusDB::select($checkSeedBoxUserSql);
        if (!empty($res)) {
            \Nexus\Database\NexusDB::cache_put($key, 1, 300);
            do_log("$key, get result from user, true");
            return true;
        }
    }
    \Nexus\Database\NexusDB::cache_put($key, 0, 300);
    do_log("$key, no result, false");
    return false;
}

function getDataTraffic(array $torrent, array $queries, array $user, $peer, $snatch, $promotionInfo)
{
    if (!isset($user['__is_donor'])) {
        throw new \InvalidArgumentException("user no '__is_donor' field");
    }
    $log = sprintf(
        "torrent: %s, owner: %s, user: %s, peerUploaded: %s, peerDownloaded: %s, queriesUploaded: %s, queriesDownloaded: %s",
        $torrent['id'], $torrent['owner'], $user['id'], $peer['uploaded'] ?? '', $peer['downloaded'] ?? '', $queries['uploaded'], $queries['downloaded']
    );
    if (!empty($peer)) {
        $realUploaded = max(bcsub($queries['uploaded'], $peer['uploaded']), 0);
        $realDownloaded = max(bcsub($queries['downloaded'], $peer['downloaded']), 0);
        $log .= ", [PEER_EXISTS], realUploaded: $realUploaded, realDownloaded: $realDownloaded, [SP_STATE]";
        $spStateGlobal = get_global_sp_state();
        $spStateNormal = \App\Models\Torrent::PROMOTION_NORMAL;
        if (!empty($promotionInfo) && isset($promotionInfo['__ignore_global_sp_state'])) {
            $log .= ', use promotionInfo';
            $spStateReal = $promotionInfo['sp_state'];
        } elseif ($spStateGlobal != $spStateNormal) {
            $log .= ", use global";
            $spStateReal = $spStateGlobal;
        } else {
            $log .= ", use torrent individual";
            $spStateReal = $torrent['sp_state'];
        }
        if (!isset(\App\Models\Torrent::$promotionTypes[$spStateReal])) {
            $log .= ", spStateReal = $spStateReal, invalid, reset to: $spStateNormal";
            $spStateReal = $spStateNormal;
        }
        $uploaderRatio = get_setting('torrent.uploaderdouble');
        $log .= ", uploaderRatio: $uploaderRatio";
        if ($torrent['owner'] == $user['id']) {
            //uploader, use the bigger one
            $upRatio = max($uploaderRatio, \App\Models\Torrent::$promotionTypes[$spStateReal]['up_multiplier']);
            $log .= ", [IS_UPLOADER], upRatio: $upRatio";
        } else {
            $upRatio = \App\Models\Torrent::$promotionTypes[$spStateReal]['up_multiplier'];
            $log .= ", [IS_NOT_UPLOADER], upRatio: $upRatio";
        }
        /**
         * VIP do not calculate downloaded
         * @since 1.7.13
         */
        if ($user['class'] == \App\Models\User::CLASS_VIP) {
            $downRatio = 0;
            $log .= ", [IS_VIP], downRatio: $downRatio";
        } else {
            $downRatio = \App\Models\Torrent::$promotionTypes[$spStateReal]['down_multiplier'];
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
    if ($isSeedBoxRuleEnabled && $torrent['owner'] != $user['id'] && !($user['class'] >= \App\Models\User::CLASS_VIP || $user['__is_donor'])) {
        $isIPSeedBox = isIPSeedBox($queries['ip'], $user['id']);
        $log .= ", isIPSeedBox: $isIPSeedBox";
        if ($isIPSeedBox) {
            $isSeedBoxNoPromotion = get_setting('seed_box.no_promotion') == 'yes';
            $log .= ", isSeedBoxNoPromotion: $isSeedBoxNoPromotion";
            if ($isSeedBoxNoPromotion) {
                $uploadedIncrementForUser = $realUploaded;
                $downloadedIncrementForUser = $realDownloaded;
                $log .= ", isIPSeedBox && isSeedBoxNoPromotion, increment for user = real";
            }
            $maxUploadedTimes = get_setting('seed_box.max_uploaded');
            $maxUploadedDurationSeconds = get_setting('seed_box.max_uploaded_duration', 0) * 3600;
            $torrentTTL = time() - strtotime($torrent['added']);
            $timeRangeValid = ($maxUploadedDurationSeconds == 0) || ($torrentTTL < $maxUploadedDurationSeconds);
            $log .= ", maxUploadedTimes: $maxUploadedTimes, maxUploadedDurationSeconds: $maxUploadedDurationSeconds, timeRangeValid: $timeRangeValid";
            if ($maxUploadedTimes > 0 && $timeRangeValid) {
                $log .= ", [LIMIT_UPLOADED]";
                if (!empty($snatch) && isset($torrent['size']) && $snatch['uploaded'] >= $torrent['size'] * $maxUploadedTimes) {
                    $log .= ", snatchUploaded({$snatch['uploaded']}) >= torrentSize({$torrent['size']}) * times($maxUploadedTimes), uploadedIncrementForUser = 0";
                    $uploadedIncrementForUser = 0;
                } else {
                    $log .= ", snatchUploaded({$snatch['uploaded']}) < torrentSize({$torrent['size']}) * times($maxUploadedTimes), uploadedIncrementForUser do not change to 0";
                }
            } else {
                $log .= ", [NOT_LIMIT_UPLOADED]";
            }
        }
    }

    $result = [
        'uploaded_increment' => $realUploaded,
        'uploaded_increment_for_user' => $uploadedIncrementForUser,
        'downloaded_increment' => $realDownloaded,
        'downloaded_increment_for_user' => $downloadedIncrementForUser,
    ];
    do_log("$log, result: " . json_encode($result), 'info');
    return $result;
}

function clear_user_cache($uid, $passkey = '')
{
    do_log("clear_user_cache, uid: $uid, passkey: $passkey");
    \Nexus\Database\NexusDB::cache_del("user_{$uid}_content");
    \Nexus\Database\NexusDB::cache_del("user_{$uid}_roles");
    \Nexus\Database\NexusDB::cache_del("announce_user_passkey_$uid");//announce.php
    \Nexus\Database\NexusDB::cache_del(\App\Models\Setting::DIRECT_PERMISSION_CACHE_KEY_PREFIX . $uid);
    \Nexus\Database\NexusDB::cache_del("user_role_ids:$uid");
    \Nexus\Database\NexusDB::cache_del("direct_permissions:$uid");
    if ($passkey) {
        \Nexus\Database\NexusDB::cache_del('user_passkey_'.$passkey.'_content');//announce.php
    }
}

function clear_setting_cache()
{
    do_log("clear_setting_cache");
    \Nexus\Database\NexusDB::cache_del('nexus_settings_in_laravel');
    \Nexus\Database\NexusDB::cache_del('nexus_settings_in_nexus');
}

/**
 * @see functions.php::get_category_row(), genrelist()
 */
function clear_category_cache()
{
    do_log("clear_category_cache");
    \Nexus\Database\NexusDB::cache_del('category_content');
    $searchBoxList = \App\Models\SearchBox::query()->get(['id']);
    foreach ($searchBoxList as $item) {
        \Nexus\Database\NexusDB::cache_del("category_list_mode_{$item->id}");
    }

}

/**
 * @see functions.php::searchbox_item_list()
 */
function clear_taxonomy_cache($table)
{
    do_log("clear_taxonomy_cache: $table");
    $list = \App\Models\SearchBox::query()->get(['id']);
    foreach ($list as $item) {
        \Nexus\Database\NexusDB::cache_del("{$table}_list_mode_{$item->id}");
    }
    \Nexus\Database\NexusDB::cache_del("{$table}_list_mode_0");
}

function clear_staff_message_cache()
{
    do_log("clear_staff_message_cache");
    \App\Repositories\MessageRepository::updateStaffMessageCountCache(false);
}

/**
 * @see functions.php::get_searchbox_value()
 */
function clear_search_box_cache()
{
    do_log("clear_search_box_cache");
    \Nexus\Database\NexusDB::cache_del("search_box_content");
}

/**
 * @see functions.php::get_category_icon_row()
 */
function clear_icon_cache()
{
    do_log("clear_icon_cache");
    \Nexus\Database\NexusDB::cache_del("category_icon_content");
}

function clear_inbox_count_cache($uid)
{
    do_log("clear_inbox_count_cache");
    foreach (\Illuminate\Support\Arr::wrap($uid) as $id) {
        \Nexus\Database\NexusDB::cache_del('user_'.$id.'_inbox_count');
        \Nexus\Database\NexusDB::cache_del('user_'.$id.'_unread_message_count');
    }
}

function clear_agent_allow_deny_cache()
{
    do_log("clear_agent_allow_deny_cache");
    $allowCacheKey = nexus_env("CACHE_KEY_AGENT_ALLOW", "all_agent_allows");
    $denyCacheKey = nexus_env("CACHE_KEY_AGENT_DENY", "all_agent_denies");
    foreach (["", ":php", ":go"] as $suffix) {
        \Nexus\Database\NexusDB::cache_del($allowCacheKey . $suffix);
        \Nexus\Database\NexusDB::cache_del($denyCacheKey . $suffix);
    }
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
    if (!$fail && isset($userCanCached[$permission][$uid])) {
        return $userCanCached[$permission][$uid];
    }
    $userInfo = get_user_row($uid);
    $class = $userInfo['class'];
    $log .= ", userClass: $class";
    if ($class == \App\Models\User::CLASS_STAFF_LEADER) {
        do_log("$log, CLASS_STAFF_LEADER, true");
        $userCanCached[$permission][$uid] = true;
        return true;
    }
    $userAllPermissions = \App\Repositories\ToolRepository::listUserAllPermissions($uid);
    $result = isset($userAllPermissions[$permission]);
    if ($sequence == 0) {
        $sequence++;
        $log .= ", userAllPermissions: " . json_encode($userAllPermissions);
    }
    $log .= ", result: $result";
    if (!$fail || $result) {
        do_log($log);
        $userCanCached[$permission][$uid] = $result;
        return $result;
    }
    FAIL:
    do_log("$log, [FAIL]");
    if (IN_NEXUS && !IN_TRACKER) {
        global $lang_functions;
        $requireClass = get_setting("authority.$permission");
        if (isset(\App\Models\User::$classes[$requireClass])) {
            stderr($lang_functions['std_sorry'],$lang_functions['std_permission_denied_only'].get_user_class_name($requireClass,false,true,true).$lang_functions['std_or_above_can_view'],false);
        } else {
            stderr($lang_functions['std_error'], $lang_functions['std_permission_denied']);
        }
    }
    throw new \App\Exceptions\InsufficientPermissionException();
}



function is_donor(array $userInfo): bool
{
    return $userInfo['donor'] == 'yes' && ($userInfo['donoruntil'] === null || $userInfo['donoruntil'] == '0000-00-00 00:00:00' || $userInfo['donoruntil'] >= date('Y-m-d H:i:s'));
}

/**
 * @param $authkey
 * @return false|int|mixed|string|null
 * @throws \App\Exceptions\NexusException
 * @see download.php
 */
function get_passkey_by_authkey($authkey)
{
    return \Nexus\Database\NexusDB::remember("authkey2passkey:$authkey", 3600*24, function () use ($authkey) {
        $arr = explode('|', $authkey);
        if (count($arr) != 3) {
            throw new \InvalidArgumentException("Invalid authkey: $authkey, format error");
        }
        $uid = $arr[1];
        $torrentRep = new \App\Repositories\TorrentRepository();
        $decrypted = $torrentRep->checkTrackerReportAuthKey($authkey);
        if (empty($decrypted)) {
            throw new \InvalidArgumentException("Invalid authkey: $authkey");
        }
        $userInfo = \Nexus\Database\NexusDB::remember("announce_user_passkey_$uid", 3600, function () use ($uid) {
            return \App\Models\User::query()->where('id', $uid)->first(['id', 'passkey']);
        });
        return $userInfo->passkey;
    });
}

function executeCommand($command, $format = 'string', $artisan = false, $exception = true): string|array
{
    $append = " 2>&1";
    if (!str_ends_with($command, $append)) {
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
    do_log(sprintf('result_code: %s, result: %s, output: %s', $result_code, $result, $outputString));
    if ($exception && $result_code != 0) {
        throw new \RuntimeException($outputString);
    }
    return $format == 'string' ? $outputString : $output;
}

function has_role_work_seeding($uid)
{
    $result = apply_filter('user_has_role_work_seeding', false, $uid);
    do_log("uid: $uid, result: $result");
    return $result;
}

function is_danger_url($url): bool
{
    $dangerScriptsPattern = "/(logout|login|ajax|announce|scrape|adduser|modtask|take.*)\.php/i";
    $match = preg_match($dangerScriptsPattern, $url);
    if ($match > 0) {
        return true;
    }
    return false;
}

