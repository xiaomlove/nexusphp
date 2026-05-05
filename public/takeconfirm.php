<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
$id =  isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : die());
int_check($id,true);
if (($CURUSER['id'] != $id && !user_can('viewinvite')) || !is_valid_id($id))
    stderr($lang_functions['std_sorry'],$lang_functions['std_permission_denied'], true, false);
$email = unesc(htmlspecialchars(trim($_POST["email"])));
if(!empty($_POST['conusr'])) {
    $userList = \App\Models\User::query()->whereIn('id', $_POST['conusr'])
        ->where('status', 'pending')
        ->where('invited_by', $id)
        ->get(\App\Models\User::$commonFields)
    ;
    if ($userList->isNotEmpty()) {
        $uidArr = [];
        foreach ($userList as $user) {
            $uidArr[] = $user->id;
            fire_event(\App\Enums\ModelEventEnum::USER_UPDATED, $user);
        }
        \App\Models\User::query()->whereIn('id', $uidArr)->update(['status' => 'confirmed', 'editsecret' => '']);
    } else {
        stderr($lang_takeconfirm['std_sorry'],$lang_takeconfirm['std_no_buddy_to_confirm'].
            "<a class=altlink href=invite.php?id={$CURUSER['id']}>".$lang_takeconfirm['std_here_to_go_back'],false);
    }
} else {
    stderr($lang_takeconfirm['std_sorry'],$lang_takeconfirm['std_no_buddy_to_confirm'].
        "<a class=altlink href=invite.php?id={$CURUSER['id']}>".$lang_takeconfirm['std_here_to_go_back'],false);
}
$title = $SITENAME.$lang_takeconfirm['mail_title'];
$baseUrl = getSchemeAndHttpHost();
$siteName = \App\Models\Setting::getSiteName();
$mailContentTwo = sprintf($lang_takeconfirm['mail_content_two'], $siteName, $REPORTMAIL, $siteName);
$body = <<<EOD
{$lang_takeconfirm['mail_content_1']}
<b><a href="javascript:void(null)" onclick="window.open('{$baseUrl}/login.php')">{$lang_takeconfirm['mail_here']}</a></b><br />
{$baseUrl}/login.php
{$mailContentTwo}
EOD;

//this mail is sent when the site is using admin(open/closed)/inviter(closed) confirmation and the admin/inviter confirmed the pending user
sent_mail($email,$SITENAME,$SITEEMAIL,$title,$body,"invite confirm",false,false,'');

header("Location: invite.php?id=".htmlspecialchars($CURUSER['id']));
?>
