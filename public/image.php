<?php
require_once("../include/bittorrent.php");
dbconn();
$action = $_GET['action'];
$imagehash = $_GET['imagehash'];
if($action == "regimage")
{
		$query = "SELECT * FROM regimages WHERE imagehash= ".sqlesc($imagehash);
		$sql = sql_query($query);
		$regimage = mysql_fetch_array($sql);
		$imagestring = $regimage['imagestring'];
		$space = $newstring = '';
		for($i=0;$i<strlen($imagestring);$i++)
		{
			$newstring .= $space.$imagestring[$i];
			$space = " ";
		}
		$imagestring = $newstring;

	if(function_exists("imagecreatefrompng"))
	{
		$fontwidth = imageFontWidth(5);
		$fontheight = imageFontHeight(5);
		$textwidth = $fontwidth*strlen($imagestring);
		$textheight = $fontheight;

		$randimg = rand(1, 5);
		$im = imagecreatefrompng("pic/regimages/reg".$randimg.".png");

		$imgheight = 40;
		$imgwidth = 150;
		$textposh = floor(($imgwidth-$textwidth)/2);
		$textposv = floor(($imgheight-$textheight)/2);

			$dots = $imgheight*$imgwidth/35;
			$gd = imagecreatetruecolor($imgwidth, $imgheight);
			for($i=1;$i<=$dots;$i++)
			{
				imagesetpixel($im, rand(0, $imgwidth), rand(0, $imgheight), imagecolorallocate($gd, rand(0, 255), rand(0, 255), rand(0, 255)));
			}

		$textcolor = imagecolorallocate($im, 0, 0, 0);
		imagestring($im, 5, $textposh, $textposv, $imagestring, $textcolor);

		// output the image
		header("Content-type: image/png");
		imagepng($im);
		imagedestroy($im);
		exit;
	}
	else
	{
		header("Location: pic/clear.gif");
	}
}
else
{
	die('invalid action');
}
?>
