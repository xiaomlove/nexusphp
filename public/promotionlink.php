<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
$key=$_GET['key'];
$updatekey=$_GET['updatekey'];
if ($key)
{
	if (!$CURUSER)
	{
	if ($prolinkpoint_bonus)
	{
		$res=sql_query("SELECT id FROM users WHERE promotion_link=".sqlesc($key)." LIMIT 1");
		$row=mysql_fetch_array($res);
		if ($row)
		{
			$ip = getip();
			$dt=sqlesc(date("Y-m-d H:i:s",(TIMENOW-$prolinktime_bonus)));
			$res2=sql_query("SELECT COUNT(id) FROM prolinkclicks WHERE userid=".sqlesc($row['id'])." AND (added > ".$dt." OR ip=".sqlesc($ip).")");
			$row2=mysql_fetch_array($res2);
			if ($row2[0]==0)
			{
				KPS("+", $prolinkpoint_bonus, $row['id']);
				sql_query("INSERT INTO prolinkclicks (userid, ip, added) VALUES (".$row['id'].", ".sqlesc($ip).", NOW())");
			}
		}
	}
	}
	header("Location: " . get_protocol_prefix() . $BASEURL);
}
elseif(($updatekey || !$CURUSER['promotion_link']) && $CURUSER)
{
	$promotionkey=md5($CURUSER['email'].date("Y-m-d H:i:s").$CURUSER['passhash']);
	sql_query("UPDATE users SET promotion_link=".sqlesc($promotionkey)." WHERE id=".sqlesc($CURUSER['id']));
	header("Location: " . get_protocol_prefix() . $BASEURL."/promotionlink.php");
}
else
{
	stdhead($lang_promotionlink['head_promotion_link']);
	begin_main_frame();
	$yourlink=get_protocol_prefix() . $BASEURL."/promotionlink.php?key=".$CURUSER['promotion_link'];
	$imgurl=get_protocol_prefix() . $BASEURL."/".$prolinkimg;
	begin_frame($lang_promotionlink['text_promotion_link']);
?>
<div><p align="left"><?php echo $lang_promotionlink['text_promotion_link_note_one']?></p><p align="left"><?php echo $lang_promotionlink['text_promotion_link_note_two']?></p><p align="left"><?php echo $lang_promotionlink['text_you_would_get'].$prolinkpoint_bonus.$lang_promotionlink['text_bonus_points'].$prolinktime_bonus.$lang_promotionlink['text_seconds']?></p><p align="left"><?php echo "<b>".$lang_promotionlink['text_your_promotion_link_is']."</b><a href=\"".$yourlink."\">".$yourlink."</a>"?></p><p align="left"><?php echo $lang_promotionlink['text_promotion_link_note_four']?></p></div>
<table border="1" cellspacing="0" cellpadding="10" width="100%">
<tr>
<td class="colhead"><?php echo $lang_promotionlink['col_type']?></td>
<td class="colhead"><?php echo $lang_promotionlink['col_code']?></td>
<td class="colhead"><?php echo $lang_promotionlink['col_result']?> / <?php echo $lang_promotionlink['col_note']?></td>
</tr>
<tr><td class="colfollow"><?php echo $lang_promotionlink['row_xhtml']?></td><td class="colfollow"><textarea cols="50" rows="4"><?php echo htmlspecialchars("<a href=\"".$yourlink."\" target=\"_blank\"><img src=\"".$imgurl."\" alt=\"".$SITENAME."\" title=\"".$SITENAME." - ".$SLOGAN."\" /></a>")?></textarea></td><td class="colfollow" align="left"><div><a href="<?php echo $yourlink?>" target="_blank"><img src="<?php echo $imgurl?>" alt="<?php echo htmlspecialchars($SITENAME)?>" title="<?php echo htmlspecialchars($SITENAME)?> - <?php echo htmlspecialchars($SLOGAN)?>" /></a></div><div style="padding-top: 10px"><?php echo $lang_promotionlink['text_xhtml_note']?></div></td></tr>
<tr><td class="colfollow"><?php echo $lang_promotionlink['row_html']?></td><td class="colfollow"><textarea cols="50" rows="4"><?php echo htmlspecialchars("<a href=\"".$yourlink."\"><img src=\"". $imgurl . "\" alt=\"".$SITENAME."\" title=\"".$SITENAME." - ".$SLOGAN."\"></a>")?></textarea></td><td class="colfollow"><div><a href="<?php echo $yourlink?>" target="_blank"><img src="<?php echo $imgurl?>" alt="<?php echo htmlspecialchars($SITENAME)?>" title="<?php echo htmlspecialchars($SITENAME)?> - <?php echo htmlspecialchars($SLOGAN)?>" /></a></div><div style="padding-top: 10px"><?php echo $lang_promotionlink['text_html_note']?></div></td></tr>
<tr><td class="colfollow"><?php echo $lang_promotionlink['row_bbcode']?></td><td class="colfollow"><textarea cols="50" rows="4"><?php echo htmlspecialchars("[url=".$yourlink."][img]".$imgurl."[/img][/url]")?></textarea></td><td class="colfollow"><div><a href="<?php echo $yourlink?>"><img src="<?php echo $imgurl?>" /></a></div><div style="padding-top: 10px"><?php echo $lang_promotionlink['text_bbcode_note']?></div></td></tr>
<?php
if (get_user_class() >= $userbar_class)
{
?>
<tr><td class="colfollow"><?php echo $lang_promotionlink['row_bbcode_userbar']?></td><td class="colfollow"><textarea cols="50" rows="4"><?php echo htmlspecialchars("[url=".$yourlink."][img]".get_protocol_prefix() . $BASEURL."/mybar.php?userid=".$CURUSER['id'].".png[/img][/url]")?></textarea></td><td class="colfollow"><div><a href="<?php echo $yourlink?>"><img src="<?php echo get_protocol_prefix() . $BASEURL?>/mybar.php?userid=<?php echo $CURUSER['id']?>.png" /></a></div><div style="padding-top: 10px"><?php echo $lang_promotionlink['text_bbcode_userbar_note']?></div></td></tr>
<!--<tr><td class="colfollow">--><?php //echo $lang_promotionlink['row_bbcode_userbar_alt']?><!--</td><td class="colfollow"><textarea cols="50" rows="4">--><?php //echo htmlspecialchars("[url=".$yourlink."][img]".get_protocol_prefix() . $BASEURL."/cc98bar.php/id".$CURUSER['id'].".png[/img][/url]")?><!--</textarea></td><td class="colfollow"><div><a href="--><?php //echo $yourlink?><!--"><img src="--><?php //echo get_protocol_prefix() . $BASEURL?><!--/cc98bar.php/id--><?php //echo $CURUSER['id']?><!--.png" /></a></div><div style="padding-top: 10px">--><?php //echo $lang_promotionlink['text_bbcode_userbar_alt_note']?><!--</div></td></tr>-->
<?php
}
?>
</table>
</div>
<?php
	end_frame();
	end_main_frame();
	stdfoot();
}
?>
