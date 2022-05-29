<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
if (get_user_class() < UC_SYSOP)
    stderr("Sorry", "Access denied.");

$validTypeMap = [
    'seedbonus' => 'Bonus',
    'attendance_card' => 'Attend card',
    'invites' => 'Invite',
    'uploaded' => 'Upload',
];
$type = $_REQUEST['type'] ?? '';
stdhead("Add Bonus/Attend card/Invite/upload", false);
$classes = array_chunk(\App\Models\User::$classes, 4, true);
?>
    <table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
                <div align=center>
                    <h1>Add Bonus/Attend card/Invite/upload to all staff members and users:</a></h1>
                    <form method=post action=take-increment-bulk.php>
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
                                echo '<tr><td colspan=2 class="text" align="center"><font color=red><b> '. ($validTypeMap[$type] ?? '') .' has been added and inform message has been sent.</font></b></tr></td>';
                            }
                            ?>
                            <tr>
                                <td class="rowhead" valign="top">Type </td>
                                <td class="rowfollow">
                                    <?php
                                    foreach ($validTypeMap as $name => $text) {
                                        $desc = '';
                                        if ($name == 'uploaded') {
                                            $desc = '&nbsp;(in GB)';
                                        }
                                        printf('<label><input type="radio" name="type" value="%s">%s%s</label>', $name, $text, $desc);
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr><td class="rowhead" valign="top">Amount </td><td class="rowfollow"><input type=text name=amount size=10></td></tr>
                            <tr>
                                <td class="rowhead" valign="top">Add to</td><td class="rowfollow">
                                    <table style="border: 0" width="100%" cellpadding="0" cellspacing="0">
                                        <?php
                                        foreach ($classes as $chunk) {
                                            printf('<tr>');
                                            foreach ($chunk as $class => $info) {
                                                printf('<td style="border: 0"><label><input type="checkbox" name="clases[]" value="%s" />%s</label></td>', $class, $info['text']);
                                            }
                                            printf('</tr>');
                                        }
                                        ?>
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
    NOTE: Do not user BB codes. (NO HTML)
<?php
stdfoot();
