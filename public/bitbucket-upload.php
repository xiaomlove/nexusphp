<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();
if ($enablebitbucket_main != 'yes')
	permissiondenied();
$maxfilesize = 256 * 1024;
$imgtypes = array (null,'gif','jpg','png');
$scaleh = 200; // set our height size desired
$scalew = 150; // set our width size desired

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
	$file = $_FILES["file"];
	if (!isset($file) || $file["size"] < 1)
	stderr($lang_bitbucketupload['std_upload_failed'], $lang_bitbucketupload['std_nothing_received']);
	if ($file["size"] > $maxfilesize)
	stderr($lang_bitbucketupload['std_upload_failed'], $lang_bitbucketupload['std_file_too_large']);
	$pp=pathinfo($filename = $file["name"]);
	if($pp['basename'] != $filename)
	stderr($lang_bitbucketupload['std_upload_failed'], $lang_bitbucketupload['std_bad_file_name']);
	$tgtfile = getFullDirectory("$bitbucket/$filename");
	if (file_exists($tgtfile))
	stderr($lang_bitbucketupload['std_upload_failed'], $lang_bitbucketupload['std_file_already_exists'].htmlspecialchars($filename).$lang_bitbucketupload['std_already_exists'],false);

	$size = getimagesize($file["tmp_name"]);
	$height = $size[1];
	$width = $size[0];
	$it = $size[2];
	if($imgtypes[$it] == null || $imgtypes[$it] != strtolower($pp['extension']))
	stderr($lang_bitbucketupload['std_error'], $lang_bitbucketupload['std_invalid_image_format'],false);

	// Scale image to appropriate avatar dimensions
	$hscale=$height/$scaleh;
	$wscale=$width/$scalew;
	$scale=($hscale < 1 && $wscale < 1) ? 1 : (( $hscale > $wscale) ? $hscale : $wscale);
	$newwidth=floor($width/$scale);
	$newheight=floor($height/$scale);

	if ($it==1)
		$orig=@imagecreatefromgif($file["tmp_name"]);
	elseif ($it == 2)
		$orig=@imagecreatefromjpeg($file["tmp_name"]);
	else
		$orig=@imagecreatefrompng($file["tmp_name"]);
	if(!$orig)
	stderr($lang_bitbucketupload['std_image_processing_failed'],$lang_bitbucketupload['std_sorry_the_uploaded']."$imgtypes[$it]".$lang_bitbucketupload['std_failed_processing']);
	$thumb = imagecreatetruecolor($newwidth, $newheight);
	imagecopyresized($thumb, $orig, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
	switch ($it) {
        case 1:
            $ret = imagegif($thumb, $tgtfile);
            break;
        case 2:
            $ret = imagejpeg($thumb, $tgtfile);
            break;
        default:
            $ret = imagepng($thumb, $tgtfile);
    }
//	$ret=($it==1)?imagegif($thumb, $tgtfile): ($it==2)?imagejpeg($thumb, $tgtfile):imagepng($thumb, $tgtfile);

	$url = str_replace(" ", "%20", htmlspecialchars(get_protocol_prefix()."$BASEURL/bitbucket/$filename"));
	$name = sqlesc($filename);
	$added = sqlesc(date("Y-m-d H:i:s"));
	if (!isset($_POST['public']) || $_POST['public'] != 'yes' )
	$public='"0"';
	else
	$public='"1"';
	sql_query("INSERT INTO bitbucket (owner, name, added, public) VALUES ({$CURUSER['id']}, $name, $added, $public)") or sqlerr(__FILE__, __LINE__);
	sql_query("UPDATE users SET avatar = ".sqlesc($url)." WHERE id = {$CURUSER['id']}") or sqlerr(__FILE__, __LINE__);

	stderr($lang_bitbucketupload['std_success'], $lang_bitbucketupload['std_use_following_url']."<br /><b><a href=\"$url\">$url</a></b><p><a href=bitbucket-upload.php>".$lang_bitbucketupload['std_upload_another_file']."</a>.<br /><br /><img src=\"$url\" border=0><br /><br />".$lang_bitbucketupload['std_image']. ($width=$newwidth && $height==$newheight ? $lang_bitbucketupload['std_need_not_rescaling']:$lang_bitbucketupload['std_rescaled_from']."$height x $width".$lang_bitbucketupload['std_to']."$newheight x $newwidth") .$lang_bitbucketupload['std_profile_updated'],false);
}

stdhead($lang_bitbucketupload['head_avatar_upload']);
?>
<h1><?php echo $lang_bitbucketupload['text_avatar_upload'] ?></h1>
<form method="post" action=bitbucket-upload.php enctype="multipart/form-data">
<table border=1 cellspacing=0 cellpadding=5>
<?php

if(!is_writable(ROOT_PATH . "$bitbucket"))
print("<tr><td align=left colspan=2>".$lang_bitbucketupload['text_upload_directory_unwritable']."</tr></td>");
print("<tr><td align=left colspan=2>".$lang_bitbucketupload['text_disclaimer']."$scaleh".$lang_bitbucketupload['text_disclaimer_two']."$scalew".$lang_bitbucketupload['text_disclaimer_three'].number_format($maxfilesize).$lang_bitbucketupload['text_disclaimer_four']);
?>
<tr><td class=rowhead><?php echo $lang_bitbucketupload['row_file'] ?></td><td class="rowfollow"><input type="file" name="file" size="60"></td></tr>
<tr><td colspan=2 align=left class="toolbox"><input class="checkbox" type=checkbox name=public value=yes><?php echo $lang_bitbucketupload['checkbox_avatar_shared']?> <input type="submit" value=<?php echo $lang_bitbucketupload['submit_upload'] ?>></td></tr>
</table>
</form>
<?php
stdfoot();
