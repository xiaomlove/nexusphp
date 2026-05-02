<?php

use App\Models\Message;
use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
require_once get_langfile_path();
// require_once(get_langfile_path("",true));
loggedinorreturn();

if (! isset($CURUSER)) {
    stderr($lang_subtitles['std_error'], $lang_subtitles['std_must_login_to_upload']);
}

stdhead($lang_subtitles['head_subtitles']);

$in_detail = $_POST['in_detail'] ?? '';
$detail_torrent_id = intval($_POST['detail_torrent_id'] ?? 0);
$torrent_name = $_POST['torrent_name'] ?? '';

function isInteger($n)
{
    if (preg_match('/[^0-^9]+/', $n) > 0) {
        return false;
    }

    return true;
}

$act = intval($_GET['act'] ?? 0);
$search = trim($_GET['search'] ?? '');
$letter = trim($_GET['letter'] ?? '');
if (strlen($letter) > 1) {
    exit;
}
if ($letter == '' || strpos('abcdefghijklmnopqrstuvwxyz', $letter) === false) {
    $letter = '';
}

$lang_id = intval($_GET['lang_id'] ?? 0);
if (! is_valid_id($lang_id)) {
    $lang_id = '';
}

$query = '';
if ($search != '') {
    $query = 'subs.title LIKE '.DB::getPdo()->quote("%$search%").'';
    if ($search) {
        $q = 'search='.rawurlencode($search);
    }
} elseif ($letter != '') {
    $query = 'subs.title LIKE '.DB::getPdo()->quote("$letter%");
    $q = "letter=$letter";
}

if ($lang_id) {
    $query .= ($query ? ' AND ' : '').'subs.lang_id = '.(int) $lang_id;
    $q = ($q ? $q.'&amp;' : '').'lang_id='.(int) $lang_id;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload' && ($in_detail != 'in_detail')) {
    // start process upload file
    $file = $_FILES['file'];

    if (! $file || $file['size'] == 0 || $file['name'] == '') {
        echo $lang_subtitles['std_nothing_received'];
        exit;
    }

    if ($file['size'] > $maxsubsize_main && $maxsubsize_main > 0) {
        echo $lang_subtitles['std_subs_too_big'];
        exit;
    }

    $accept_ext = ['sub' => 'sub', 'srt' => 'srt', 'zip' => 'zip', 'rar' => 'rar', 'ace' => 'ace', 'txt' => 'txt', 'SUB' => 'SUB', 'SRT' => 'SRT', 'ZIP' => 'ZIP', 'RAR' => 'RAR', 'ACE' => 'ACE', 'TXT' => 'TXT', 'ssa' => 'ssa', 'ass' => 'ass', 'cue' => 'cue'];
    $ext_l = strrpos($file['name'], '.');
    $ext = strtolower(substr($file['name'], $ext_l + 1, strlen($file['name']) - ($ext_l + 1)));

    if (! array_key_exists($ext, $accept_ext)) {
        echo $lang_subtitles['std_wrong_subs_format'];
        exit;
    }

    /*
    if (file_exists("$SUBSPATH/$file[name]"))
    {
        echo($lang_subtitles['std_file_already_exists']);
        exit;
    }
    */

    // end process upload file

    // start process torrent ID
    if (! $_POST['torrent_id']) {
        echo $lang_subtitles['std_missing_torrent_id'].htmlspecialchars($file['name']).'</b></font> !';
        exit;
    } else {
        $torrent_id = $_POST['torrent_id'];
        if (! is_numeric($_POST['torrent_id']) || ! isInteger($_POST['torrent_id'])) {
            echo $lang_subtitles['std_invalid_torrent_id'];
            exit;
        }

        $r_a = NexusDB::table('torrents')->where('id', (int) $torrent_id)->first();
        if (! $r_a) {
            echo $lang_subtitles['std_invalid_torrent_id'];
            exit;
        } else {
            $r_a = (array) $r_a;
            if ($r_a['owner'] != $CURUSER['id'] && ! user_can('uploadsub')) {
                echo $lang_subtitles['std_no_permission_uploading_others'];
                exit;
            }
        }
    }
    // end process torrent ID

    // start process title
    $title = trim($_POST['title']);
    if ($title == '') {
        $title = substr($file['name'], 0, strrpos($file['name'], '.'));
        if (! $title) {
            $title = $file['name'];
        }

        $file['name'] = str_replace(' ', '_', htmlspecialchars("$file[name]"));
    }

    /*
    $r = sql_query("SELECT id FROM subs WHERE title=" . sqlesc($title)) or sqlerr(__FILE__, __LINE__);
    if (mysql_num_rows($r) > 0)
    {
        echo($lang_subtitles['std_file_same_name_exists']."<font color=red><b>" . htmlspecialchars($title) . "</b></font> ");
        exit;
    }
    */
    // end process title

    // start process language
    if ($_POST['sel_lang'] == 0) {
        echo $lang_subtitles['std_must_choose_language'];
        exit;
    } else {
        $lang_id = $_POST['sel_lang'];
    }
    // end process language

    if (isset($_POST['uplver']) && $_POST['uplver'] == 'yes' && user_can('beanonymous')) {
        $anonymous = 'yes';
        $anon = 'Anonymous';
    } else {
        $anonymous = 'no';
        $anon = $CURUSER['username'];
    }

    // $file["name"] = str_replace("", "_", htmlspecialchars("$file[name]"));
    // $file["name"] = preg_replace('/[^a-z0-9_\-\.]/i', '_', $file[name]);

    // make_folder($SUBSPATH."/",$detail_torrent_id);
    // stderr("",$file["name"]);

    $arr = NexusDB::table('language')
        ->where('sub_lang', 1)
        ->where('id', (int) $lang_id)
        ->select(['lang_name'])
        ->first();
    $arr = $arr ? (array) $arr : ['lang_name' => ''];

    $filename = $file['name'];
    $added = date('Y-m-d H:i:s');
    $uppedby = $CURUSER['id'];
    $size = $file['size'];

    $id = (int) NexusDB::insert('subs', [
        'torrent_id' => (int) $torrent_id,
        'lang_id' => (int) $lang_id,
        'title' => (string) $title,
        'filename' => (string) $filename,
        'added' => (string) $added,
        'uppedby' => (int) $uppedby,
        'anonymous' => (string) $anonymous,
        'size' => (int) $size,
        'ext' => (string) $ext,
    ]);

    // stderr("",make_folder($SUBSPATH."/",$torrent_id). "/" . $id . "." .$ext);
    if (! move_uploaded_file($file['tmp_name'], make_folder($SUBSPATH.'/', $torrent_id).'/'.$id.'.'.$ext)) {
        echo $lang_subtitles['std_failed_moving_file'];
    }

    KPS('+', $uploadsubtitle_bonus, $uppedby); // subtitle uploader gets bonus

    write_log("$arr[lang_name] Subtitle $id ($title) was uploaded by $anon");
    $msg_bt = "$arr[lang_name] Subtitle $id ($title) was uploaded by $anon, Download: ".get_protocol_prefix()."$BASEURL/downloadsubs.php/".$file['name'].'';
}

