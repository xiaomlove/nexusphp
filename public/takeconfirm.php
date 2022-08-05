<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
$id =  isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : die());
int_check($id,true);
$email = unesc(htmlspecialchars(trim($_POST["email"])));
if(isset($_POST['conusr']))
	sql_query("UPDATE users SET status = 'confirmed', editsecret = '' WHERE id IN (" . implode(", ", $_POST['conusr']) . ") AND status='pending'");
else
	stderr($lang_takeconfirm['std_sorry'],$lang_takeconfirm['std_no_buddy_to_confirm'].
 "<a class=altlink href=invite.php?id={$CURUSER['id']}>".$lang_takeconfirm['std_here_to_go_back'],false);

$title = $SITENAME.$lang_takeconfirm['mail_title'];
$baseUrl = getSchemeAndHttpHost();
$body = <<<EOD
{$lang_takeconfirm['mail_content_1']}
<b><a href="javascript:void(null)" onclick="window.open('{$baseUrl}/login.php')">{$lang_takeconfirm['mail_here']}</a></b><br />
{$baseUrl}/login.php
{$lang_takeconfirm['mail_content_2']}
EOD;

//this mail is sent when the site is using admin(open/closed)/inviter(closed) confirmation and the admin/inviter confirmed the pending user
sent_mail($email,$SITENAME,$SITEEMAIL,$title,$body,"invite confirm",false,false,'');

header("Refresh: 0; url=invite.php?id=".htmlspecialchars($CURUSER['id']));
?>
