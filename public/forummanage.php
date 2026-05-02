<?php

use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
require_once get_langfile_path();
loggedinorreturn();
// lots of place use this variable
$prefix = '';
$user = $CURUSER;
$PHP_SELF = $_SERVER['PHP_SELF'];

user_can('forummanage', true);

// DELETE FORUM ACTION
if (isset($_GET['action']) && $_GET['action'] == 'del') {
    $id = intval($_GET['id'] ?? 0);
    if (! $id) {
        header('Location: forummanage.php');
        exit();
    }
    $topicRows = NexusDB::table('topics')->where('forumid', (int) $id)->select(['id'])->get();
    foreach ($topicRows as $topicRow) {
        NexusDB::table('posts')->where('topicid', (int) $topicRow->id)->delete();
    }
    NexusDB::table('topics')->where('forumid', (int) $id)->delete();
    NexusDB::table('forums')->where('id', (int) $id)->delete();
    NexusDB::table('forummods')->where('forumid', (int) $id)->delete();
    $Cache->delete_value('forums_list');
    $Cache->delete_value('forum_moderator_array');
    header('Location: forummanage.php');
    exit();
}

// EDIT FORUM ACTION
elseif (isset($_POST['action']) && $_POST['action'] == 'editforum') {
    $name = $_POST['name'];
    $desc = $_POST['desc'];
    $id = $_POST['id'];
    if (! $name && ! $desc && ! $id) {
        header('Location: '.get_protocol_prefix()."$BASEURL/forummanage.php");
        exit();
    }
    if (! empty($_POST['moderator'])) {
        $moderator = $_POST['moderator'];
        set_forum_moderators($moderator, $id);
    } else {
        NexusDB::table('forummods')->where('forumid', (int) $id)->delete();
    }
    NexusDB::table('forums')
        ->where('id', (int) $id)
        ->update([
            'sort' => (int) ($_POST['sort'] ?? 0),
            'name' => (string) ($_POST['name'] ?? ''),
            'description' => (string) ($_POST['desc'] ?? ''),
            'forid' => (int) ($_POST['overforums'] ?? 0),
            'minclassread' => (int) ($_POST['readclass'] ?? 0),
            'minclasswrite' => (int) ($_POST['writeclass'] ?? 0),
            'minclasscreate' => (int) ($_POST['createclass'] ?? 0),
        ]);
    $Cache->delete_value('forums_list');
    $Cache->delete_value('forum_moderator_array');
    header('Location: forummanage.php');
    exit();
}

// ADD FORUM ACTION
elseif (isset($_POST['action']) && $_POST['action'] == 'addforum') {
    $name = ($_POST['name']);
    $desc = ($_POST['desc']);
    if (! $name && ! $desc) {
        header('Location: '.get_protocol_prefix()."$BASEURL/forummanage.php");
        exit();
    }
    $id = (int) NexusDB::insert('forums', [
        'sort' => (int) ($_POST['sort'] ?? 0),
        'name' => (string) ($_POST['name'] ?? ''),
        'description' => (string) ($_POST['desc'] ?? ''),
        'minclassread' => (int) ($_POST['readclass'] ?? 0),
        'minclasswrite' => (int) ($_POST['writeclass'] ?? 0),
        'minclasscreate' => (int) ($_POST['createclass'] ?? 0),
        'forid' => (int) ($_POST['overforums'] ?? 0),
    ]);
    $Cache->delete_value('forums_list');
    if ($_POST['moderator']) {
        $moderator = $_POST['moderator'];
        set_forum_moderators($moderator, $id);
    }
    header('Location: forummanage.php');
    exit();
}

