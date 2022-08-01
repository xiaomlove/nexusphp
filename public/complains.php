<?php
require '../include/bittorrent.php';
dbconn();
require get_langfile_path();

$isLogin = isset($CURUSER['id']);
$isAdmin = get_user_class() >= $staffmem_class;

if($isLogin && !$isAdmin) {
    permissiondenied();
}
$uid = $CURUSER['id'] ?? 0;
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    switch($action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS)){
        case 'new':
            cur_user_check();
            check_code ($_POST['imagehash'], $_POST['imagestring'],'complains.php');
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            $body = filter_input(INPUT_POST, 'body', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if(empty($email) || empty($body)) stderr($lang_functions['std_error'], $lang_complains['text_new_failure']);
            sql_query(sprintf('INSERT INTO complains (uuid, email, body, added) VALUES (UUID(), %s, %s, NOW())', sqlesc($email), sqlesc($body))) or sqlerr(__FILE__, __LINE__);
            $Cache->delete_value('COMPLAINTS_COUNT_CACHE');
            nexus_redirect(sprintf('complains.php?action=view&id=%s', get_single_value('complains', 'uuid', 'WHERE id = ' . mysql_insert_id())));
            break;
        case 'reply':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $body = filter_input(INPUT_POST, 'body', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $complain = \App\Models\Complain::query()->findOrFail($id);
            if(empty($id) || empty($body)) stderr($lang_functions['std_error'], $lang_complains['text_new_failure']);
            sql_query(sprintf('INSERT INTO complain_replies (complain, userid, added, body) VALUES (%u, %u, NOW(), %s)', $id, $uid, sqlesc($body))) or sqlerr(__FILE__, __LINE__);
            if ($uid > 0) {
                try {
                    $toolRep = new \App\Repositories\ToolRepository();
                    $toolRep->sendMail($complain->email, $lang_complains['reply_notify_subject'], sprintf($lang_complains['reply_notify_body'], get_setting('basic.SITENAME'), getSchemeAndHttpHost() . '/complains.php?action=view&id=' . $complain->uuid));
                } catch (\Exception $exception) {
                    do_log($exception->getMessage(), 'error');
                }
            }
            nexus_redirect($_SERVER['HTTP_REFERER']);
            break;
        case 'answered':
        case 'unanswered':
            if(!$isAdmin) permissiondenied();
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if(!$id) permissiondenied();
            sql_query(sprintf('UPDATE complains SET answered = %u WHERE id = %u', $action == 'answered' ? 1 : 0, $id)) or sqlerr(__FILE__, __LINE__);
            $Cache->delete_value('COMPLAINTS_COUNT_CACHE');
            nexus_redirect($_SERVER['HTTP_REFERER']);
            break;
        default:
            permissiondenied();
    }
}else{
    switch (filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS)){
        case 'list':
            if(!$isAdmin) permissiondenied();
            $showTable = function($res){
                global $lang_complains;
                echo '<table width="100%">';
                echo EchoRow('colhead', $lang_complains['th_complain_at'], $lang_complains['th_complain_account'], $lang_complains['th_action_view']);
                while($row = mysql_fetch_assoc($res)){
                    echo EchoRow('rowfollow', gettime($row['added']), htmlspecialchars($row['email']), sprintf('<a href="?action=view&id=%s" class="faqlink">%s</a>', $row['uuid'], $lang_complains['th_action_view']));
                }
                echo '</table>';
            };
            stdhead($lang_complains['text_complain']);
            begin_main_frame();
            if(!isset($_GET['page'])){
                $res = sql_query('SELECT added, uuid, email FROM complains WHERE answered = 0 ORDER BY id DESC') or sqlerr(__FILE__, __LINE__);
                begin_frame($lang_complains['pending_complaints']);
                if(mysql_num_rows($res)){
                    $showTable($res);
                }else{
                    echo $lang_complains['no_pending_complaints'];
                }
                end_frame();
            }
            begin_frame($lang_complains['complaints_processed']);
            list($pagertop, $pagerbottom, $limit) = pager(20, get_row_count('complains', 'WHERE answered = 1'), '?action=list&');
            $res = sql_query('SELECT added, uuid, email FROM complains WHERE answered = 1 ORDER BY id DESC ' . $limit) or sqlerr(__FILE__, __LINE__);
            if(mysql_num_rows($res)){
                echo $pagertop;
                $showTable($res);
                echo $pagerbottom;
            }else{
                echo $lang_complains['no_complaints_have_been_processed'];
            }
            end_frame();
            end_main_frame();
            stdfoot();
            break;
        case 'view':
            $uuid = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if(strlen($uuid) != 36) permissiondenied();
            $res = sql_query(sprintf('SELECT * FROM complains WHERE uuid = %s', sqlesc($uuid))) or sqlerr(__FILE__, __LINE__);
            $complain = mysql_fetch_assoc($res);
            if(!$complain) permissiondenied();
            $user = \App\Models\User::query()->where('email', $complain['email'])->first();
            stdhead($lang_complains['text_complain']);
            begin_main_frame();
            if(!$isLogin){
                begin_frame($lang_complains['text_created_title']);
                printf('<p style="font-weight: bold; color: red">%s</p>', $lang_complains['text_created_note']);
                end_frame();
            }
            begin_frame($lang_complains['text_new_body']);
            printf('%s：%s<br />%s %s', $lang_complains['text_added'], gettime($complain['added']), $lang_complains['text_new_email'], htmlspecialchars($complain['email']));
            if($isAdmin) {
                printf(' [<a href="usersearch.php?em=%s" class="faqlink" target="_blank">%s</a>]', urlencode($complain['email']), $lang_complains['text_search_account']);
                if ($user) {
                    printf(' [<a href="user-ban-log.php?q=%s" class="faqlink" target="_blank">%s</a>]', urlencode($user->username), $lang_complains['text_view_band_log']);
                }
            }
            echo '<hr />', format_comment($complain['body']);
            end_frame();
            // REPLIES
            begin_frame($lang_complains['text_replies']);
            $res = sql_query(sprintf('SELECT * FROM `complain_replies` WHERE complain = %u ORDER BY id DESC', $complain['id'])) or sqlerr(__FILE__, __LINE__);
            if(mysql_num_rows($res)){
                while($row = mysql_fetch_assoc($res)){
                    printf('<b>%s @ %s</b>: ', $row['userid'] ? get_plain_username($row['userid']) : $lang_complains['text_complainer'], gettime($row['added']));
                    echo format_comment($row['body']) . '<hr />';
                }
            }else{
                printf('<p align="center">%s</p>', $lang_complains['text_no_replies']);
            }
            end_frame();
            // NEW REPLY
            if($complain['answered']){
                printf('<p align="center">%s</p>', $lang_complains['text_closed']);
            }else{
                printf('<br /><br /><table style="border:1px solid #000000;" align="center"><tr><td class="text" align="center"><b>%s</b><br /><br /><form id="reply" method="post" action="" onsubmit="return postvalid(this);"><input type="hidden" name="action" value="reply" /><input type="hidden" name="id" value="%u" /><br />', $lang_complains['text_reply'], $complain['id']);
                quickreply('reply', 'body', $lang_complains['text_reply']);
                echo '</form></td></tr></table>';
            }
            if($isAdmin){
                printf('<form action="" method="post" style="text-align: center; margin-top: 2em"><input type="hidden" name="action" value="%s" /><input type="hidden" name="id" value="%u" /><button>%s</button></form>', $complain['answered'] ? 'unanswered' : 'answered', $complain['id'],$complain['answered'] ? $lang_complains['text_unanswer_it'] : $lang_complains['text_answer_it']);
            }
            end_main_frame();
            stdfoot();
            break;
        case 'compose':
        default:
            cur_user_check();
            stdhead($lang_complains['text_complain']);
            ?>
            <h2><?= $lang_complains['text_new_complain'] ?></h2>
            <form action="" method="post">
                <input type="hidden" name="action" value="new" />
                <table border="0" cellpadding="5">
                    <tr><td class="rowhead"><?php echo $lang_complains['text_new_email']?></td><td class="rowfollow" align="left"><input type="email" name="email" style="width: 180px; border: 1px solid gray" /></td></tr>
                    <tr><td class="rowhead"><?php echo $lang_complains['text_new_body']?></td><td class="rowfollow" align="left"><textarea name="body" style="width: 200px; height: 250px" placeholder="<?= $lang_complains['text_new_body_placeholder'] ?>"></textarea></td></tr>
                    <?php show_image_code (); ?>
                    <tr><td class="toolbox" colspan="2" align="center"><input type="submit" value="<?= $lang_complains['text_new_submit']?>" class="btn" /></td></tr>
                </table>
            </form>
            <?php
            stdfoot();
    }
}
