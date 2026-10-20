<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
include_once($rootpath . 'classes/class_attachment.php');

$Attach = new ATTACHMENT($CURUSER['id']);
$count_limit = $Attach->get_count_limit();
$count_limit = (int)$count_limit;
$count_left = $Attach->get_count_left();
$size_limit = $Attach->get_size_limit_byte();
$allowed_exts = $Attach->get_allowed_ext();
$css_uri = get_css_uri();
$altsize = $_POST['altsize'] ?? '';
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="<?php echo get_font_css_uri()?>" type="text/css">
<link rel="stylesheet" href="<?php echo $css_uri."theme.css"?>" type="text/css">
<style>
html,
body.inframe {
    min-height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
    background: transparent !important;
}

body.inframe table {
    width: 100% !important;
    margin: 0 !important;
    border: 0 !important;
    border-collapse: collapse !important;
    background: transparent !important;
}

body.inframe td.attachment-controls {
    height: 44px !important;
    padding: 4px 2px !important;
    border: 0 !important;
    background: transparent !important;
    color: #233f48 !important;
    font-size: 12px !important;
    line-height: 36px !important;
    text-align: left !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
}

body.inframe input[type="file"] {
    width: 238px !important;
    height: 36px !important;
    margin: 0 6px 0 0 !important;
    padding: 0 !important;
    border: 1px solid #c9dcde !important;
    border-radius: 6px !important;
    background: #fff !important;
    color: #36515a !important;
    font-size: 12px !important;
    line-height: 34px !important;
    vertical-align: middle !important;
    box-shadow: none !important;
}

body.inframe input[type="file"]::file-selector-button {
    height: 34px !important;
    margin: 0 9px 0 0 !important;
    padding: 0 14px !important;
    border: 0 !important;
    border-right: 1px solid #d4e3e5 !important;
    border-radius: 5px 0 0 5px !important;
    background: #eaf7f6 !important;
    color: #17434c !important;
    font-weight: 700 !important;
    line-height: 34px !important;
    text-align: center !important;
    cursor: pointer;
}

body.inframe input[type="checkbox"] {
    width: 16px !important;
    height: 16px !important;
    margin: 0 4px 0 6px !important;
    vertical-align: middle !important;
}

