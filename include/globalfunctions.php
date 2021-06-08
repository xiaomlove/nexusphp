<?php
function get_global_sp_state()
{
	global $Cache;
	static $global_promotion_state;
	if (!$global_promotion_state){
		if (!$global_promotion_state = $Cache->get_value('global_promotion_state')){
			$res = mysql_query("SELECT * FROM torrents_state");
			$row = mysql_fetch_assoc($res);
			$global_promotion_state = $row["global_sp_state"];
			$Cache->cache_value('global_promotion_state', $global_promotion_state, 57226);
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
//    $query = preg_replace("/[\n\r\t]+/", " ", $query);
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
	$shhash = preg_replace('/ *$/s', "", $hash);
	return "($name = " . sqlesc($hash) . " OR $name = " . sqlesc($shhash) . ")";
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
function do_log($log, $level = 'info')
{
    $logFile = getLogFile();
	if (($fd = fopen($logFile, 'a')) === false) {
       $fd = fopen(sys_get_temp_dir() . '/nexus.log', 'a');
	}
	static $uid, $passkey, $env, $sequence;
	if (is_null($uid)) {
	    $sequence = 0;
        if (IN_NEXUS) {
            global $CURUSER;
            $user = $CURUSER;
            $uid = $user['id'] ?? 0;
            $passkey = $user['passkey'] ?? $_REQUEST['passkey'] ?? '';
            $env = nexus_env('APP_ENV');
        } else {
            $user = \Illuminate\Support\Facades\Auth::user();
            $uid = $user->id ?? 0;
            $passkey = $user->passkey ?? $_REQUEST['passkey'] ?? '';
            $env = env('APP_ENV');
        }
    } else {
	    $sequence++;
    }
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $content = sprintf(
        "[%s] [%s] [%s] [%s] [%s] [%s] %s.%s %s:%s %s%s%s %s%s",
        date('Y-m-d H:i:s'),
        defined('REQUEST_ID') ? REQUEST_ID : '',
        $uid,
        $passkey,
        $sequence,
        sprintf('%.3f', microtime(true) - NEXUS_START),
        $env, $level,
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
}

function getLogFile()
{
    static $logFile;
    if (!is_null($logFile)) {
        return $logFile;
    }
    if (IN_NEXUS) {
        $config = nexus_config('nexus');
    } else {
        $config = config('nexus');
    }
    $logFile = sys_get_temp_dir() . '/nexus_' . date('Y-m-d') . '.log';
    if (!empty($config['log_file'])) {
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
    $logFile = sprintf('%s-%s%s', $prefix, date('Y-m-d'), $suffix);
    return $logFile;

}

function nexus_config($key, $default = null)
{
    static $configs;
    if (is_null($configs)) {
        //get all configuration from config file
//		$files = glob(ROOT_PATH . 'config/*.php');
        $files = [
            ROOT_PATH . 'config/nexus.php',
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
 * @return array|mixed|string
 */
function get_setting($name = null)
{
	static $settings;
	if (is_null($settings)) {
		//get all settings from database
		$sql = "select name, value from settings";
		$result = sql_query($sql);
		while ($row = mysql_fetch_assoc($result)) {
			$value = $row['value'];
			$arr = json_decode($value, true);
			if (is_array($arr)) {
				$value = $arr;
			}
			arr_set($settings, $row['name'], $value);
		}
	}
	if (is_null($name)) {
	    return $settings;
    }
    return arr_get($settings, $name);
}

function nexus_env($key = null, $default = null)
{
    static $env;
    if (is_null($env)) {
        $envFile = defined('ROOT_PATH') ? ROOT_PATH . '.env' : base_path('.env');
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

function isHttps()
{
    $result = !empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) !== 'off');
    return $result;
}


function getSchemeAndHttpHost()
{
    global $BASEURL;
    if (isRunningInConsole()) {
        return $BASEURL;
    }
    $isHttps = isHttps();
    $protocol = $isHttps ? 'https' : 'http';
    $result = "$protocol://" . $_SERVER['HTTP_HOST'];
    return $result;

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
    if (defined('LARAVEL_START')) {
        $start = LARAVEL_START;
        if ($data instanceof \Illuminate\Http\Resources\Json\ResourceCollection || $data instanceof \Illuminate\Http\Resources\Json\JsonResource) {
            $data = $data->response()->getData(true);
            if (isset($data['data']) && count($data) == 1) {
                //单纯的集合，无分页等其数据
                $data = $data['data'];
            }
        }
    } elseif (defined('NEXUS_START')) {
        $start = NEXUS_START;
    } else {
        throw new \RuntimeException("no constant START is defined.");
    }
    return [
        'ret' => (int)$ret,
        'msg' => (string)$msg,
        'data' => $data,
        'time' => (float)number_format(microtime(true) - $start, 3),
    ];
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
    if (IN_NEXUS) {
        $queries = \Illuminate\Database\Capsule\Manager::connection(\Nexus\Database\NexusDB::ELOQUENT_CONNECTION_NAME)->getQueryLog();
    } else {
        $queries = \Illuminate\Support\Facades\DB::connection(config('database.default'))->getQueryLog();
    }
    if ($all) {
        return nexus_json_encode($queries);
    }
    $query = last($queries);
    return nexus_json_encode($query);
}

function format_datetime($datetime, $format = 'Y-m-d H:i')
{
    if ($datetime instanceof \Carbon\Carbon) {
        return $datetime->format($format);
    }
    if (is_numeric($datetime) && $datetime > strtotime('1970')) {
        return date($format, $datetime);
    }
    return $datetime;
}

function nexus_trans($key, $replace = [], $locale = null)
{
    if (!IN_NEXUS) {
        return trans($key, $replace, $locale);
    }
    static $translations;
    if (!$locale) {
        $lang = get_langfolder_cookie();
        $locale = \App\Http\Middleware\Locale::$languageMaps[$lang] ?? 'en';
    }
    if (is_null($translations)) {
        $langDir = ROOT_PATH . 'resources/lang/';
        $files = glob($langDir . '*/*');
        foreach ($files as $file) {
            $values = require $file;
            $setKey = substr($file, strlen($langDir));
            if (substr($setKey, -4) == '.php') {
                $setKey = substr($setKey, 0, -4);
            }
            $setKey = str_replace('/', '.', $setKey);
            arr_set($translations, $setKey, $values);
        }
    }
    $getKey = $locale . "." . $key;
    $result = arr_get($translations, $getKey);
    if (empty($result) && $locale != 'en') {
        $getKey = "en.$key";
        $result = arr_get($translations, $getKey);
    }
    if (!empty($replace)) {
        $search = array_map(function ($value) {return ":$value";}, array_keys($replace));
        $result = str_replace($search, array_values($replace), $result);
    }
    do_log("key: $key, replace: " . nexus_json_encode($replace) . ", locale: $locale, getKey: $getKey, result: $result");
    return $result;
}

function isRunningInConsole(): bool
{
    return php_sapi_name() == 'cli';
}

function get_base_announce_url()
{
    global $https_announce_urls, $announce_urls;
    $httpsAnnounceUrls = array_filter($https_announce_urls);
    $log = "cookie: " . json_encode($_COOKIE) . ", https_announce_urls: " . json_encode($httpsAnnounceUrls);
    if ((isset($_COOKIE["c_secure_tracker_ssl"]) && $_COOKIE["c_secure_tracker_ssl"] == base64("yeah")) || !empty($httpsAnnounceUrls)) {
        $log .= ", c_secure_tracker_ssl = base64('yeah'): " . base64("yeah") . ", or not empty https_announce_urls";
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
    }
    else{
        $ssl_torrent = "http://";
        $base_announce_url = $announce_urls[0];
    }
    do_log($log);
    return $ssl_torrent . $base_announce_url;
}
