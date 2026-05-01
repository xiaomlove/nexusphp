<?php
require_once("../include/bittorrent.php");
header("Content-Type: text/html; charset=utf-8");
$id = (int) $_GET["id"];
$confirm_md5 = $_GET["secret"];

if (!$id)
	httperr();

dbconn();

$row = \Nexus\Database\NexusDB::table('users')
    ->where('id', (int) $id)
    ->select(['passhash', 'secret', 'auth_key', 'editsecret', 'status'])
    ->first();
$row = $row ? (array) $row : null;

if (!$row)
	httperr();

if ($row["status"] != "pending") {
	header("Location: ok.php?type=confirmed");
	exit();
}

$confirm_sec = hash_pad($row["secret"]);
if ($confirm_md5 != md5($confirm_sec))
	httperr();

$updated = \Nexus\Database\NexusDB::table('users')
	->where('id', (int) $id)
	->where('status', 'pending')
	->update(['status' => 'confirmed', 'editsecret' => '']);

if (!$updated)
	httperr();

publish_model_event(\App\Enums\ModelEventEnum::USER_UPDATED, $id);
//if ($securelogin == "yes")
//{
//	$securelogin_indentity_cookie = true;
//	$passh = md5($row["passhash"].$_SERVER["REMOTE_ADDR"]);
//}
//else	// when it's op, default is not use secure login
//{
//	$securelogin_indentity_cookie = false;
//	$passh = md5($row["passhash"]);
//}
//logincookie($id, $passh,1,get_setting('system.cookie_valid_days', 365) * 86400,$securelogin_indentity_cookie);
logincookie($id, $row["auth_key"]);
//sessioncookie($row["id"], $passh,false);

header("Location: ok.php?type=confirm");
?>
