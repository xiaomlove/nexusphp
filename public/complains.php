<?php

use App\Models\Complain;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\ToolRepository;
use Nexus\Database\NexusDB;
use Nexus\Database\NexusLock;

require '../include/bittorrent.php';
dbconn();
require get_langfile_path();

$isLogin = isset($CURUSER['id']);
$isAdmin = user_can('staffmem');

if ($isLogin && ! $isAdmin) {
    permissiondenied();
}
if (! $isAdmin && ! Setting::getIsComplainEnabled()) {
    stderr($lang_functions['std_error'], $lang_complains['complain_not_enabled']);
}

$uid = $CURUSER['id'] ?? 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
        case 'new':
            cur_user_check();
            check_code($_POST['imagehash'] ?? null, $_POST['imagestring'] ?? null, 'complains.php');
            NexusLock::lockOrFail('complains:lock:'.getip(), 10);
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            NexusLock::lockOrFail('complains:lock:'.$email, 600);
            $body = filter_input(INPUT_POST, 'body', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (empty($email) || empty($body)) {
                stderr($lang_functions['std_error'], $lang_complains['text_new_failure']);
            }
            $user = User::query()->where('email', $email)->where('enabled', 'no')->first();
            if (! $user) {
                stderr($lang_functions['std_error'], $lang_complains['text_new_failure']);
            }
            $newId = (int) NexusDB::insert('complains', [
                'uuid' => NexusDB::raw('UUID()'),
                'email' => (string) $email,
                'body' => (string) $body,
                'added' => NexusDB::raw('NOW()'),
                'ip' => (string) getip(),
            ]);
            $Cache->delete_value('COMPLAINTS_COUNT_CACHE');
            $newUuid = NexusDB::table('complains')->where('id', $newId)->value('uuid');
            nexus_redirect(sprintf('complains.php?action=view&id=%s', $newUuid));
            break;
        case 'reply':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $body = filter_input(INPUT_POST, 'body', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $complain = Complain::query()->findOrFail($id);
            if (empty($id) || empty($body)) {
                stderr($lang_functions['std_error'], $lang_complains['text_new_failure']);
            }
            NexusDB::insert('complain_replies', [
                'complain' => (int) $id,
                'userid' => (int) $uid,
                'added' => NexusDB::raw('NOW()'),
                'body' => (string) $body,
                'ip' => (string) getip(),
            ]);
            if ($uid > 0) {
                try {
                    $toolRep = new ToolRepository;
                    $toolRep->sendMail($complain->email, $lang_complains['reply_notify_subject'], sprintf($lang_complains['reply_notify_body'], get_setting('basic.SITENAME'), getSchemeAndHttpHost().'/complains.php?action=view&id='.$complain->uuid));
                } catch (Exception $exception) {
                    do_log($exception->getMessage(), 'error');
                }
            }
            nexus_redirect($_SERVER['HTTP_REFERER']);
            break;
        case 'answered':
        case 'unanswered':
            if (! $isAdmin) {
                permissiondenied();
            }
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (! $id) {
                permissiondenied();
            }
            NexusDB::table('complains')
                ->where('id', (int) $id)
                ->update(['answered' => $action == 'answered' ? 1 : 0]);
            $Cache->delete_value('COMPLAINTS_COUNT_CACHE');
            nexus_redirect($_SERVER['HTTP_REFERER']);
            break;
        default:
            permissiondenied();
    }
} else {
    switch (filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
        case 'list':
            if (! $isAdmin) {
                permissiondenied();
            }
            $showTable = function ($rows) {
                global $lang_complains;
                echo '<table width="100%">';
                echo EchoRow('colhead', $lang_complains['th_complain_at'], $lang_complains['th_complain_account'], $lang_complains['th_action_view']);
                foreach ($rows as $row) {
                    $row = (array) $row;
                    echo EchoRow('rowfollow', gettime($row['added']), htmlspecialchars($row['email']), sprintf('<a href="?action=view&id=%s" class="faqlink">%s</a>', $row['uuid'], $lang_complains['th_action_view']));
                }
                echo '</table>';
            };
            stdhead($lang_complains['text_complain']);
            begin_main_frame();
            if (! isset($_GET['page'])) {
                $rows = NexusDB::table('complains')
                    ->where('answered', 0)
                    ->orderByDesc('id')
                    ->select(['added', 'uuid', 'email'])
                    ->get();
                begin_frame($lang_complains['pending_complaints']);
                if (count($rows)) {
                    $showTable($rows);
                } else {
                    echo $lang_complains['no_pending_complaints'];
                }
                end_frame();
            }
            begin_frame($lang_complains['complaints_processed']);
            $answeredCount = (int) NexusDB::table('complains')->where('answered', 1)->count();
            [$pagertop, $pagerbottom, $limit, $offsetStart, $rowsPerPage] = pager(20, $answeredCount, '?action=list&');
            $rows = NexusDB::table('complains')
                ->where('answered', 1)
                ->orderByDesc('id')
                ->offset((int) $offsetStart)
                ->limit((int) $rowsPerPage)
                ->select(['added', 'uuid', 'email'])
                ->get();
            if (count($rows)) {
                echo $pagertop;
                $showTable($rows);
                echo $pagerbottom;
            } else {
                echo $lang_complains['no_complaints_have_been_processed'];
            }
            end_frame();
            end_main_frame();
            stdfoot();
            break;
        case 'view':
            $uuid = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (strlen($uuid) != 36) {
                permissiondenied();
            }
            $complainRow = NexusDB::table('complains')->where('uuid', (string) $uuid)->first();
            if (! $complainRow) {
                permissiondenied();
            }
            $complain = (array) $complainRow;
            $user = User::query()->where('email', $complain['email'])->first();
            stdhead($lang_complains['text_complain']);
            begin_main_frame();
            if (! $isLogin) {
                begin_frame($lang_complains['text_created_title']);
                printf('<p style="font-weight: bold; color: red">%s</p>', $lang_complains['text_created_note']);
                end_frame();
            }
            begin_frame($lang_complains['text_new_body']);
            printf('%s：%s<br />%s %s', $lang_complains['text_added'], gettime($complain['added']), $lang_complains['text_new_email'], htmlspecialchars($complain['email']));
            if ($isAdmin) {
                if ($user) {
                    printf(' [<a href="userdetails.php?id=%s" class="faqlink" target="_blank">%s</a>]', $user->id, $user->username);
                    printf(' [<a href="user-ban-log.php?q=%s" class="faqlink" target="_blank">%s</a>]', urlencode($user->username), $lang_complains['text_view_band_log']);
                } else {
                    printf(' [<a href="usersearch.php?em=%s" class="faqlink" target="_blank">%s</a>]', urlencode($complain['email']), $lang_complains['text_search_account']);
                }
                printf('<br />IP: '.htmlspecialchars($complain['ip']));
            }
            echo '<hr />', format_comment($complain['body']);
            end_frame();
            // REPLIES
            begin_frame($lang_complains['text_replies']);
            $replyRows = NexusDB::table('complain_replies')
                ->where('complain', (int) $complain['id'])
                ->orderByDesc('id')
                ->get();
            if (count($replyRows)) {
                foreach ($replyRows as $row) {
                    $row = (array) $row;
                    printf('<b>%s @ %s', $row['userid'] ? get_plain_username($row['userid']) : $lang_complains['text_complainer'], gettime($row['added']));
                    if ($isAdmin) {
                        printf(' (%s)', htmlspecialchars($row['ip']));
                    }
                    echo ': </b>';
                    echo format_comment($row['body']).'<hr />';
                }
            } else {
                printf('<p align="center">%s</p>', $lang_complains['text_no_replies']);
            }
            end_frame();
            // NEW REPLY
            if ($complain['answered']) {
                printf('<p align="center">%s</p>', $lang_complains['text_closed']);
            } else {
                printf('<br /><br /><table style="border:1px solid #000000;" align="center"><tr><td class="text" align="center"><b>%s</b><br /><br /><form id="reply" method="post" action="" onsubmit="return postvalid(this);"><input type="hidden" name="action" value="reply" /><input type="hidden" name="id" value="%u" /><br />', $lang_complains['text_reply'], $complain['id']);
                quickreply('reply', 'body', $lang_complains['text_reply']);
                echo '</form></td></tr></table>';
            }
            if ($isAdmin) {
                printf('<form action="" method="post" style="text-align: center; margin-top: 2em"><input type="hidden" name="action" value="%s" /><input type="hidden" name="id" value="%u" /><button>%s</button></form>', $complain['answered'] ? 'unanswered' : 'answered', $complain['id'], $complain['answered'] ? $lang_complains['text_unanswer_it'] : $lang_complains['text_answer_it']);
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
                <?php
                $inputStyle = 'style="width: min(100%, 420px); min-width: 180px; border: 1px solid gray; box-sizing: border-box"';
            $textareaStyle = 'style="width: min(100%, 420px); min-width: 180px; border: 1px solid gray; box-sizing: border-box; height: 250px; resize: vertical;"';
            ?>
                <table border="0" cellpadding="5">
                    <tr><td class="rowhead"><?php echo $lang_complains['text_new_email']?></td><td class="rowfollow" align="left"><input type="email" name="email" <?php echo $inputStyle; ?> autocomplete="email" /></td></tr>
                    <tr><td class="rowhead"><?php echo $lang_complains['text_new_body']?></td><td class="rowfollow" align="left"><textarea name="body" <?php echo $textareaStyle; ?> placeholder="<?= $lang_complains['text_new_body_placeholder'] ?>"></textarea></td></tr>
                    <?php show_image_code(); ?>
                    <tr><td class="toolbox" colspan="2" align="center"><input type="submit" value="<?= $lang_complains['text_new_submit']?>" class="btn" /></td></tr>
                </table>
            </form>
            <?php
            stdfoot();
    }
}