// SHOW FORUMS WITH FORUM MANAGMENT TOOLS
stdhead($lang_forummanage['head_forum_management']);
begin_main_frame();
if (isset($_GET['action']) && $_GET['action'] == 'editforum') {
    // EDIT PAGE FOR THE FORUMS
    $id = intval($_GET['id'] ?? 0);
    $forumRows = NexusDB::table('forums')->where('id', (int) $id)->get();
    if (count($forumRows) > 0) {
        foreach ($forumRows as $row) {
            $row = (array) $row;
            ?>
<h1 align=center><a class=faqlink href=forummanage.php><?php echo $lang_forummanage['text_forum_management']?></a><b>--></b><?php echo $lang_forummanage['text_edit_forum']?></h2>
<br />
<form method=post action="<?php echo $_SERVER['PHP_SELF']; ?>">
<table width="100%"  border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center">
    <td colspan="2" class=colhead><?php echo $lang_forummanage['text_edit_forum']?> -- <?php echo htmlspecialchars($row['name']); ?></td>
  </tr>

    <td><b><?php echo $lang_forummanage['row_forum_name']?></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60" value="<?php echo $row['name']; ?>"></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_forum_description']?></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200" value="<?php echo $row['description']; ?>"></td>
  </tr>


    <tr>
    <td><b><?php echo $lang_forummanage['row_overforum']?></td>
    <td>
    <select name=overforums>
    <?php
                        $forid = $row['forid'];
            foreach (NexusDB::table('overforums')->get() as $arr) {
                $arr = (array) $arr;
                $name = $arr['name'];
                $i = $arr['id'];

                echo "<option value=$i".($forid == $i ? ' selected' : '').">$prefix".$name."\n";
            }
            ?>
        </select>
    </td>
  </tr>
<?php
                    $username = get_forum_moderators($row['id'], true);
            ?>
  <tr><td><b><?php echo $lang_forummanage['row_moderator']?></b></td><td><input name="moderator" type="text" style="width: 200px" maxlength="200" value="<?php echo $username?>">&nbsp;<?php echo $lang_forummanage['text_moderator_note']?></td></tr>
    <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_read_permission']?></td>
    <td>
    <select name=readclass>
<?php
                         $maxclass = get_user_class();
            for ($i = 0; $i <= $maxclass; $i++) {
                echo "<option value=$i".($row['minclassread'] == $i ? ' selected' : '').">$prefix".get_user_class_name($i, false, true, true);
            }
            ?>
        </select>
    </td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_write_permission']?></td>
    <td><select name=writeclass>
<?php
                          $maxclass = get_user_class();
            for ($i = 0; $i <= $maxclass; $i++) {
                echo "<option value=$i".($row['minclasswrite'] == $i ? ' selected' : '').">$prefix".get_user_class_name($i, false, true, true)."\n";
            }
            ?>
        </select></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_create_topic_permission']?></td>
    <td><select name=createclass>
<?php
                        $maxclass = get_user_class();
            for ($i = 0; $i <= $maxclass; $i++) {
                echo "<option value=$i".($row['minclasscreate'] == $i ? ' selected' : '').">$prefix".get_user_class_name($i, false, true, true)."\n";
            }
            ?>
        </select></td>
  </tr>
    <tr>
    <td><b><?php echo $lang_forummanage['row_forum_order']?></td>
    <td>
    <select name=sort>
<?php
            $nr = NexusDB::table('forums')->count();
            $maxclass = $nr + 1;
            for ($i = 0; $i <= $maxclass; $i++) {
                echo "<option value=$i".($row['sort'] == $i ? ' selected' : '').">$i \n";
            }
            ?>
        </select>
    <?php echo $lang_forummanage['text_forum_order_note']?></td>
  </tr>

  <tr align="center">
    <td colspan="2"><input type="hidden" name="action" value="editforum"><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="submit" name="Submit" value="<?php echo $lang_forummanage['submit_edit_forum']?>" class="btn"></td>
  </tr>
</table>

<?php
        }
    } else {
        echo $lang_forummanage['text_no_records_found'];
    }
}
//
elseif (isset($_GET['action']) && $_GET['action'] == 'newforum') {
    ?>
<h2 class=transparentbg align=center><a class=faqlink href=forummanage.php><?php echo $lang_forummanage['text_forum_management']?></a><b>--></b><?php echo $lang_forummanage['text_add_forum']?></h2>
<br />
<form method=post action="<?php echo $_SERVER['PHP_SELF']; ?>">
<table width="100%"  border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center">
    <td colspan="2" class=colhead><?php echo $lang_forummanage['text_make_new_forum']?></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_forum_name']?></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60"></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_forum_description']?></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200"></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_overforum']?></td>
    <td>
    <select name=overforums>
<?php
                $forid = $row['forid'] ?? 0;
    foreach (NexusDB::table('overforums')->get() as $arr) {
        $arr = (array) $arr;
        $name = $arr['name'];
        $i = $arr['id'];

        echo "<option value=$i".($forid == $i ? ' selected' : '').">$prefix".$name."\n";
    }
    ?>
        </select>
    </td>
  </tr>
	<tr><td><b><?php echo $lang_forummanage['row_moderator']?></b></td><td><input name="moderator" type="text" style="width: 200px" maxlength="200">&nbsp;<?php echo $lang_forummanage['text_moderator_note']?></td></tr>
    <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_read_permission']?></td>
    <td>
    <select name=readclass>
