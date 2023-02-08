<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
if (get_user_class() < UC_SYSOP)
    stderr("Sorry", "Access denied.");

$validTypeMap = $lang_increment_bulk['types'];
$type = $_REQUEST['type'] ?? '';
stdhead($lang_increment_bulk['page_title'], false);
$classes = array_chunk(\App\Models\User::listClass(), 4, true);
?>
    <table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
                <div align=center>
                    <h1><?php echo $lang_increment_bulk['page_title']?></a></h1>
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
                                echo '<tr><td colspan=2 class="text" align="center"><font color=red><b> '. ($validTypeMap[$type] ?? '') . $lang_increment_bulk['sent_success'] .'</font></b></tr></td>';
                            }
                            ?>
                            <tr>
                                <td class="rowhead" valign="top"><?php echo $lang_increment_bulk['labels']['type'] ?></td>
                                <td class="rowfollow">
                                    <?php
                                    foreach ($validTypeMap as $name => $text) {
                                        $desc = '';
                                        if ($name == 'uploaded') {
                                            $desc = '&nbsp;(GB)';
                                        }
                                        printf('<label><input type="radio" name="type" value="%s">%s%s</label>', $name, $text, $desc);
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr><td class="rowhead" valign="top"><?php echo $lang_increment_bulk['labels']['amount'] ?> </td><td class="rowfollow"><input type=text name=amount size=10></td></tr>
                            <tr><td class="rowhead" valign="top"><?php echo $lang_increment_bulk['labels']['duration'] ?></td><td class="rowfollow"><input type=number min="1" name=duration size=10> <?php echo $lang_increment_bulk['labels']['duration_help'] ?></td></tr>
                            <tr>
                                <td class="rowhead" valign="top"><?php echo $lang_increment_bulk['labels']['user_class'] ?></td><td class="rowfollow">
                                    <table style="border: 0" width="100%" cellpadding="0" cellspacing="0">
                                        <?php
                                        foreach ($classes as $chunk) {
                                            printf('<tr>');
                                            foreach ($chunk as $class => $info) {
                                                printf('<td style="border: 0"><label><input type="checkbox" name="classes[]" value="%s" />%s</label></td>', $class, $info);
                                            }
                                            printf('</tr>');
                                        }
                                        ?>
                                    </table>
                                </td>
                            </tr>
                            <?php do_action('form_role_filter', $lang_increment_bulk['labels']['roles']) ?>
                            <tr><td class="rowhead" valign="top"><?php echo $lang_increment_bulk['labels']['msg_subject'] ?> </td><td class="rowfollow"><input type=text name=subject size=82></td></tr>
                            <tr><td class="rowhead" valign="top"><?php echo $lang_increment_bulk['labels']['msg_body'] ?> </td><td class="rowfollow"><textarea name=msg cols=80 rows=5><?php echo $body ?? ''?></textarea></td></tr>
                            <tr>
                                <td class="rowfollow" colspan=2><div align="center"><b><?php echo $lang_increment_bulk['labels']['operator'] ?>:&nbsp;&nbsp;</b>
                                        <label><input name="sender" type="radio" value="self" checked><?php echo $CURUSER['username']?></label>
                                        &nbsp; <label><input name="sender" type="radio" value="system">System</label>
                                    </div></td></tr>
                            <tr><td class="rowfollow" colspan=2 align=center><input type=submit value="<?php echo nexus_trans('label.submit') ?>" class=btn></td></tr>
                        </table>
                        <input type=hidden name=receiver value=<?php echo $receiver ?? ''?>>
                    </form>

                </div></td></tr></table>
<?php
stdfoot();
