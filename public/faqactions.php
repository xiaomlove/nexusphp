<?php

use Nexus\Database\NexusDB;

/*
+--------------------------------------------------------------------------
|   MySQL driven FAQ version 1.1 Beta
|   ========================================
|   by avataru
|   (c) 2002 - 2005 avataru
|   http://www.avataru.net
|   ========================================
|   Web: http://www.avataru.net
|   Release: 1/9/2005 1:03 AM
|   Email: avataru@avataru.net
|   Tracker: http://www.sharereactor.ro
+---------------------------------------------------------------------------
|
|   > FAQ Management actions
|   > Written by avataru
|   > Date started: 1/7/2005
|
+--------------------------------------------------------------------------
*/

require '../include/bittorrent.php';
dbconn();
loggedinorreturn();

if (get_user_class() < UC_ADMINISTRATOR) {
    stderr('Error', 'Only Administrators and above can modify the FAQ, sorry.');
}

function clear_faq_cache()
{
    NexusDB::cache_del('faq');
}
// stdhead("FAQ Management");

// ACTION: reorder - reorder sections and items
if (isset($_GET['action']) && $_GET['action'] == 'reorder') {
    foreach ($_POST['order'] as $id => $position) {
        NexusDB::table('faq')
            ->where('id', (int) $id)
            ->update(['order' => (int) $position]);
    }
    header('Location: '.get_protocol_prefix()."$BASEURL/faqmanage.php");
    exit;
}

// ACTION: edit - edit a section or item
elseif (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    stdhead('FAQ Management');
    begin_main_frame();
    echo '<h1 align="center">Edit Section or Item</h1>';

    $faqRow = NexusDB::table('faq')->where('id', (int) $_GET['id'])->first();
    $faqRows = $faqRow ? [(array) $faqRow] : [];
    foreach ($faqRows as $arr) {
        $arr['question'] = htmlspecialchars($arr['question']);
        $arr['answer'] = htmlspecialchars($arr['answer']);
        if ($arr['type'] == 'item') {
            $lang_id = $arr['lang_id'];
            echo '<form method="post" action="faqactions.php?action=edititem">';
            echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n";
            echo "<tr><td>ID:</td><td>{$arr['id']} <input type=\"hidden\" name=\"id\" value=\"{$arr['id']}\" /></td></tr>\n";
            echo "<tr><td>Question:</td><td><input style=\"width: 600px;\" type=\"text\" name=\"question\" value=\"{$arr['question']}\" /></td></tr>\n";
            echo "<tr><td style=\"vertical-align: top;\">Answer:</td><td><textarea rows=20 style=\"width: 600px; height=600px;\" name=\"answer\">{$arr['answer']}</textarea></td></tr>\n";
            if ($arr['flag'] == '0') {
                echo '<tr><td>Status:</td><td><select name="flag" style="width: 110px;"><option value="0" style="color: #FF0000;" selected="selected">Hidden</option><option value="1" style="color: #000000;">Normal</option><option value="2" style="color: #0000FF;">Updated</option><option value="3" style="color: #008000;">New</option></select></td></tr>';
            } elseif ($arr['flag'] == '2') {
                echo '<tr><td>Status:</td><td><select name="flag" style="width: 110px;"><option value="0" style="color: #FF0000;">Hidden</option><option value="1" style="color: #000000;">Normal</option><option value="2" style="color: #0000FF;" selected="selected">Updated</option><option value="3" style="color: #008000;">New</option></select></td></tr>';
            } elseif ($arr['flag'] == '3') {
                echo '<tr><td>Status:</td><td><select name="flag" style="width: 110px;"><option value="0" style="color: #FF0000;">Hidden</option><option value="1" style="color: #000000;">Normal</option><option value="2" style="color: #0000FF;">Updated</option><option value="3" style="color: #008000;" selected="selected">New</option></select></td></tr>';
            } else {
                echo '<tr><td>Status:</td><td><select name="flag" style="width: 110px;"><option value="0" style="color: #FF0000;">Hidden</option><option value="1" style="color: #000000;" selected="selected">Normal</option><option value="2" style="color: #0000FF;">Updated</option><option value="3" style="color: #008000;">New</option></select></td></tr>';
            }
            echo '<tr><td>Category:</td><td><select style="width: 400px;" name="categ" />';
            $catRows = NexusDB::table('faq')
                ->where('type', 'categ')
                ->where('lang_id', (int) $lang_id)
                ->orderBy('order')
                ->select(['id', 'question', 'link_id'])
                ->get();
            foreach ($catRows as $arr2) {
                $arr2 = (array) $arr2;
                $selected = ($arr2['link_id'] == $arr['categ']) ? ' selected="selected"' : '';
                echo "<option value=\"{$arr2['link_id']}\"".$selected.">{$arr2['question']}</option>";
            }
            echo "</td></tr>\n";
            echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"edit\" value=\"Edit\" style=\"width: 60px;\"></td></tr>\n";
            echo '</table>';
        } elseif ($arr['type'] == 'categ') {
            $lang_name = NexusDB::table('language')->where('id', (int) $arr['lang_id'])->value('lang_name');
            echo '<form method="post" action="faqactions.php?action=editsect">';
            echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n";
            echo "<tr><td>ID:</td><td>{$arr['id']} <input type=\"hidden\" name=\"id\" value=\"{$arr['id']}\" /></td></tr>\n";
            echo "<tr><td>Language:</td><td>$lang_name</td></tr>\n";
            echo "<tr><td>Title:</td><td><input style=\"width: 300px;\" type=\"text\" name=\"title\" value=\"{$arr['question']}\" /></td></tr>\n";
            if ($arr['flag'] == '0') {
                echo '<tr><td>Status:</td><td><select name="flag" style="width: 110px;"><option value="0" style="color: #FF0000;" selected="selected">Hidden</option><option value="1" style="color: #000000;">Normal</option></select></td></tr>';
            } else {
                echo '<tr><td>Status:</td><td><select name="flag" style="width: 110px;"><option value="0" style="color: #FF0000;">Hidden</option><option value="1" style="color: #000000;" selected="selected">Normal</option></select></td></tr>';
            }
            echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"edit\" value=\"Edit\" style=\"width: 60px;\"></td></tr>\n";
            echo '</table>';
        }
    }

    end_main_frame();
    stdfoot();
}

