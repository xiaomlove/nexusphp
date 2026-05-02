<?php

use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
loggedinorreturn();

if (get_user_class() < UC_MODERATOR) {
    stderr('Sorry', 'Access denied.');
}
$status = $_GET['status'];
if ($status) {
    int_check($status, true);
}

$rows = NexusDB::table('users')
            ->where('status', 'pending')
            ->orderBy('username')
            ->get();
if (count($rows) != 0) {
    stdhead('Unconfirmed Users');
    begin_main_frame();
    begin_frame('');
    echo '<br><table width=100% border=1 cellspacing=0 cellpadding=5>';
    if ($status) {
        echo '<tr><td class=rowhead colspan=5><font color=red size=1>The User account has been updated!</font></tr></td>';
    }
    echo '<tr>';
    echo '<td class=rowhead><center>Name</center></td>';
    echo '<td class=rowhead><center>eMail</center></td>';
    echo '<td class=rowhead><center>Added</center></td>';
    echo '<td class=rowhead><center>Set Status</center></td>';
    echo '<td class=rowhead><center>Confirm</center></td>';
    echo '</tr>';
    foreach ($rows as $row) {
        $row = (array) $row;
        $id = $row['id'];
        echo '<tr><form method=post action=modtask.php>';
        echo '<input type=hidden name=\'action\' value=\'confirmuser\'>';
        echo "<input type=hidden name='userid' value='$id'>";
        echo '<a href="userdetails.php?id='.$row['id'].'"><td><center>'.$row['username'].'</center></td></a>';
        echo '<td align=center>&nbsp;&nbsp;&nbsp;&nbsp;'.$row['email'].'</td>';
        echo '<td align=center>&nbsp;&nbsp;&nbsp;&nbsp;'.$row['added'].'</td>';
        echo '<td align=center><select name=confirm><option value=pending>pending</option><option value=confirmed>confirmed</option></select></td>';
        echo '<td align=center><input type=submit value="-Go-" style=\'height: 20px; width: 40px\'>';
        echo '</form></tr>';
    }
    echo '</table>';
    end_frame();
    end_main_frame();
} else {
    if ($status) {
        stderr('Updated!', 'The user account has been updated.');
    } else {
        stderr('Ups!', 'Nothing Found...');
    }
}

stdfoot();
