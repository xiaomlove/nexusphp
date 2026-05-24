<?php

use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
loggedinorreturn();
if (get_user_class() < UC_SYSOP) {
    stderr('Error', 'Permission denied.');
}

$action = isset($_POST['action']) ? htmlspecialchars($_POST['action']) : (isset($_GET['action']) ? htmlspecialchars($_GET['action']) : 'showlist');
$id = isset($_POST['id']) ? htmlspecialchars($_POST['id']) : (isset($_GET['id']) ? htmlspecialchars($_GET['id']) : '');
$update = isset($_POST['update']) ? htmlspecialchars($_POST['update']) : (isset($_GET['update']) ? htmlspecialchars($_GET['update']) : '');

function check($id)
{
    if (! is_valid_id($id)) {
        return stderr('Error', 'Invalid ID');
    } else {
        return true;
    }
}
function searchform()
{
    ?>
<form method=post name=search action=maxlogin.php?>
<input type=hidden name=action value=searchip>
<p class=success align=center>Search IP <input type=text name=ip size=25> <input type=submit name=submit value='Search IP' class=btn></p>
</form>
<?php
}
$countrows = number_format(NexusDB::table('loginattempts')->count()) + 1;
$page = intval($_GET['page'] ?? 0);

$order = $_GET['order'] ?? '';
if ($order == 'id') {
    $orderby = 'id';
} elseif ($order == 'ip') {
    $orderby = 'ip';
} elseif ($order == 'added') {
    $orderby = 'added';
} elseif ($order == 'attempts') {
    $orderby = 'attempts';
} elseif ($order == 'type') {
    $orderby = 'type';
} elseif ($order == 'status') {
    $orderby = 'banned';
} else {
    $orderby = 'attempts';
}