if (user_can('delownsub')) {
    $delete = intval($_GET['delete'] ?? 0);
    if (is_valid_id($delete)) {
        $a = NexusDB::table('subs')
            ->where('id', (int) $delete)
            ->select(['id', 'torrent_id', 'ext', 'lang_id', 'title', 'filename', 'uppedby', 'anonymous'])
            ->first();
        if ($a) {
            $a = (array) $a;
            if (user_can('submanage') || $a['uppedby'] == $CURUSER['id']) {
                $sure = intval($_GET['sure'] ?? 0);
                if ($sure == 1) {
                    $reason = $_POST['reason'];
                    $filename = getFullDirectory("$SUBSPATH/$a[torrent_id]/$a[id].$a[ext]");
                    do_log("Going to delete subtitle: $filename ...");
                    if (! @unlink($filename)) {
                        do_log("Delete subtitle: $filename fail.", 'error');
                        stdmsg($lang_subtitles['std_error'], $lang_subtitles['std_this_file']."$a[filename]".$lang_subtitles['std_is_invalid']);
                        stdfoot();
                        exit;
                    } else {
                        NexusDB::table('subs')->where('id', (int) $delete)->delete();
                        KPS('-', $uploadsubtitle_bonus, $a['uppedby']); // subtitle uploader loses bonus for deleted subtitle
                    }
                    if ($CURUSER['id'] != $a['uppedby']) {
                        $locale = get_user_locale($a['uppedby']);
                        $msg = $CURUSER['username'].nexus_trans('subtitle.msg_deleted_your_sub', [], $locale).$a['title'].($reason != '' ? nexus_trans('subtitle.msg_reason_is', [], $locale).$reason : '');
                        $subject = nexus_trans('subtitle.msg_your_sub_deleted', [], $locale);
                        $time = date('Y-m-d H:i:s');
                        Message::add([
                            'sender' => 0,
                            'receiver' => $a['uppedby'],
                            'added' => now(),
                            'msg' => $msg,
                            'subject' => $subject,
                        ]);
                    }
                    $arr = NexusDB::table('language')
                        ->where('sub_lang', 1)
                        ->where('id', (int) $a['lang_id'])
                        ->select(['lang_name'])
                        ->first();
                    $arr = $arr ? (array) $arr : ['lang_name' => ''];
                    write_log("$arr[lang_name] Subtitle $delete ($a[title]) was deleted by ".(($a['anonymous'] == 'yes' && $a['uppedby'] == $CURUSER['id']) ? 'Anonymous' : $CURUSER['username']).($a['uppedby'] != $CURUSER['id'] ? ', Mod Delete' : '').($reason != '' ? ' ('.$reason.')' : ''));
                } else {
                    stdmsg($lang_subtitles['std_delete_subtitle'], $lang_subtitles['std_delete_subtitle_note']."<br /><form method=post action=subtitles.php?delete=$delete&sure=1>".$lang_subtitles['text_reason_is'].'<input type=text style="width: 200px" name=reason><input type=submit value="'.$lang_subtitles['submit_confirm'].'"></form>');
                    stdfoot();
                    exit;
                }
            }
        }
    }
}

