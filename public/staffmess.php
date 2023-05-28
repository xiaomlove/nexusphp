<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
if (get_user_class() < UC_ADMINISTRATOR)
stderr("Sorry", "Access denied.");
stdhead("Mass PM", false);
$classes = array_chunk(\App\Models\User::$classes, 4, true);
?>
<table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
<div align=center>
<h1>Mass PM to all Staff members and users:</a></h1>
<form method=post action=takestaffmess.php>
<?php

if ($_GET["returnto"] || $_SERVER["HTTP_REFERER"])
{
?>
<input type=hidden name=returnto value="<?php echo htmlspecialchars($_GET["returnto"]) ? htmlspecialchars($_GET["returnto"]) : htmlspecialchars($_SERVER["HTTP_REFERER"])?>">
<?php
}
?>
<table cellspacing=0 cellpadding=5>
<?php
if ($_GET["sent"] == 1) {
?>
<tr><td colspan=2><font color=red><b>The message has ben sent.</b></font></td></tr>
<?php
}
?>
<tr>
    <td><b>Send to class:</b></td>
    <td>
        <table style="border: 0" width="100%" cellpadding="0" cellspacing="0">
            <?php
            foreach ($classes as $chunk) {
                printf('<tr>');
                foreach ($chunk as $class => $info) {
                    printf('<td style="border: 0"><label><input type="checkbox" name="classes[]" value="%s" />%s</label></td>', $class, $info['text']);
                }
                printf('</tr>');
            }
            ?>
        </table>
    </td>
</tr>
<?php do_action('form_role_filter', 'Send to Role:') ?>
<tr>
    <td class="rowhead">Subject</td>
    <td> <input type=text name=subject size=75></td>
</tr>
<tr>
    <td class="rowhead">Message</td>
    <td><textarea name=msg cols=80 rows=15><?php echo $body?></textarea></td>
</tr>
<tr>
<td colspan=2><div align="center"><b>Sender:&nbsp;&nbsp;</b>
<?php echo $CURUSER['username']?>
<input name="sender" type="radio" value="self" checked>
&nbsp; System
<input name="sender" type="radio" value="system">
</div></td></tr>
<tr><td colspan=2 align=center><input type=submit value="Send!" class=btn></td></tr>
</table>
<input type=hidden name=receiver value=<?php echo $receiver?>>
</form>

 </div></td></tr></table>
<br />
NOTE: Do not user BB codes. (NO HTML)
<?php
stdfoot();
