<?php
require_once("../include/bittorrent.php");
header("Content-Type: text/html; charset=utf-8");
if (!mkglobal("username"))
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
if ($iv == "yes") {
    check_code($_POST['imagehash'] ?? null, $_POST['imagestring'] ?? null, 'login.php', true);
}
//同时支持新旧两种登录方式
$useChallengeResponse = \App\Models\Setting::getIsUseChallengeResponseAuthentication();
if ($useChallengeResponse) {
    if (empty($_POST['response'])) {
        failedlogins("Require response parameter.");
    }
} else {
    if (empty($_POST['password'])) {
        failedlogins("Require password parameter.");
    }
}

$row = \Nexus\Database\NexusDB::table('users')
    ->where('username', (string) $username)
    ->select(['id', 'passhash', 'secret', 'auth_key', 'enabled', 'status', 'two_step_secret', 'lang'])
    ->first();
$row = $row ? (array) $row : null;

if (!$row)
	failedlogins();
if ($row['status'] == 'pending')
	failedlogins($lang_takelogin['std_user_account_unconfirmed']);
if ($row["enabled"] == "no" && \App\Models\Setting::getSelfEnableBonus() <= 0) {
    bark($lang_takelogin['std_account_disabled']);
}

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
$update = [];
if ($useChallengeResponse) {
    $challenge = \Nexus\Database\NexusDB::cache_get(get_challenge_key($username));
    if (empty($challenge)) {
        failedlogins("expired");
    }
    $log .= ", useChallengeResponse, client response: " . $_POST['response'];
} else {
    $passwordHash = hash('sha256', $row['secret'] . hash('sha256', $_POST['password']));
    $log .= ", !useChallengeResponse, passwordHash: $passwordHash";
    if (empty($row['auth_key'])) {
        //先使用旧的验证方式验证
        if ($row["passhash"] != md5($row["secret"] . $_POST['password'] . $row["secret"])) {
            do_log("$log, md5 not equal");
            login_failedlogins();
        }
        $log .= ", no auth_key, upgrade to challenge response";
        //自动升级为新的验证方式
        $update['passhash'] = $row['passhash'] = $passwordHash;
    }
    //后端自动生成挑战响应
    $challenge = mksecret();
    $_POST['response'] = hash_hmac('sha256', $passwordHash, $challenge);
    $log .= ", server generate response: " . $_POST['response'];
}
$expectedResponse = hash_hmac('sha256', $row['passhash'], $challenge);
$log .= ", expectedResponse: $expectedResponse";
if (!hash_equals($expectedResponse, $_POST["response"])) {
    do_log("$log, !hash_equals");
    login_failedlogins();
}
\Nexus\Database\NexusDB::cache_del(get_challenge_key($username));
do_log("$log, login successful");
$userRep = new \App\Repositories\UserRepository();
$userRep->saveLoginLog($row['id'], $ip, 'Web', true);

//update user lang
$language = \App\Models\Language::query()->where("site_lang_folder", get_langfolder_cookie())->first();

if ($language && $language->id != $row["lang"]) {
    do_log(sprintf("update user: %s lang: %s => %s", $row["id"], $row["lang"], $language->id));
    $update["lang"] = $language->id;
}
if (empty($row['auth_key'])) {
    $row['auth_key'] = $update['auth_key'] = hash('sha256', mksecret(32));
}
if (!empty($update)) {
    \App\Models\User::query()->where("id", $row["id"])->update($update);
    clear_user_cache($row["id"]);
}

if (isset($_POST["logout"]) && $_POST["logout"] == "yes")
{
	logincookie($row["id"], $row['auth_key'],900);
}
else
{
    logincookie($row["id"], $row['auth_key']);
}

if (!empty($_POST["returnto"]))
	nexus_redirect($_POST['returnto']);
else
	nexus_redirect("index.php");
?>
