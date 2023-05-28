<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
if (get_user_class() < UC_SYSOP) {
    stderr("Sorry", "Access denied.");
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sender_id = ($_POST['sender'] == 'system' ? 0 : (int)$CURUSER['id']);
    $dt = date("Y-m-d H:i:s");
    $msg = trim($_POST['msg']);
    $amount = $_POST['amount'];
    if (!$msg || !$amount)
        stderr("Error","Don't leave any fields blank.");
    if(!is_numeric($amount))
        stderr("Error","amount must be numeric");
    $updateset = $_POST['clases'];
    if (is_array($updateset)) {
        foreach ($updateset as $class) {
            if (!is_valid_id($class) && $class != 0)
                stderr("Error","Invalid Class");
        }
    }else{
        if (!is_valid_id($updateset) && $updateset != 0)
            stderr("Error","Invalid Class");
    }
    $subject = trim($_POST['subject']);
    $page = 1;
    $size = 10000;
    while (true) {
        $messages = [];
        $userIdArr = [];
        $users = \App\Models\User::query()->whereIn('class', $updateset)->forPage($page, $size)->get(['id']);
        if ($users->isEmpty()) {
            break;
        }
        foreach ($users as $user) {
            $userIdArr[] = $user->id;
            $messages[] = [
                'sender' => $sender_id,
                'receiver' => $user->id,
                'added' => $dt,
                'subject' => $subject,
                'msg' => $msg,
            ];
        }
        \App\Models\User::query()->whereIn('id', $userIdArr)->increment('attendance_card', $amount);
        \App\Models\Message::query()->insert($messages);
        $page++;
    }
    header(sprintf("Refresh: 0; url=%s?sent=1", $_SERVER['PHP_SELF']));
}

stdhead("Add Attendance card", false);
$allClass = array_chunk(\App\Models\User::$classes, 4, true);
?>
    <table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
        <div align=center>
            <h1>Add attendance card to all staff members and users:</a></h1>
            <form method=post action=<?php echo $_SERVER['PHP_SELF']?>>
                <?php

                if (isset($_GET["returnto"]) || $_SERVER["HTTP_REFERER"])
                {
                    ?>
                    <input type=hidden name=returnto value="<?php echo htmlspecialchars($_GET["returnto"]) ? htmlspecialchars($_GET["returnto"]) : htmlspecialchars($_SERVER["HTTP_REFERER"])?>">
                    <?php
                }
                ?>
                <table cellspacing=0 cellpadding=5>
                    <?php
                    if (isset($_GET["sent"]) && $_GET["sent"] == 1) {
                        ?>
                        <tr><td colspan=2 class="text" align="center"><font color=red><b>Attendance card has been added and inform message has been sent.</font></b></tr></td>
                        <?php
                    }
                    ?>
                    <tr><td class="rowhead" valign="top">Amount </td><td class="rowfollow"><input type=number name=amount size=10></td></tr>
                    <tr>
                        <td class="rowhead" valign="top">Add to</td><td class="rowfollow">
                            <table style="border: 0" width="100%" cellpadding="0" cellspacing="0">
                                <?php foreach ($allClass as $bulk) {?>
                                <tr>
                                    <?php foreach ($bulk as $key => $value) {?>
                                    <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="<?php echo $key?>">
                                    </td>
                                    <td style="border: 0"><?php echo $value['text'] ?></td>
                                    <?php }?>
                                </tr>
                                <?php } ?>
                            </table>
                        </td>
                    </tr>
                    <tr><td class="rowhead" valign="top">Subject </td><td class="rowfollow"><input type=text name=subject size=82></td></tr>
                    <tr><td class="rowhead" valign="top">Reason </td><td class="rowfollow"><textarea name=msg cols=80 rows=5><?php echo $body ?? ''?></textarea></td></tr>
                    <tr>
                        <td class="rowfollow" colspan=2><div align="center"><b>Operator:&nbsp;&nbsp;</b>
                                <?php echo $CURUSER['username']?>
                                <input name="sender" type="radio" value="self" checked>
                                &nbsp; System
                                <input name="sender" type="radio" value="system">
                            </div></td></tr>
                    <tr><td class="rowfollow" colspan=2 align=center><input type=submit value="Do It!" class=btn></td></tr>
                </table>
                <input type=hidden name=receiver value=<?php echo $receiver ?? ''?>>
            </form>

        </div></td></tr></table>
    <br />
    NOTE: Do not use BB codes. (NO HTML)
<?php
stdfoot();
