<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
if ($_SERVER["REQUEST_METHOD"] == "POST")
{
	$torrentid = intval($_POST['id'] ?? 0);
	$type = $_POST['type'];
	$hidenotice = $_POST['hidenotice'];
	if (!$torrentid || !in_array($type,array('firsttime', 'client', 'ratio')))
		die("error");
	elseif ($type == 'firsttime')
	{
		if ($hidenotice){
			sql_query("UPDATE users SET showdlnotice=0 WHERE id=".sqlesc($CURUSER['id']));
		}
		nexus_redirect(getSchemeAndHttpHost(). "/download.php?id=".$torrentid."&letdown=1");
	}
	elseif ($type == 'client')
	{
		if ($hidenotice){
			sql_query("UPDATE users SET showclienterror='no' WHERE id=".sqlesc($CURUSER['id']));
		}
        nexus_redirect(getSchemeAndHttpHost() . "/download.php?id=".$torrentid."&letdown=1");
	}
	else
	{
        nexus_redirect(getSchemeAndHttpHost() . "/download.php?id=".$torrentid."&letdown=1");
	}
}
else
{
	$torrentid = (int)$_GET["torrentid"];
	$type = $_GET["type"];
	switch ($type)
	{
		case 'client':
		{
			$title = $lang_downloadnotice['text_client_banned_notice'];
			$note = $lang_downloadnotice['text_client_banned_note'];
			$noticenexttime = $lang_downloadnotice['text_notice_not_show_again'];
			$showrationotice = false;
			$showclientnotice = true;
			$forcecheck = false;
			break;
		}
		case 'ratio':
		{
			$title = $lang_downloadnotice['text_low_ratio_notice'];
			$leechwarnuntiltime = strtotime($CURUSER['leechwarnuntil']);
			if (TIMENOW < $leechwarnuntiltime){
				$kicktimeout = gettime($CURUSER['leechwarnuntil'], false, false, true);
				$note = $lang_downloadnotice['text_low_ratio_note_one'].$kicktimeout.$lang_downloadnotice['text_low_ratio_note_two'];
			}
			$noticenexttime = $lang_downloadnotice['text_notice_always_show'];
			$showrationotice = true;
			$showclientnotice = false;
			$forcecheck = true;
			break;
		}
		case 'firsttime':
		default:
		{
			$type = 'firsttime';
			$title = $lang_downloadnotice['text_first_time_download_notice'];
			$note = $lang_downloadnotice['text_first_time_download_note'];
			$noticenexttime = $lang_downloadnotice['text_notice_not_show_again'];
			$showrationotice = true;
			$showclientnotice = true;
			$forcecheck = false;
		}
	}
	if ($showrationotice && $showclientnotice)
		$tdattr = "width=\"50%\"";
	else
		$tdattr = "colspan=\"2\" width=\"100%\"";
	stdhead($lang_downloadnotice['head_download_notice']);
	begin_main_frame();
?>
<h2><?php echo $title?></h2>
<table width="100%"><tr>
<td colspan="2" class="text" align="left"><p><?php echo $note?></p></td></tr>
<tr>
<?php
if ($showrationotice)
{
?>
<td class="text" align="left" valign="top" <?php echo $tdattr?>>
<h3><?php echo $lang_downloadnotice['text_this_is_private_tracker']?></h3>
<p><?php echo $lang_downloadnotice['text_private_tracker_note_one']?><i>(<?php echo $lang_downloadnotice['text_learn_more']?><a class="faqlink" href="<?php echo NEXUSWIKIURL?>/Private Tracker" target="_blank"><?php echo $lang_downloadnotice['text_nexuswiki']?></a>)</i></p>
<p><?php echo $lang_downloadnotice['text_private_tracker_note_two']?><i>(<?php echo $lang_downloadnotice['text_see_ratio']?><a class="faqlink" href="faq.php#id23" target="_blank"><?php echo $lang_downloadnotice['text_faq']?></a>)</i></p>
<p><?php echo $lang_downloadnotice['text_private_tracker_note_three']?></p>
<img src="pic/ratio.png" alt="ratio" />
<p><?php echo $lang_downloadnotice['text_private_tracker_note_four']?></p>
</td>
<?php
}
if ($showclientnotice)
{
?>
<td class="text" align="left" valign="top" <?php echo $tdattr?>>
<h3><?php echo $lang_downloadnotice['text_use_allowed_clients']?></h3>
<p><?php echo $lang_downloadnotice['text_allowed_clients_note_one']?><i>(<?php echo $lang_downloadnotice['text_why_banned']?><a class="faqlink" href="<?php echo NEXUSWIKIURL?>/客户端测试报告" target="_blank"><?php echo $lang_downloadnotice['text_nexuswiki']?></a>)</i></p>
<p><?php echo $lang_downloadnotice['text_allowed_clients_note_two']?><a class='faqlink' href='faq.php#id29' target='_blank'><?php echo $lang_downloadnotice['text_faq']?></a><?php echo $lang_downloadnotice['text_allowed_clients_note_three']?></p>
<table width="100%">
<tr>
<td class="embedded" style="text-align: center; padding: 5px;" width="50%">
<a href="http://www.utorrent.com/download.php" target="_blank" title="<?php echo $lang_downloadnotice['title_download']?>uTorrent"><img src="pic/utorrentbig.png" alt="uTorrent" /></a>
</td>
<td class="embedded" style="text-align: center; padding: 5px;" width="50%">
<a href="http://azureus.sourceforge.net/download.php" target="_blank" title="<?php echo $lang_downloadnotice['title_download']?>Vuze"><img src="pic/vuzebig.png" alt="Vuze" /></a>
</td>
</tr>
<tr>
<td class="embedded" style="text-align: center; padding: 5px;">
<div class="big"><a href="http://www.utorrent.com/download.php" target="_blank" title="<?php echo $lang_downloadnotice['title_download']?>uTorrent"><b>uTorrent</b></a></div>
<div><?php echo $lang_downloadnotice['text_for']?>Windows</div>
</td>
<td class="embedded" style="text-align: center; padding: 5px;">
<div class="big"><a href="http://azureus.sourceforge.net/download.php" target="_blank" title="<?php echo $lang_downloadnotice['title_download']?>Vuze"><b>Vuze</b></a></div>
<div><?php echo $lang_downloadnotice['text_for']?>Windows, Linux, Mac OS X</div>
</td>
</tr>
</table>
</td>
<?php
}
?>
</tr>
<?php
if ($torrentid)
{
?>
<tr>
<td class="text" colspan="2">
<form action="?" method="post"><p><?php echo $lang_downloadnotice['text_for_more_information_read']?><a class="faqlink" href="rules.php" target="_blank"><?php echo $lang_downloadnotice['text_rules']?></a><?php echo $lang_downloadnotice['text_and']?><a class="faqlink" href="faq.php" target="_blank"><?php echo $lang_downloadnotice['text_faq']?></a><br />
<input type="hidden" name="id" value="<?php echo $torrentid?>" />
<input type="hidden" name="type" value="<?php echo htmlspecialchars($type)?>" />
<input type="checkbox" name="hidenotice" id="hidenotice" value="1"<?php echo $forcecheck ? " disabled=\"disabled\"" : " checked=\"checked\""?> /><label for="hidenotice"><?php echo $noticenexttime?></label>
<?php
if ($forcecheck)
{
?>
<br /><input type="checkbox" name="letmedown" id="letmedown" value="<?php echo htmlspecialchars($type)?>" onclick="if (this.checked) {document.getElementById('continuedownload').disabled = false;}else{document.getElementById('continuedownload').disabled = true;}" /><label for="letmedown"><span class="big"><?php echo $lang_downloadnotice['text_let_me_download']?></span></label>
<?php
}
?>
</p>
<div><input type="submit" name="submit" id="continuedownload" style="font-size: 20pt; height: 40px;" value="<?php echo $lang_downloadnotice['submit_download_the_torrent']?>"<?php echo $forcecheck ? " disabled=\"disabled\"" : ""?> /></div>
</form>
</td>
</tr>
<?php
}
?>
</table>
<?php
	end_main_frame();
	stdfoot();
}
