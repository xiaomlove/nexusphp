<?php
require_once("../include/bittorrent.php");
header("Content-Type: text/html; charset=utf-8");
if (!mkglobal("username:password"))
	die();
dbconn();
require_once(get_langfile_path("", false, get_langfolder_cookie()));
failedloginscheck ();
cur_user_check () ;
$ip = getip();
function bark($text = "")
{
  global $lang_takelogin;
  $text =  ($text == "" ? $lang_takelogin['std_login_fail_note'] : $text);
  stderr($lang_takelogin['std_login_fail'], $text,false);
}
if ($iv == "yes")
	check_code ($_POST['imagehash'], $_POST['imagestring'],'login.php',true);
$res = sql_query("SELECT id, passhash, secret, enabled, status, two_step_secret, lang FROM users WHERE username = " . sqlesc($username));
$row = mysql_fetch_array($res);

if (!$row)
	failedlogins();
if ($row['status'] == 'pending')
	failedlogins($lang_takelogin['std_user_account_unconfirmed']);

if (!empty($row['two_step_secret'])) {
    if (empty($_POST['two_step_code'])) {
        failedlogins($lang_takelogin['std_require_two_step_code']);
    }
    $ga = new \PHPGangsta_GoogleAuthenticator();
    if (!$ga->verifyCode($row['two_step_secret'], $_POST['two_step_code'])) {
        failedlogins($lang_takelogin['std_invalid_two_step_code']);
    }
}
$log = "user: {$row['id']}, ip: $ip";
if ($row["passhash"] != md5($row["secret"] . $password . $row["secret"])) {
    login_failedlogins();
}

$userRep = new \App\Repositories\UserRepository();
$userRep->saveLoginLog($row['id'], $ip, 'Web', true);

if ($row["enabled"] == "no")
	bark($lang_takelogin['std_account_disabled']);

if (isset($_POST["securelogin"]) && $_POST["securelogin"] == "yes")
{
	$securelogin_indentity_cookie = true;
    /**
     * Not IP related
     * @since 1.8.0
     */
//	$passh = md5($row["passhash"].$ip);
	$passh = md5($row["passhash"]);
	$log .= ", secure login == yeah, passhash: {$row['passhash']}, ip: $ip, md5: $passh";
}
else
{
	$securelogin_indentity_cookie = false;
	$passh = md5($row["passhash"]);
    $log .= ",  passhash: {$row['passhash']}, md5: $passh";
}

if ($securelogin=='yes' || (isset($_POST["ssl"]) && $_POST["ssl"] == "yes"))
{
	$pprefix = "https://";
	$ssl = true;
}
else
{
	$pprefix = "http://";
	$ssl = false;
}
if ($securetracker=='yes' || (isset($_POST["trackerssl"] ) && $_POST["trackerssl"] == "yes"))
{
	$trackerssl = true;
}
else
{
	$trackerssl = false;
}

do_log($log);

//update user lang
$language = \App\Models\Language::query()->where("site_lang_folder", get_langfolder_cookie())->first();
if ($language && $language->id != $row["lang"]) {
    do_log(sprintf("update user: %s lang: %s => %s", $row["id"], $row["lang"], $language->id));
    \App\Models\User::query()->where("id", $row["id"])->update(["lang" => $language->id]);
    clear_user_cache($row["id"]);
}

if (isset($_POST["logout"]) && $_POST["logout"] == "yes")
{
	logincookie($row["id"], $passh,1,900,$securelogin_indentity_cookie, $ssl, $trackerssl);
	//sessioncookie($row["id"], $passh,true);
}
else
{
	logincookie($row["id"], $passh,1,get_setting('system.cookie_valid_days', 365) * 86400,$securelogin_indentity_cookie, $ssl, $trackerssl);
	//sessioncookie($row["id"], $passh,false);
}

if (!empty($_POST["returnto"]))
	nexus_redirect($_POST['returnto']);
else
	nexus_redirect("index.php");
?>
