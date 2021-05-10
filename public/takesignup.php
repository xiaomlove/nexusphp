<?php
require_once("../include/bittorrent.php");
dbconn();
cur_user_check ();
require_once(get_langfile_path("",true));
require_once(get_langfile_path("", false, get_langfolder_cookie()));

function bark($msg) {
	global $lang_takesignup;
	stdhead();
	stdmsg($lang_takesignup['std_signup_failed'], $msg);
	stdfoot();
	exit;
}

$type = $_POST['type'];
if ($type == 'invite'){
registration_check();
failedloginscheck ("Invite Signup");
if ($iv == "yes")
	check_code ($_POST['imagehash'], $_POST['imagestring'],'signup.php?type=invite&invitenumber='.htmlspecialchars($_POST['hash']));
}
else{
registration_check("normal");
failedloginscheck ("Signup");
if ($iv == "yes")
	check_code ($_POST['imagehash'], $_POST['imagestring']);
}

function isportopen($port)
{
	$sd = @fsockopen($_SERVER["REMOTE_ADDR"], $port, $errno, $errstr, 1);
	if ($sd)
	{
		fclose($sd);
		return true;
	}
	else
		return false;
}

function isproxy()
{
	$ports = array(80, 88, 1075, 1080, 1180, 1182, 2282, 3128, 3332, 5490, 6588, 7033, 7441, 8000, 8080, 8085, 8090, 8095, 8100, 8105, 8110, 8888, 22788);
	for ($i = 0; $i < count($ports); ++$i)
		if (isportopen($ports[$i])) return true;
	return false;
}
if ($type=='invite')
{
$inviter =  $_POST["inviter"];
	int_check($inviter);
$code = unesc($_POST["hash"]);

//check invite code
	$sq = sprintf("SELECT id, inviter FROM invites WHERE valid = %s and hash ='%s'", \App\Models\Invite::VALID_YES, mysql_real_escape_string($code));
	$res = sql_query($sq) or sqlerr(__FILE__, __LINE__);
	$inv = mysql_fetch_assoc($res);
	if (!$inv)
		bark('invalid invite code');
	if ($inv['inviter'] != $inviter) {
        \App\Models\Invite::query()->where('id', $inv['id'])->update(['valid' => \App\Models\Invite::VALID_NO]);
        stderr(nexus_trans('nexus.invalid_argument'), nexus_trans('invite.invalid_inviter'));
        exit();
    }

$ip = getip();


$res = sql_query("SELECT username FROM users WHERE id = $inviter") or sqlerr(__FILE__, __LINE__);
$arr = mysql_fetch_assoc($res);
$invusername = $arr['username'];
}

if (!mkglobal("wantusername:wantpassword:passagain:email"))
	die();

$email = htmlspecialchars(trim($email));
$email = safe_email($email);
if (!check_email($email))
	bark($lang_takesignup['std_invalid_email_address']);

if(EmailBanned($email))
    bark($lang_takesignup['std_email_address_banned']);

if(!EmailAllowed($email))
    bark($lang_takesignup['std_wrong_email_address_domains'].allowedemails());

$country = $_POST["country"];
	int_check($country);

if ($showschool == 'yes'){
$school = $_POST["school"];
int_check($school);
}

$gender =  htmlspecialchars(trim($_POST["gender"]));
$allowed_genders = array("Male","Female","male","female");
if (!in_array($gender, $allowed_genders, true))
	bark($lang_takesignup['std_invalid_gender']);

if (empty($wantusername) || empty($wantpassword) || empty($email) || empty($country) || empty($gender))
	bark($lang_takesignup['std_blank_field']);


if (strlen($wantusername) > 12)
	bark($lang_takesignup['std_username_too_long']);

if ($wantpassword != $passagain)
	bark($lang_takesignup['std_passwords_unmatched']);

if (strlen($wantpassword) < 6)
	bark($lang_takesignup['std_password_too_short']);

if (strlen($wantpassword) > 40)
	bark($lang_takesignup['std_password_too_long']);

if ($wantpassword == $wantusername)
	bark($lang_takesignup['std_password_equals_username']);

if (!validemail($email))
	bark($lang_takesignup['std_wrong_email_address_format']);

if (!validusername($wantusername))
	bark($lang_takesignup['std_invalid_username']);

// make sure user agrees to everything...
if ($_POST["rulesverify"] != "yes" || $_POST["faqverify"] != "yes" || $_POST["ageverify"] != "yes")
	stderr($lang_takesignup['std_signup_failed'], $lang_takesignup['std_unqualified']);

// check if email addy is already in use
$a = (@mysql_fetch_row(@sql_query("select count(*) from users where email='".mysql_real_escape_string($email)."'"))) or sqlerr(__FILE__, __LINE__);
if ($a[0] != 0)
  bark($lang_takesignup['std_email_address'].$email.$lang_takesignup['std_in_use']);

/*
// do simple proxy check
if (isproxy())
	bark("You appear to be connecting through a proxy server. Your organization or ISP may use a transparent caching HTTP proxy. Please try and access the site on <a href="." . get_protocol_prefix() . "$BASEURL.":81/signup.php>port 81</a> (this should bypass the proxy server). <p><b>Note:</b> if you run an Internet-accessible web server on the local machine you need to shut it down until the sign-up is complete.");

$res = sql_query("SELECT COUNT(*) FROM users") or sqlerr(__FILE__, __LINE__);
$arr = mysql_fetch_row($res);
*/

