<?php

use App\Repositories\UserRepository;
use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
loggedinorreturn();
user_can('user-delete', true);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userid = trim($_POST['userid']);

    if (! $userid) {
        stderr('Error', 'Please fill out the form correctly.');
    }

    $arr = NexusDB::table('users')->where('id', (int) $userid)->first();
    $arr = $arr ? (array) $arr : null;
    if (! $arr) {
        stderr('Error', 'Bad user id or password. Please verify that all entered information is correct.');
    }

    $id = $arr['id'];
    $name = $arr['username'];
    $userRep = new UserRepository;
    $userRep->destroy($id);
    stderr('Success', 'The account <b>'.htmlspecialchars($name).'</b> was deleted.', false);
}
stdhead('Delete account');
?>
<h1>Delete account</h1>
<table border=1 cellspacing=0 cellpadding=5>
<form method=post action=delacctadmin.php>
<tr><td class=rowhead>User name</td><td><input size=40 name=userid></td></tr>

<tr><td colspan=2><input type=submit class=btn value='Delete'></td></tr>
</form>
</table>
<?php
stdfoot();
