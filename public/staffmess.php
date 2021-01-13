<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
if (get_user_class() < UC_ADMINISTRATOR)
stderr("Sorry", "Access denied.");
stdhead("Mass PM", false);
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
<tr><td colspan=2><font color=red><b>The message has ben sent.</font></b></tr></td>
<?php
}
?>
<tr>
<td><b>Send to:</b><br />
  <table style="border: 0" width="100%" cellpadding="0" cellspacing="0">
    <tr>
             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="0">
             </td>
             <td style="border: 0">Peasant</td>

             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="1">
             </td>
             <td style="border: 0">User</td>

             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="2">
             </td>
             <td style="border: 0">Power User</td>

             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="3">
             </td>
             <td style="border: 0">Elite User</td>
      </tr>
    <tr>
             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="4">
             </td>
             <td style="border: 0">Crazy User</td>

             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="5">
             </td>
             <td style="border: 0">Insane User</td>

             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="6">
             </td>
             <td style="border: 0">Veteran User</td>

             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="7">
             </td>
             <td style="border: 0">Extreme User</td>
      </tr>

    <tr>
             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="8">
             </td>
             <td style="border: 0">Ultimate User</td>

             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="9">
             </td>
             <td style="border: 0">Nexus Master</td>

             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="10">
             </td>
             <td style="border: 0">VIP</td>

             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="11">
             </td>
             <td style="border: 0">Uploader</td>
      </tr>

    <tr>
             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="12">
             </td>
             <td style="border: 0">Moderator</td>

             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="13">
             </td>
             <td style="border: 0">Administrator</td>

             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="14">
             </td>
             <td style="border: 0">SysOp</td>

             <td style="border: 0" width="20"><input type="checkbox" name="clases[]" value="15">
             </td>
             <td style="border: 0">Staff Leader</td>
	
       <td style="border: 0">&nbsp;</td>
       <td style="border: 0">&nbsp;</td>
      </tr>
    </table>
  </td>
</tr>
<tr><td>Subject <input type=text name=subject size=75></tr></td>
<tr><td><textarea name=msg cols=80 rows=15><?php echo $body?></textarea></td></tr>
<tr>
<td colspan=1><div align="center"><b>Sender:&nbsp;&nbsp;</b>
<?php echo $CURUSER['username']?>
<input name="sender" type="radio" value="self" checked>
&nbsp; System
<input name="sender" type="radio" value="system">
</div></td></tr>
<tr><td colspan=1 align=center><input type=submit value="Send!" class=btn></td></tr>
</table>
<input type=hidden name=receiver value=<?php echo $receiver?>>
</form>

 </div></td></tr></table>
<br />
NOTE: Do not user BB codes. (NO HTML)
<?php
stdfoot();