$secret = mksecret();
$wantpasshash = md5($secret . $wantpassword . $secret);
$editsecret = ($verification == 'admin' ? '' : $secret);
$invite_count = (int) $invite_count;

$wantusername = sqlesc($wantusername);
$wantpasshash = sqlesc($wantpasshash);
$secret = sqlesc($secret);
$editsecret = sqlesc($editsecret);
$send_email = $email;
$email = sqlesc($email);
$country = sqlesc($country);
$gender = sqlesc($gender);
$sitelangid = sqlesc(get_langid_from_langcookie());

$res_check_user = sql_query("SELECT * FROM users WHERE username = " . $wantusername);

if(mysql_num_rows($res_check_user) == 1)
  bark($lang_takesignup['std_username_exists']);

$ret = sql_query("INSERT INTO users (username, passhash, secret, editsecret, email, country, gender, status, class, invites, ".($type == 'invite' ? "invited_by," : "")." added, last_access, lang, stylesheet".($showschool == 'yes' ? ", school" : "").", uploaded) VALUES (" . $wantusername . "," . $wantpasshash . "," . $secret . "," . $editsecret . "," . $email . "," . $country . "," . $gender . ", 'pending', ".$defaultclass_class.",". $invite_count .", ".($type == 'invite' ? "'$inviter'," : "") ." '". date("Y-m-d H:i:s") ."' , " . " '". date("Y-m-d H:i:s") ."' , ".$sitelangid . ",".$defcss.($showschool == 'yes' ? ",".$school : "").",".($iniupload_main > 0 ? $iniupload_main : 0).")") or sqlerr(__FILE__, __LINE__);
$id = mysql_insert_id();
$dt = sqlesc(date("Y-m-d H:i:s"));
$subject = sqlesc($lang_takesignup['msg_subject'].$SITENAME."!");
$msg = sqlesc($lang_takesignup['msg_congratulations'].htmlspecialchars($wantusername).$lang_takesignup['msg_you_are_a_member']);
sql_query("INSERT INTO messages (sender, receiver, subject, added, msg) VALUES(0, $id, $subject, $dt, $msg)") or sqlerr(__FILE__, __LINE__);

//write_log("User account $id ($wantusername) was created");
$res = sql_query("SELECT passhash, secret, editsecret, status FROM users WHERE id = ".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
$row = mysql_fetch_assoc($res);
$psecret = md5($row['secret']);
$ip = getip();
$usern = htmlspecialchars($wantusername);
$title = $SITENAME.$lang_takesignup['mail_title'];
$confirmUrl = getSchemeAndHttpHost() . "/confirm.php?id=$id&secret=$psecret";
$confirmResendUrl = getSchemeAndHttpHost() . "/confirm_resend.php";
$body = <<<EOD
{$lang_takesignup['mail_one']}$usern{$lang_takesignup['mail_two']}($email){$lang_takesignup['mail_three']}$ip{$lang_takesignup['mail_four']}
<b><a href="javascript:void(null)" onclick="window.open($confirmUrl)">
{$lang_takesignup['mail_this_link']} </a></b><br />
$confirmUrl
{$lang_takesignup['mail_four_1']}
<b><a href="javascript:void(null)" onclick="window.open($confirmResendUrl)">{$lang_takesignup['mail_here']}</a></b><br />
$confirmResendUrl
<br />
{$lang_takesignup['mail_five']}
EOD;

if ($type == 'invite')
{
    //don't forget to delete confirmed invitee's hash code from table invites
    //sql_query("DELETE FROM invites WHERE hash = '".mysql_real_escape_string($code)."'");
    // set invalid
    $update = [
        'valid' => \App\Models\Invite::VALID_NO,
        'invitee_register_uid' => $id,
        'invitee_register_email' => $_POST['email'],
        'invitee_register_username' => $_POST['wantusername'],
    ];
    \App\Models\Invite::query()->where('id', $inv['id'])->update($update);

    $dt = sqlesc(date("Y-m-d H:i:s"));
    $subject = sqlesc($lang_takesignup_target[get_user_lang($inviter)]['msg_invited_user_has_registered']);
    $msg = sqlesc($lang_takesignup_target[get_user_lang($inviter)]['msg_user_you_invited'].$usern.$lang_takesignup_target[get_user_lang($inviter)]['msg_has_registered']);
    //sql_query("UPDATE users SET uploaded = uploaded + 10737418240 WHERE id = $inviter"); //add 10GB to invitor's uploading credit
    sql_query("INSERT INTO messages (sender, receiver, subject, added, msg) VALUES(0, $inviter, $subject, $dt, $msg)") or sqlerr(__FILE__, __LINE__);
    $Cache->delete_value('user_'.$inviter.'_unread_message_count');
    $Cache->delete_value('user_'.$inviter.'_inbox_count');
}

if ($verification == 'admin'){
	if ($type == 'invite')
	header("Location: " . get_protocol_prefix() . "$BASEURL/ok.php?type=inviter");
	else
	header("Location: " . get_protocol_prefix() . "$BASEURL/ok.php?type=adminactivate");
}
elseif ($verification == 'automatic' || $smtptype == 'none'){
	header("Location: " . get_protocol_prefix() . "$BASEURL/confirm.php?id=$id&secret=$psecret");
}
else{
	sent_mail($send_email,$SITENAME,$SITEEMAIL,$title,$body,"signup",false,false,'');
	header("Location: " . get_protocol_prefix() . "$BASEURL/ok.php?type=signup&email=" . rawurlencode($send_email));
}

?>