$perpage = 50;
[$pagertop, $pagerbottom, $limit, $offsetStart, $rowsPerPage] = pager($perpage, $countrows, "maxlogin.php?order=$order&");
$msg = '';
if ($update) {
    $msg = '<h3><b>'.htmlspecialchars($update).' Successful!</b></h3>';
}
if ($action == 'showlist') {
    stdhead('Max. Login Attemps - Show List');
    echo '<h1>Failed Login Attempts</h1>';
    echo $msg;
    echo "<table border=1 cellspacing=0 cellpadding=5 width=100%>\n";

    $rows = NexusDB::table('loginattempts')
        ->orderByDesc($orderby)
        ->offset((int) $offsetStart)
        ->limit((int) $rowsPerPage)
        ->get();
    if (count($rows) == 0) {
        echo "<tr><td colspan=2><b>Nothing found</b></td></tr>\n";
    } else {
        echo '<tr><td class=colhead><a href=?order=id>ID</a></td><td class=colhead align=left><a href=?order=ip>Ip Address</a></td><td class=colhead align=left><a href=?order=added>Action Time</a></td>'.
          "<td class=colhead align=left><a href=?order=attempts>Attempts</a></td><td class=colhead align=left><a href=?order=type>Attempt Type</a></td><td class=colhead align=left><a href=?order=status>Status</a></td></tr>\n";

        foreach ($rows as $arr) {
            $arr = (array) $arr;
            $a2 = NexusDB::table('users')
                ->where('ip', (string) $arr['ip'])
                ->select(['id', 'username'])
                ->first();
            $a2 = $a2 ? (array) $a2 : ['id' => 0, 'username' => ''];
            echo "<tr><td align=>{$arr['id']}</td><td align=left>{$arr['ip']} ".($a2['id'] ? get_username($a2['id']) : '')."</td><td align=left>{$arr['added']}</td><td align=left>$arr[attempts]</td><td align=left>".($arr['type'] == 'recover' ? 'Recover Password Attempt!' : 'Login Attempt!').'</td><td align=left>'.($arr['banned'] == 'yes' ? "<font color=red><b>banned</b></font> <a href=maxlogin.php?action=unban&id={$arr['id']}><font color=green>[<b>unban</b>]</font></a>" : "<font color=green><b>not banned</b></font> <a href=maxlogin.php?action=ban&id={$arr['id']}><font color=red>[<b>ban</b>]</font></a>")."  <a OnClick=\"return confirm('Are you wish to delete this attempt?');\" href=maxlogin.php?action=delete&id={$arr['id']}>[<b>delete</b></a>] <a href=maxlogin.php?action=edit&id={$arr['id']}><font color=blue>[<b>edit</b></a>]</font></td></tr>\n";
        }

    }
    echo '</table>';
    if ($countrows > $perpage) {
        echo $pagerbottom;
    }
    searchform();
    stdfoot();
} elseif ($action == 'ban') {
    check($id);
    stdhead('Max. Login Attemps - BAN');
    NexusDB::table('loginattempts')->where('id', (int) $id)->update(['banned' => 'yes']);
    nexus_redirect('maxlogin.php?update=Ban');
} elseif ($action == 'unban') {
    check($id);
    stdhead('Max. Login Attemps - UNBAN');
    NexusDB::table('loginattempts')->where('id', (int) $id)->update(['banned' => 'no']);
    nexus_redirect('maxlogin.php?update=Unban');
} elseif ($action == 'delete') {
    check($id);
    stdhead('Max. Login Attemps - DELETE');
    NexusDB::table('loginattempts')->where('id', (int) $id)->delete();
    nexus_redirect('maxlogin.php?update=Delete');
} elseif ($action == 'edit') {
    check($id);
    stdhead('Max. Login Attemps - EDIT ('.htmlspecialchars($id).')');
    $a = NexusDB::table('loginattempts')->where('id', (int) $id)->first();
    $a = $a ? (array) $a : null;
    if (! $a) {
        stderr('Error', 'Not found');
    }
    echo "<table border=1 cellspacing=0 cellpadding=5 width=100%>\n";
    echo '<tr><td><p>IP Address: <b>'.htmlspecialchars($a['ip']).'</b></p>';
    echo '<p>Action Time: <b>'.htmlspecialchars($a['added']).'</b></p></tr></td>';
    echo "<form method='post' action='maxlogin.php'>";
    echo "<input type='hidden' name='action' value='save'>";
    echo "<input type='hidden' name='id' value='{$a['id']}'>";
    echo "<input type='hidden' name='ip' value='{$a['ip']}'>";
    if ($_GET['return'] == 'yes') {
        echo "<input type='hidden' name='returnto' value='viewunbaniprequest.php'>";
    }
    echo "<tr><td>Attempts <input type='text' size='33' name='attempts' value='$a[attempts]'>";
    echo "<tr><td>Attempt Type <select name='type'><option value='login' ".($a['type'] == 'login' ? 'selected' : '').">Login Attempt</option><option value='recover' ".($a['type'] == 'recover' ? 'selected' : '').'>Recover Password Attempts</option></select></tr></td>';
    echo "<tr><td>Current Status <select name='banned'><option value='yes' ".($a['banned'] == 'yes' ? 'selected' : '').">Banned!</option><option value='no' ".($a['banned'] == 'no' ? 'selected' : '').'>Not Banned!</option></select></tr></td>';
    echo "<tr><td><input type='submit' name='submit' value='Save' class=btn></tr></td>";
    echo '</table>';
    stdfoot();

} elseif ($action == 'save') {
    $id = intval($_POST['id'] ?? 0);
    $ip = $_POST['ip'];
    $attempts = $_POST['attempts'];
    $type = $_POST['type'];
    $banned = $_POST['banned'];
    check($id);
    check($attempts);
    NexusDB::table('loginattempts')
        ->where('id', (int) $id)
        ->limit(1)
        ->update([
            'attempts' => (int) $attempts,
            'type' => (string) $type,
            'banned' => (string) $banned,
        ]);
    if ($_POST['returnto']) {
        $returnto = $_POST['returnto'];
        header("Location: $returnto");
    } else {
        header('Location: maxlogin.php?update=Edit');
    }
} elseif ($action == 'searchip') {
    $ip = trim($_POST['ip']);
    $rows = NexusDB::table('loginattempts')
        ->where('ip', 'like', '%'.$ip.'%')
        ->get();
    stdhead('Max. Login Attemps - Search');
    echo '<h2>Failed Login Attempts</h2>';
    echo "<table border=1 cellspacing=0 cellpadding=5 width=100%>\n";
    if (count($rows) == 0) {
        echo "<tr><td colspan=2><b>Sorry, nothing found!</b></td></tr>\n";
    } else {
        echo '<tr><td class=colhead><a href=?order=id>ID</a></td><td class=colhead align=left><a href=?order=ip>Ip Address</a></td><td class=colhead align=left><a href=?order=added>Action Time</a></td>'.
        "<td class=colhead align=left><a href=?order=attempts>Attempts</a></td><td class=colhead align=left><a href=?order=type>Attempt Type</a></td><td class=colhead align=left><a href=?order=status>Status</a></td></tr>\n";

        foreach ($rows as $arr) {
            $arr = (array) $arr;
            $a2 = NexusDB::table('users')
                ->where('ip', (string) $arr['ip'])
                ->select(['id', 'username'])
                ->first();
            $a2 = $a2 ? (array) $a2 : ['id' => 0, 'username' => ''];
            echo "<tr><td align=>{$arr['id']}</td><td align=left>{$arr['ip']} ".($a2['id'] ? get_username($a2['id']) : '')."</td><td align=left>{$arr['added']}</td><td align=left>$arr[attempts]</td><td align=left>".($arr['type'] == 'recover' ? 'Recover Password Attempt!' : 'Login Attempt!').'</td><td align=left>'.($arr['banned'] == 'yes' ? "<font color=red><b>banned</b></font> <a href=maxlogin.php?action=unban&id={$arr['id']}><font color=green>[<b>unban</b>]</font></a>" : "<font color=green><b>not banned</b></font> <a href=maxlogin.php?action=ban&id={$arr['id']}><font color=red>[<b>ban</b>]</font></a>")."  <a OnClick=\"return confirm('Are you wish to delete this attempt?');\" href=maxlogin.php?action=delete&id={$arr['id']}>[<b>delete</b></a>] <a href=maxlogin.php?action=edit&id={$arr['id']}><font color=blue>[<b>edit</b></a>]</font></td></tr>\n";
        }
    }
    echo "</table>\n";
    searchform();
    stdfoot();
} else {
    stderr('Error', 'Invalid Action');
}
