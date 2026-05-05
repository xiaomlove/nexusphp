<?php
require_once("../include/bittorrent.php");
function bark($msg) {
stdhead();
stdmsg("Update Has Failed !", $msg);
stdfoot();
exit;
}
dbconn();
loggedinorreturn();

if(isset($_POST["nowarned"])&&($_POST["nowarned"]=="nowarned")){
//if (get_user_class() >= UC_SYSOP) {
if (get_user_class() < UC_MODERATOR)
stderr("Sorry", "Access denied.");
{
if (empty($_POST["usernw"]) && empty($_POST["desact"]) && empty($_POST["delete"]))
   bark("You Must Select A User To Edit.");

if (!empty($_POST["usernw"]))
{

$modcomment = date("Y-m-d") . " - Warning Removed By " . $CURUSER['username'];
\App\Models\User::query()->whereIn('id', $_POST['usernw'])
    ->update([
        'warned' => 'no',
        'warneduntil' => null,
        'modcomment' => \Nexus\Database\NexusDB::raw("if(modcomment = '', '$modcomment', concat_ws('\n', '$modcomment', modcomment))")
    ]);
}

if (!empty($_POST["desact"])){
    \App\Models\User::query()->whereIn('id', $_POST['desact'])->update(['enabled' => 'no']);
}
}
}
header("Location: warned.php");
?>