body.inframe input[type="submit"] {
    min-width: 72px !important;
    height: 36px !important;
    margin: 0 8px !important;
    padding: 0 16px !important;
    border: 0 !important;
    border-radius: 6px !important;
    background: #0f9f8d !important;
    color: #fff !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    line-height: 36px !important;
    text-align: center !important;
    text-indent: 0 !important;
    vertical-align: middle !important;
    box-shadow: 0 4px 10px rgba(15, 159, 141, .18) !important;
}
</style>
</head>
<body class="inframe">
<table width="100%">
<?php
$warning = "";
if ($Attach->enable_attachment())
{
	if ($_SERVER["REQUEST_METHOD"] == "POST")
	{
		$file = $_FILES['file'];
		$filesize = $file["size"];
		$filetype = $file["type"];
		$origfilename = $file['name'];
		$ext_l = strrpos($origfilename, ".");
		$ext = strtolower(substr($origfilename, $ext_l+1, strlen($origfilename)-($ext_l+1)));
		$banned_ext = array('exe', 'com', 'bat', 'msi');
		$img_ext = \App\Models\Attachment::IMG_EXTENSIONS;

		if (!$file || $filesize == 0 || $file["name"] == "") // nothing received
		{
			$warning = $lang_attachment['text_nothing_received'];
		}
		elseif (!$count_left) //user cannot upload more files
		{
			$warning = $lang_attachment['text_file_number_limit_reached'];
		}
		elseif ($filesize > $size_limit || $filesize >= 5242880) //do not allow file bigger than 5 MB
		{
			$warning = $lang_attachment['text_file_size_too_big'];
		}
		elseif (!in_array($ext, $allowed_exts) || in_array($ext, $banned_ext)) //the file extension is banned
		{
			$warning = $lang_attachment['text_file_extension_not_allowed'];
		}
		else //everythins is okay
		{
			if (in_array($ext, $img_ext))
				$isimage = true;
			else $isimage = false;
            $width = $height = 0;
            $imagesize = [];
            if ($isimage) {
                $imagesize = getimagesize($file['tmp_name']);
                $height = $imagesize[1];
                $width = $imagesize[0];
            }
            //get driver
            $storageDriver = get_setting("image_hosting.driver", "local");
            if ($storageDriver == "local" || !$isimage) {
                if ($savedirectorytype_attachment == 'onedir')
                    $savepath = "";
                elseif ($savedirectorytype_attachment == 'monthdir')
                    $savepath = date("Ym")."/";
                elseif ($savedirectorytype_attachment == 'daydir')
                    $savepath = date("Ymd")."/";
                $filemd5 = md5_file($file['tmp_name']);
                $filename = date("YmdHis").$filemd5;
                $file_location = make_folder($savedirectory_attachment."/", $savepath)  . $filename;
                do_log("file_location: $file_location");
                $db_file_location = $savepath.$filename;
                $abandonorig = false;
                $hasthumb = false;
                if ($isimage) //the uploaded file is a image
                {
                    $maycreatethumb = false;
                    $stop = false;
                    if ($imagesize){
                        $it = $imagesize[2];
                        if ($it != 1 || !$Attach->is_gif_ani($file['tmp_name'])){ //if it is an animation GIF, stop creating thumbnail and adding watermark
                            if ($thumbnailtype_attachment != 'no') //create thumbnail for big image
                            {
                                //determine the size of thumbnail
                                if ($altsize == 'yes'){
                                    $targetwidth = $altthumbwidth_attachment;
                                    $targetheight = $altthumbheight_attachment;
                                }
                                else
                                {
                                    $targetwidth = $thumbwidth_attachment;
                                    $targetheight = $thumbheight_attachment;
                                }
                                $hscale=$height/$targetheight;
                                $wscale=$width/$targetwidth;
                                $scale=($hscale < 1 && $wscale < 1) ? 1 : (( $hscale > $wscale) ? $hscale : $wscale);
                                $newwidth=floor($width/$scale);
                                $newheight=floor($height/$scale);
                                if ($scale != 1){ //thumbnail is needed
                                    if ($it==1)
                                        $orig=@imagecreatefromgif($file["tmp_name"]);
                                    elseif ($it == 2)
                                        $orig=@imagecreatefromjpeg($file["tmp_name"]);
                                    else
                                        $orig=@imagecreatefrompng($file["tmp_name"]);
                                    if ($orig && !$stop)
                                    {
                                        $thumb = imagecreatetruecolor($newwidth, $newheight);
                                        imagecopyresampled($thumb, $orig, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
                                        if ($thumbnailtype_attachment == 'createthumb'){
                                            $hasthumb = true;
                                            imagejpeg($thumb, $file_location.".".$ext.".thumb.jpg", $thumbquality_attachment);
                                        }
                                        elseif ($thumbnailtype_attachment == 'resizebigimg'){
                                            $ext = "jpg";
                                            $filetype = "image/jpeg";
                                            $it = 2;
                                            $height = $newheight;
                                            $width = $newwidth;
                                            $maycreatethumb = true;
                                            $abandonorig = true;
                                        }
                                    }
                                }
                            }
                            $watermarkpos = $watermarkpos_attachment;
                            if ($watermarkpos != 'no' && !$stop) //add watermark to image
                            {
                                if ($width > $watermarkwidth_attachment && $height > $watermarkheight_attachment)
                                {
                                    if ($abandonorig)
                                    {
                                        $resource = $thumb;
                                    }
                                    else
                                    {
                                        $resource=imagecreatetruecolor($width,$height);
                                        if ($it==1)
                                            $resource_p=@imagecreatefromgif($file["tmp_name"]);
                                        elseif ($it==2)
                                            $resource_p=@imagecreatefromjpeg($file["tmp_name"]);
                                        else
                                            $resource_p=@imagecreatefrompng($file["tmp_name"]);
                                        imagecopy($resource, $resource_p, 0, 0, 0, 0, $width, $height);
                                    }
                                    $watermark = imagecreatefrompng('pic/watermark.png');
                                    $watermark_width = imagesx($watermark);
                                    $watermark_height = imagesy($watermark);
                                    //the position of the watermark
                                    if ($watermarkpos == 'random')
                                        $watermarkpos = mt_rand(1, 9);
                                    switch ($watermarkpos)
                                    {
                                        case 1: {
                                            $wmx = 5;
                                            $wmy = 5;
                                            break;
                                        }
                                        case 2: {
                                            $wmx = ($width-$watermark_width)/2;
                                            $wmy = 5;
                                            break;
                                        }
                                        case 3: {
                                            $wmx = $width-$watermark_width-5;
                                            $wmy = 5;
                                            break;
                                        }
                                        case 4: {
                                            $wmx = 5;
                                            $wmy = ($height-$watermark_height)/2;
                                            break;
                                        }
                                        case 5: {
                                            $wmx = ($width-$watermark_width)/2;
                                            $wmy = ($height-$watermark_height)/2;
                                            break;
                                        }
                                        case 6: {
                                            $wmx = $width-$watermark_width-5;
                                            $wmy = ($height-$watermark_height)/2;
                                            break;
                                        }
                                        case 7: {
                                            $wmx = 5;
                                            $wmy = $height-$watermark_height-5;
                                            break;
                                        }
                                        case 8: {
                                            $wmx = ($width-$watermark_width)/2;
                                            $wmy = $height-$watermark_height-5;
                                            break;
                                        }
                                        case 9: {
                                            $wmx = $width-$watermark_width-5;
                                            $wmy = $height-$watermark_height-5;
                                            break;
                                        }
                                    }

                                    imagecopy($resource, $watermark, $wmx, $wmy, 0, 0, $watermark_width, $watermark_height);
                                    if ($it==1)
                                        imagegif($resource, $file_location.".".$ext);
                                    elseif ($it==2)
                                        imagejpeg($resource, $file_location.".".$ext, $watermarkquality_attachment);
                                    else
                                        imagepng($resource, $file_location.".".$ext);
                                    $filesize = filesize($file_location.".".$ext);
                                    $maycreatethumb = false;
                                    $abandonorig = true;
                                }
                            }
                            if ($maycreatethumb){ // if no watermark is added, create the thumbnail now for the above resized image.
                                imagejpeg($thumb, $file_location.".".$ext, $thumbquality_attachment);
                                $filesize = filesize($file_location.".".$ext);
                            }
                        }
                    }
                    else $warning = $lang_attachment['text_invalid_image_file'];
                }
                if (!$abandonorig){
                    if(!move_uploaded_file($file["tmp_name"], $file_location.".".$ext))
                        $warning = $lang_attachment['text_cannot_move_file'];
                }
                $url = $httpdirectory_attachment."/".$db_file_location . ".$ext";
                if ($hasthumb) {
                    $url .= ".thumb.jpg";
                }
                $location = $db_file_location.".".$ext;
            } else {
                try {
                    $driver = \Nexus\Attachment\Storage::getDriver();
                    $location = $driver->uploadGetLocation($file["tmp_name"], $file['name']);
                    do_log("location: $location");
                    $url = $driver->getImageUrl($location);
                } catch (\Exception $exception) {
                    do_log("upload failed: " . $exception->getMessage() . $exception->getTraceAsString(), 'error');
                    $warning = $exception->getMessage();
                }
            }
			if (!$warning) //insert into database and add code to editor
			{
				$dlkey = md5($location . microtime(true));
				sql_query("INSERT INTO attachments (userid, width, added, filename, filetype, filesize, location, dlkey, isimage, thumb, driver) VALUES (".$CURUSER['id'].", ".$width.", ".sqlesc(date("Y-m-d H:i:s")).", ".sqlesc($origfilename).", ".sqlesc($filetype).", ".$filesize.", ".sqlesc($location).", ".sqlesc($dlkey).", ".($isimage ? 1 : 0).", ".($hasthumb ? 1 : 0). "," .sqlesc($storageDriver) . ")") or sqlerr(__FILE__, __LINE__);
				$count_left--;
				if (!empty($_REQUEST['callback_func']) && preg_match('/^preview_custom_field_image_\d+$/', $_REQUEST['callback_func'])) {
                    echo sprintf('<script type="text/javascript">parent.%s("%s", "%s")</script>', $_REQUEST['callback_func'], $dlkey, $url);
                } else {
                    echo("<script type=\"text/javascript\">parent.tag_extimage('". "[attach]" . $dlkey . "[/attach]" . "');</script>");
                }
			}
		}
	}
	print("<form enctype=\"multipart/form-data\" name=\"attachment\" method=\"post\" action=\"attachment.php?callback_func=" . ($_REQUEST['callback_func'] ?? '') . "\">");
	print("<tr>");
	print("<td class=\"embedded attachment-controls\" colspan=\"2\" align=left>");
	print("<input type=\"file\" name=\"file\"".($count_left ? "" : " disabled=\"disabled\"")." />&nbsp;");
	print("<input type=\"checkbox\" name=\"altsize\" value=\"yes\"".($altsize == 'yes' ? " checked=\"checked\"" : "")." />".$lang_attachment['text_small_thumbnail']."&nbsp;");
	print("<input type=\"submit\" name=\"submit\" value=\"".$lang_attachment['submit_upload']."\"".($count_left ? "" : " disabled=\"disabled\"")." /> ");
	if ($warning) {
		print('<span class="striking">'.$warning.'</span>');
	} else {
		print("<b>".$lang_attachment['text_left']."</b><font color=\"red\">".$count_left."</font>".$lang_attachment['text_of'].$count_limit."&nbsp;&nbsp;&nbsp;<b>".$lang_attachment['text_size_limit']."</b>".mksize($size_limit)."&nbsp;&nbsp;&nbsp;<b>".$lang_attachment['text_file_extensions']."</b>");
		$allowedextsblock = "";
		foreach($allowed_exts as $ext) {
			$allowedextsblock .= $ext."/";
		}
		$allowedextsblock = rtrim(trim($allowedextsblock), "/");
		if (!$allowedextsblock) {
			$allowedextsblock = 'N/A';
		}
		print("<span title=\"".htmlspecialchars($allowedextsblock)."\"><i>".$lang_attachment['text_mouse_over_here']."</i></span>");
	}

	print("</td>");
	print("</tr>");
	print("</form>");
}
?>
</table>
</body>
</html>