<?php
                 $maxclass = get_user_class();
    for ($i = 0; $i <= $maxclass; $i++) {
        echo "<option value=$i".($user['class'] == $i ? ' selected' : '').">$prefix".get_user_class_name($i, false, true, true)."\n";
    }
    ?>
        </select>
    </td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_write_permission']?></td>
    <td><select name=writeclass>
<?php
                  $maxclass = get_user_class();
    for ($i = 0; $i <= $maxclass; $i++) {
        echo "<option value=$i".($user['class'] == $i ? ' selected' : '').">$prefix".get_user_class_name($i, false, true, true)."\n";
    }
    ?>
        </select></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_create_topic_permission']?></td>
    <td><select name=createclass>
<?php
                $maxclass = get_user_class();
    for ($i = 0; $i <= $maxclass; $i++) {
        echo "<option value=$i".($user['class'] == $i ? ' selected' : '').">$prefix".get_user_class_name($i, false, true, true)."\n";
    }
    ?>
        </select></td>
  </tr>
    <tr>
    <td><b><?php echo $lang_forummanage['row_forum_order']?></td>
    <td>
    <select name=sort>
<?php
    $nr = NexusDB::table('forums')->count();
    $maxclass = $nr + 1;
    for ($i = 0; $i <= $maxclass; $i++) {
        echo "<option value=$i>$i \n";
    }
    ?>
        </select>
    <?php echo $lang_forummanage['text_forum_order_note']?></td>
  </tr>

  <tr align="center">
    <td colspan="2"><input type="hidden" name="action" value="addforum"><input type="submit" name="Submit" value="<?php echo $lang_forummanage['submit_make_forum']?>" class=btn></td>
  </tr>
</table>
<?php
} else {
    ?>
<h2 class=transparentbg align=center><?php echo $lang_forummanage['text_forum_management']?></h2>
<table border=0 class=main cellspacing=0 cellpadding=5 width=1%><tr>
<td class=embedded align=left><form method="get" action="moforums.php"><input type="submit" value="<?php echo $lang_forummanage['submit_overforum_management']?>" class="btn"></form></td><td class=embedded align=left><form method="get" action="forummanage.php"><input type=hidden name="action" value="newforum"><input type="submit" value="<?php echo $lang_forummanage['submit_add_forum']?>" class="btn"></form></td>
</tr></table>
<?php
    echo '<table width="100%"  border="0" align="center" cellpadding="2" cellspacing="0">';
    echo '<tr><td class=colhead align=left>'.$lang_forummanage['col_name'].'</td><td class=colhead>'.$lang_forummanage['col_overforum'].'</td><td class=colhead>'.$lang_forummanage['col_read'].'</td><td class=colhead>'.$lang_forummanage['col_write'].'</td><td class=colhead>'.$lang_forummanage['col_create_topic'].'</td><td class=colhead>'.$lang_forummanage['col_moderator'].'</td><td class=colhead>'.$lang_forummanage['col_modify'].'</td></tr>';
    $forumListRows = NexusDB::select('SELECT forums.*, overforums.name AS of_name FROM forums LEFT JOIN overforums ON forums.forid=overforums.id ORDER BY forums.sort ASC');
    if (count($forumListRows) > 0) {
        foreach ($forumListRows as $row) {
            $name = $row['of_name'];
            $moderators = get_forum_moderators($row['id'], false);
            if (! $moderators) {
                $moderators = $lang_forummanage['text_not_available'];
            }
            echo '<tr><td><a href=forums.php?action=viewforum&forumid='.$row['id'].'><b>'.htmlspecialchars($row['name']).'</b></a><br />'.htmlspecialchars($row['description']).'</td>';
            echo '<td>'.htmlspecialchars($name).'</td><td>'.get_user_class_name($row['minclassread'], false, true, true).'</td><td>'.get_user_class_name($row['minclasswrite'], false, true, true).'</td><td>'.get_user_class_name($row['minclasscreate'], false, true, true).'</td><td>'.$moderators.'</td><td><b><a href="'.$PHP_SELF.'?action=editforum&id='.$row['id'].'">'.$lang_forummanage['text_edit']."</a>&nbsp;|&nbsp;<a href=\"javascript:confirm_delete('".$row['id']."', '".$lang_forummanage['js_sure_to_delete_forum']."', '');\"><font color=red>".$lang_forummanage['text_delete'].'</font></a></b></td></tr>';
        }
    } else {
        echo '<tr><td colspan=6>'.$lang_forummanage['text_no_records_found'].'</td></tr>';
    }
    echo '</table>';
}

end_main_frame();
stdfoot();