// subACTION: edititem - edit an item
elseif (isset($_GET['action']) && $_GET['action'] == 'edititem' && $_POST['id'] != null && $_POST['question'] != null && $_POST['answer'] != null && $_POST['flag'] != null && $_POST['categ'] != null) {
    $question = $_POST['question'];
    $answer = $_POST['answer'];
    NexusDB::table('faq')
        ->where('id', (int) $_POST['id'])
        ->update([
            'question' => (string) $question,
            'answer' => (string) $answer,
            'flag' => (int) $_POST['flag'],
            'categ' => (int) $_POST['categ'],
        ]);
    clear_faq_cache();
    header('Location: '.get_protocol_prefix()."$BASEURL/faqmanage.php");
    exit;
}

// subACTION: editsect - edit a section
elseif (isset($_GET['action']) && $_GET['action'] == 'editsect' && $_POST['id'] != null && $_POST['title'] != null && $_POST['flag'] != null) {
    $title = $_POST['title'];
    NexusDB::table('faq')
        ->where('id', (int) $_POST['id'])
        ->update([
            'question' => (string) $title,
            'answer' => '',
            'flag' => (int) $_POST['flag'],
            'categ' => 0,
        ]);
    clear_faq_cache();
    header('Location: '.get_protocol_prefix()."$BASEURL/faqmanage.php");
    exit;
}

// ACTION: delete - delete a section or item
elseif (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if ($_GET['confirm'] == 'yes') {
        NexusDB::table('faq')
            ->where('id', (int) ($_GET['id'] ?? 0))
            ->limit(1)
            ->delete();
        header('Location: '.get_protocol_prefix()."$BASEURL/faqmanage.php");
        exit;
    } else {
        stdhead('FAQ Management');
        begin_main_frame();
        echo '<h1 align="center">Confirmation required</h1>';
        $id = intval($_GET['id'] ?? 0);
        echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" align=\"center\" width=\"95%\">\n<tr><td align=\"center\">Please click <a href=\"faqactions.php?action=delete&id={$id}&confirm=yes\">here</a> to confirm.</td></tr>\n</table>\n";
        end_main_frame();
        stdfoot();
    }
}

// ACTION: additem - add a new item
elseif (isset($_GET['action']) && $_GET['action'] == 'additem' && $_GET['inid'] && $_GET['langid']) {
    stdhead('FAQ Management');
    begin_main_frame();
    echo '<h1 align="center">Add Item</h1>';
    echo '<form method="post" action="faqactions.php?action=addnewitem">';
    echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n";
    echo "<tr><td>Question:</td><td><input style=\"width: 600px;\" type=\"text\" name=\"question\" value=\"\" /></td></tr>\n";
    echo "<tr><td style=\"vertical-align: top;\">Answer:</td><td><textarea rows=20 style=\"width: 600px; height=600px;\" name=\"answer\"></textarea></td></tr>\n";
    echo '<tr><td>Status:</td><td><select name="flag" style="width: 110px;"><option value="0" style="color: #FF0000;">Hidden</option><option value="1" style="color: #000000;">Normal</option><option value="2" style="color: #0000FF;">Updated</option><option value="3" style="color: #008000;" selected="selected">New</option></select></td></tr>';
    echo '<input type=hidden name=categ value="'.(intval($_GET['inid'] ?? 0)).'">';
    echo '<input type=hidden name=langid value="'.(intval($_GET['langid'] ?? 0)).'">';
    echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"Add\" style=\"width: 60px;\"></td></tr>\n";
    echo '</table></form>';
    end_main_frame();
    stdfoot();
}