if (get_user_class() >= UC_PEASANT) {
    // $url = $_COOKIE["subsurl"];

    begin_main_frame();

    ?>
<div align=center>
<?php
    if (! $size = $Cache->get_value('subtitle_sum_size')) {
        $size = (int) (NexusDB::table('subs')->sum('size') ?? 0);
        $Cache->cache_value('subtitle_sum_size', $size, 3600);
    }

    begin_frame($lang_subtitles['text_upload_subtitles'].mksize($size).'', true, 10, '100%', 'center');
    ?>
	</div>
<?php

    echo '<p align=left><b><font size=5>'.$lang_subtitles['text_rules']."</font></b></p>\n";
    echo '<p align=left>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp '.$lang_subtitles['text_rule_one']."</p>\n";
    echo '<p align=left>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp '.$lang_subtitles['text_rule_two']."</p>\n";
    echo '<p align=left>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp '.$lang_subtitles['text_rule_three']."</p>\n";
    echo '<p align=left>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp '.$lang_subtitles['text_rule_four']."</p>\n";
    echo '<p align=left>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp '.$lang_subtitles['text_rule_five']."</p>\n";
    echo '<p align=left>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp '.$lang_subtitles['text_rule_six']."</p>\n";

    echo $lang_subtitles['text_red_star_required'];
    if ($in_detail != '') {
        echo '<p >'.$lang_subtitles['text_uploading_subtitles_for_torrent']."<b>$torrent_name</b></p>\n";
        echo '<br />';
    }

    echo "<form enctype=multipart/form-data method=post action=?>\n";
    echo '<input type=hidden name=action value=upload>';
    echo "<table class=main border=1 cellspacing=0 cellpadding=5>\n";

    echo '<tr><td class=rowhead>'.$lang_subtitles['row_file'].'<font color=red>*</font></td><td class=rowfollow align=left><input type=file name=file>';
    if ($maxsubsize_main > 0) {
        echo '<br />('.$lang_subtitles['text_maximum_file_size'].mksize($maxsubsize_main).'.)';
    }
    echo "</td></tr>\n";
    if ($in_detail == '') {
        echo '<tr><td class=rowhead>'.$lang_subtitles['row_torrent_id'].'<font color=red>*</font></td><td class=rowfollow align=left><input type=text name=torrent_id style="width:300px"><br />'.sprintf($lang_subtitles['text_torrent_id_note'], getSchemeAndHttpHost())."</td></tr>\n";
    } else {
        echo '<tr><td class=rowhead>'.$lang_subtitles['row_torrent_id']."<font color=red>*</font></td><td class=rowfollow align=left><input type=text name=torrent_id value=$detail_torrent_id style=\"width:300px\"><br />".$lang_subtitles['text_torrent_id_note']."</td></tr>\n";
        $in_detail = '';
    }
    echo '<tr><td class=rowhead>'.$lang_subtitles['row_title'].'</td><td class=rowfollow colspan=3 align=left><input type=text name=title style="width:300px"><br />'.$lang_subtitles['text_title_note']."</td></tr>\n";

    $s = '<tr><td class=rowhead>'.$lang_subtitles['row_language'].'<font color=red>*</font></td><td class=rowfollow align=left><select name="sel_lang"><option value="0">'.$lang_subtitles['select_choose_one']."</option>\n";

    $langs = langlist('sub_lang');

    foreach ($langs as $row) {
        $s .= '<option value="'.$row['id'].'">'.htmlspecialchars($row['lang_name'])."</option>\n";
    }
    $s .= '</select></td></tr>';

    echo $s;

    if (user_can('beanonymous')) {
        tr($lang_subtitles['row_show_uploader'], '<input type=checkbox name=uplver value=yes>'.$lang_subtitles['hide_uploader_note'], 1);
    }

    echo '<tr><td class=toolbox colspan=2 align=center><input type=submit class=btn value='.$lang_subtitles['submit_upload_file'].'> <input type=reset class=btn value="'.$lang_subtitles['submit_reset']."\"></td></tr>\n";
    echo "</table>\n";
    echo "</form>\n";
    end_frame();

    end_main_frame();
}

