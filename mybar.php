<?php
require "include/bittorrent.php";
dbconn();
$userid = 0 + $_GET["userid"];
$bgpic = 0 + $_GET["bgpic"];
if (!$userid)
	die;
if (!preg_match("/.*userid=([0-9]+)\.png$/i", $_SERVER['REQUEST_URI']))
	die;
if (!$my_img = $Cache->get_value('userbar_'.$_SERVER['REQUEST_URI'])){
$res = sql_query("SELECT username, uploaded, downloaded, class, privacy FROM users WHERE id=".sqlesc($userid)." LIMIT 1");
$row = mysql_fetch_array($res);
if (!$row)
	die;
elseif($row['privacy'] == 'strong')
	die;
elseif($row['class'] < $userbar_class)
	die;
else{
	$username = $row['username'];
	$uploaded = mksize($row['uploaded']);
	$downloaded = mksize($row['downloaded']);
}
$my_img=imagecreatefrompng("pic/userbar/".$bgpic.".png");
imagealphablending($my_img, false);

if (!$_GET['noname'])
{
	if (isset($_GET['namered']) && $_GET['namered']>=0 && $_GET['namered']<=255)
		$namered = 0 + $_GET['namered'];
	else $namered=255;
	if (isset($_GET['namegreen']) && $_GET['namegreen']>=0 && $_GET['namegreen']<=255)
		$namegreen = 0 + $_GET['namegreen'];
	else $namegreen=255;
	if (isset($_GET['nameblue']) && $_GET['nameblue']>=0 && $_GET['nameblue']<=255)
		$nameblue = 0 + $_GET['nameblue'];
	else $nameblue=255;
	if (isset($_GET['namesize']) && $_GET['namesize']>=1 && $_GET['namesize']<=5)
		$namesize = 0 + $_GET['namesize'];
	else $namesize=3;
	if (isset($_GET['namex']) && $_GET['namex']>=0 && $_GET['namex']<=350)
		$namex = 0 + $_GET['namex'];
	else $namex=10;
	if (isset($_GET['namey']) && $_GET['namey']>=0 && $_GET['namey']<=19)
		$namey = 0 + $_GET['namey'];
	else $namey=3;
	$name_colour = imagecolorallocate($my_img, $namered, $namegreen, $nameblue);
	imagestring($my_img, $namesize, $namex, $namey, $username, $name_colour);
}

if (!$_GET['noup'])
{
	if (isset($_GET['upred']) && $_GET['upred']>=0 && $_GET['upred']<=255)
		$upred = 0 + $_GET['upred'];
	else $upred=0;
	if (isset($_GET['upgreen']) && $_GET['upgreen']>=0 && $_GET['upgreen']<=255)
		$upgreen = 0 + $_GET['upgreen'];
	else $upgreen=255;
	if (isset($_GET['upblue']) && $_GET['upblue']>=0 && $_GET['upblue']<=255)
		$upblue = 0 + $_GET['upblue'];
	else $upblue=0;
	if (isset($_GET['upsize']) && $_GET['upsize']>=1 && $_GET['upsize']<=5)
		$upsize = 0 + $_GET['upsize'];
	else $upsize=3;
	if (isset($_GET['upx']) && $_GET['upx']>=0 && $_GET['upx']<=350)
		$upx = 0 + $_GET['upx'];
	else $upx=100;
	if (isset($_GET['upy']) && $_GET['upy']>=0 && $_GET['upy']<=19)
		$upy = 0 + $_GET['upy'];
	else $upy=3;
	$up_colour = imagecolorallocate($my_img, $upred, $upgreen, $upblue);
	imagestring($my_img, $upsize, $upx, $upy, $uploaded, $up_colour);
}

if (!$_GET['nodown'])
{
	if (isset($_GET['downred']) && $_GET['downred']>=0 && $_GET['downred']<=255)
		$downred = 0 + $_GET['downred'];
	else $downred=255;
	if (isset($_GET['downgreen']) && $_GET['downgreen']>=0 && $_GET['downgreen']<=255)
		$downgreen = 0 + $_GET['downgreen'];
	else $downgreen=0;
	if (isset($_GET['downblue']) && $_GET['downblue']>=0 && $_GET['downblue']<=255)
		$downblue = 0 + $_GET['downblue'];
	else $downblue=0;
	if (isset($_GET['downsize']) && $_GET['downsize']>=1 && $_GET['downsize']<=5)
		$downsize = 0 + $_GET['downsize'];
	else $downsize=3;
	if (isset($_GET['downx']) && $_GET['downx']>=0 && $_GET['downx']<=350)
		$downx = 0 + $_GET['downx'];
	else $downx=180;
	if (isset($_GET['downy']) && $_GET['downy']>=0 && $_GET['downy']<=19)
		$downy = 0 + $_GET['downy'];
	else $downy=3;
	$down_colour = imagecolorallocate($my_img, $downred, $downgreen, $downblue);
	imagestring($my_img, $downsize, $downx, $downy, $downloaded, $down_colour);
}
imagesavealpha($my_img, true);
$Cache->cache_value('userbar_'.$_SERVER['REQUEST_URI'], $my_img, 300);
}
header("Content-type: image/png");
imagepng($my_img);
imagedestroy($my_img);
?>