// ACTION: addsection - add a new section
elseif (isset($_GET['action']) && $_GET['action'] == 'addsection') {
    stdhead('FAQ Management');
    begin_main_frame();
    echo '<h1 align="center">Add Section</h1>';
    echo '<form method="post" action="faqactions.php?action=addnewsect">';
    echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n";
    echo "<tr><td>Title:</td><td><input style=\"width: 300px;\" type=\"text\" name=\"title\" value=\"\" /></td></tr>\n";
    $s = '<select name=language>';
    $langs = langlist('rule_lang');
    foreach ($langs as $row) {
        if ($row['site_lang_folder'] == $deflang) {
            $se = ' selected';
        } else {
            $se = '';
        }
        $s .= '<option value='.$row['id'].$se.'>'.htmlspecialchars($row['lang_name'])."</option>\n";
    }
    $s .= '</select>';
    echo '<tr><td>Language:</td><td>'.$s.'</td></tr>';
    echo '<tr><td>Status:</td><td><select name="flag" style="width: 110px;"><option value="0" style="color: #FF0000;">Hidden</option><option value="1" style="color: #000000;" selected="selected">Normal</option></select></td></tr>';
    echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"edit\" value=\"Add\" style=\"width: 60px;\"></td></tr>\n";
    echo '</table>';
    end_main_frame();
    stdfoot();
}

// subACTION: addnewitem - add a new item to the db
elseif (isset($_GET['action']) && $_GET['action'] == 'addnewitem' && $_POST['question'] != null && $_POST['answer'] != null) {
    $question = $_POST['question'];
    $answer = $_POST['answer'];
    $categ = intval($_POST['categ'] ?? 0);
    $langid = intval($_POST['langid'] ?? 0);
    $maxRow = NexusDB::table('faq')
        ->where('type', 'item')
        ->where('categ', (int) $categ)
        ->where('lang_id', (int) $langid)
        ->selectRaw('MAX(`order`) AS maxorder, MAX(`link_id`) AS maxlinkid')
        ->first();
    $maxRow = $maxRow ? (array) $maxRow : ['maxorder' => 0, 'maxlinkid' => 0];
    $order = (int) $maxRow['maxorder'] + 1;
    $link_id = (int) $maxRow['maxlinkid'] + 1;
    NexusDB::insert('faq', [
        'link_id' => (int) $link_id,
        'type' => 'item',
        'lang_id' => (int) $langid,
        'question' => (string) $question,
        'answer' => (string) $answer,
        'flag' => (int) ($_POST['flag'] ?? 0),
        'categ' => (int) $categ,
        'order' => (int) $order,
    ]);
    clear_faq_cache();
    header('Location: '.get_protocol_prefix()."$BASEURL/faqmanage.php");
    exit;
}

// subACTION: addnewsect - add a new section to the db
elseif (isset($_GET['action']) && $_GET['action'] == 'addnewsect' && $_POST['title'] != null && $_POST['flag'] != null) {
    $title = $_POST['title'];
    $language = intval($_POST['language'] ?? 0);
    $maxRow = NexusDB::table('faq')
        ->where('type', 'categ')
        ->where('lang_id', (int) $language)
        ->selectRaw('MAX(`order`) AS maxorder, MAX(`link_id`) AS maxlinkid')
        ->first();
    $maxRow = $maxRow ? (array) $maxRow : ['maxorder' => 0, 'maxlinkid' => 0];
    $order = (int) $maxRow['maxorder'] + 1;
    $link_id = (int) $maxRow['maxlinkid'] + 1;
    NexusDB::insert('faq', [
        'link_id' => (int) $link_id,
        'type' => 'categ',
        'lang_id' => (int) $language,
        'question' => (string) $title,
        'answer' => '',
        'flag' => (int) $_POST['flag'],
        'categ' => 0,
        'order' => (int) $order,
    ]);
    clear_faq_cache();
    header('Location: '.get_protocol_prefix()."$BASEURL/faqmanage.php");
    exit;
} else {
    header('Location: '.get_protocol_prefix()."$BASEURL/faqmanage.php");
    exit;
}