if (get_user_class() >= UC_PEASANT) {
    echo "<form method=get action=?>\n";
    echo '<br /><br />';
    echo "<input type=text style=\"width:200px\" name=search>\n";

    $s = '<select name="lang_id"><option value="0">'.$lang_subtitles['select_all_languages']."</option>\n";
    $langs = langlist('sub_lang');
    foreach ($langs as $row) {
        $s .= '<option value="'.$row['id'].'">'.htmlspecialchars($row['lang_name'])."</option>\n";
    }
    $s .= '</select>';
    echo $s;

    echo '<input type=submit class=btn value="'.$lang_subtitles['submit_search']."\">\n";
    echo "</form>\n";

    for ($i = 97; $i < 123; $i++) {
        $l = chr($i);
        $L = chr($i - 32);
        if ($l == $letter) {
            echo "<b><font class=gray>$L</font></b>\n";
        } else {
            echo "<a href=?letter=$l><b>$L</b></a>\n";
        }
    }

    $perpage = 30;
    $query = ($query ? ' WHERE '.$query : '');
    $num = (int) (NexusDB::select("SELECT COUNT(*) AS c FROM subs $query")[0]['c'] ?? 0);
    if (! $num) {
        stdmsg($lang_subtitles['text_sorry'], $lang_subtitles['text_nothing_here']);
        stdfoot();
        exit;
    }
    [$pagertop, $pagerbottom, $limit] = pager($perpage, $num, 'subtitles.php?'.$q.'&');

    echo $pagertop;

    $i = 0;
    $subRows = NexusDB::select("SELECT subs.*, language.flagpic, language.lang_name FROM subs LEFT JOIN language ON subs.lang_id=language.id $query ORDER BY id DESC $limit");

    echo "<table width=940 border=1 cellspacing=0 cellpadding=5>\n";
    echo '<tr><td class=colhead>'.$lang_subtitles['col_lang'].'</td><td width=100% class=colhead align=center>'.$lang_subtitles['col_title'].'</td><td class=colhead align=center><img class="time" src="pic/trans.gif" alt="time" title="'.$lang_subtitles['title_date_added'].'" /></td>
		<td class=colhead align=center><img class="size" src="pic/trans.gif" alt="size" title="'.$lang_subtitles['title_size'].'" /></td><td class=colhead align=center>'.$lang_subtitles['col_hits'].'</td><td class=colhead align=center>'.$lang_subtitles['col_upped_by'].'</td><td class=colhead align=center>'.$lang_subtitles['col_report']."</td></tr>\n";

    $mod = user_can('submanage');
    $pu = user_can('delownsub');

    foreach ($subRows as $arr) {
        // the number $start_subid is just for legacy support of prevoiusly uploaded subs, if the site is completely new, it should be 0 or just remove it
        $lang = '<td class=rowfollow align=center valign=middle>'.'<img border="0" src="pic/flag/'.$arr['flagpic'].'" alt="'.$arr['lang_name'].'" title="'.$arr['lang_name'].'"/>'."</td>\n";
        $title = '<td class=rowfollow align=left><a href="'.(isset($start_subid) && $arr['id'] <= $start_subid ? 'downloadsubs_legacy.php/'.$arr['filename'] : 'downloadsubs.php?torrentid='.$arr['torrent_id'].'&subid='.$arr['id']).'"<b>'.htmlspecialchars($arr['title']).'</b></a>'.
        ($mod || ($pu && $arr['uppedby'] == $CURUSER['id']) ? " <font class=small><a href=?delete=$arr[id]>".$lang_subtitles['text_delete'].'</a></font>' : '')."</td>\n";
        $addtime = gettime($arr['added'], false, false);
        $added = '<td class=rowfollow align=center><nobr>'.$addtime."</nobr></td>\n";
        $size = '<td class=rowfollow align=center>'.mksize_loose($arr['size'])."</td>\n";
        $hits = '<td class=rowfollow align=center>'.number_format($arr['hits'])."</td>\n";
        $uppedby = '<td class=rowfollow align=center>'.($arr['anonymous'] == 'yes' ? $lang_subtitles['text_anonymous'].(user_can('viewanonymous') ? '<br />'.get_username($arr['uppedby'], false, true, true, false, true) : '') : get_username($arr['uppedby']))."</td>\n";
        $report = "<td class=rowfollow align=center><a href=\"report.php?subtitle=$arr[id]\"><img class=\"f_report\" src=\"pic/trans.gif\" alt=\"Report\" title=\"".$lang_subtitles['title_report_subtitle']."\" /></a></td>\n";
        echo '<tr>'.$lang.$title.$added.$size.$hits.$uppedby.$report."</tr>\n";
        $i++;
    }

    echo "</table>\n";
    echo $pagerbottom;
}

stdfoot();
?>
