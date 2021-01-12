<?php
if(!defined('IN_TRACKER'))
die('Hacking attempt!');

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
			$ip = $_SERVER['REMOTE_ADDR'];
		}
	} else {
		if (getenv('HTTP_X_FORWARDED_FOR') && validip(getenv('HTTP_X_FORWARDED_FOR'))) {
			$ip = getenv('HTTP_X_FORWARDED_FOR');
		} elseif (getenv('HTTP_CLIENT_IP') && validip(getenv('HTTP_CLIENT_IP'))) {
			$ip = getenv('HTTP_CLIENT_IP');
		} else {
			$ip = getenv('REMOTE_ADDR');
		}
	}

	return $ip;
}

function sql_query($query)
{
	$begin = microtime(true);
	global $query_name;
	$result = mysql_query($query);
	$query_name[] = [
		'query' => $query,
		'time' => microtime(true) - $begin,
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

function dd($vars)
{
	echo '<pre>';
	array_map(function ($var) {
		var_dump($var);
	}, func_get_args());
	echo '</pre>';
	exit(0);
}

function do_log($log)
{
	global $TWEAK;
	if (!empty($TWEAK['logging'])) {
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		$content = sprintf(
			"[%s] %s:%s %s%s%s %s%s",
			date('Y-m-d H:i:s'),
			$backtrace[0]['file'] ?? '',
			$backtrace[0]['line'] ?? '',
			$backtrace[1]['class'] ?? '',
			$backtrace[1]['type'] ?? '',
			$backtrace[1]['function'] ?? '',
			$log,
			PHP_EOL
		);
		file_put_contents($TWEAK['logging'], $content, FILE_APPEND);
	}
}

/**
 * get translation for given name
 *
 * @author xiaomlove
 * @date 2021/1/11
 * @param null $name
 * @param null $prefix
 * @return mixed|string
 */
function __($name = null, $prefix = null)
{
	static $i18n;
	static $i18nWithoutPrefix;
	$userLocale = get_langfolder_cookie();
	$defaultLocale = 'en';
	if (is_null($prefix)) {
		//get prefix from scripe name
		$prefix = basename($_SERVER['SCRIPT_NAME']);
		$prefix = strstr($prefix, '.php', true);
	}
	if (is_null($i18n)) {
		//get all in18 may be used, incldue user locale and default locale, and name in('_target', 'functions') (because they are common) or prefixed with given prefix
		$sql = "select locale, name, translation from i18n where locale in (" . sqlesc($userLocale) . ", " . sqlesc($defaultLocale) . ") and (name in ('_target', 'functions') or name like '{$prefix}%')";
		$result = sql_query($sql);
		while ($row = mysql_fetch_assoc($result)) {
			$i18n[$row['locale']][$row['name']] = $row['translation'];
			$i18nWithoutPrefix[$row['locale']][substr($row['name'], strpos($row['name'], '.') + 1)] = $row['translation'];
		}
	}
	if (is_null($name)) {
		return $i18nWithoutPrefix[$userLocale] ?? $i18nWithoutPrefix[$defaultLocale] ?? [];
	}
	$name = "$prefix.$name";
	return $i18n[$userLocale][$name] ?? $i18n[$defaultLocale][$name] ?? '';

}

function config($key, $default = null)
{
	global $rootpath;
	static $configs;
	if (is_null($configs)) {
		//get all configuration from config file
		$files = glob($rootpath . 'config/*.php');
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
 * $name == null and $prefix == null, return all
 * $name == null and $prefix != null, return with specified prefix, but the result's prefix will be stripped
 *
 * @author xiaomlove
 * @date 2021/1/11
 * @param null $name
 * @param null $prefix
 * @return array|mixed|string
 */
function get_setting($name = null, $prefix = null)
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
			$settings[$row['name']] = $value;
		}

	}
	if (!is_null($name)) {
		if (!is_null($prefix)) {
			$name = "$prefix.$name";
		}
		return $settings[$name] ?? null;
	}
	if (is_null($prefix)) {
		return $settings;
	}
	$filtered = [];
	foreach ($settings as $name => $value) {
		if (preg_match("/^$prefix/", $name)) {
			$nameWithoutPrefix = substr($name, strpos($name, '.') + 1);
			$filtered[$nameWithoutPrefix] = $value;
		}
	}
	return $filtered;

}

function env($key, $default = null)
{
	global $rootpath;
	static $env;
	if (is_null($env)) {
		$envFile = $rootpath . '.env';
		if (!file_exists($envFile)) {
			throw new \RuntimeException(".env file is not exists in the root path.");
		}
		$fp = fopen($envFile, 'r');
		if ($fp === false) {
			throw new \RuntimeException(".env file: $envFile is not readable.");
		}
		while ($line = trim(fgets($fp))) {
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
	}
	return $env[$key] ?? $default;

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
?>
