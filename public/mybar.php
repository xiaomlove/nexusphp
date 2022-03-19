<?php
require "../include/bittorrent.php";
dbconn();
$userid = intval($_GET["userid"] ?? 0);
$bgpic = intval($_GET["bgpic"] ?? 0);
if (!$userid)
	die;
if (!preg_match("/.*userid=([0-9]+)\.png$/i", $_SERVER['REQUEST_URI']))
	die;
if (!$my_img_string = $Cache->get_value('userbar_'.$_SERVER['REQUEST_URI'])){
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
$my_img=imagecreatefrompng(getFullDirectory("public/pic/userbar/".$bgpic.".png"));
imagealphablending($my_img, false);

if (empty($_GET['noname']))
{
	if (isset($_GET['namered']) && $_GET['namered']>=0 && $_GET['namered']<=255)
		$namered = intval($_GET['namered'] ?? 0);
	else $namered=255;
	if (isset($_GET['namegreen']) && $_GET['namegreen']>=0 && $_GET['namegreen']<=255)
		$namegreen = intval($_GET['namegreen'] ?? 0);
	else $namegreen=255;
	if (isset($_GET['nameblue']) && $_GET['nameblue']>=0 && $_GET['nameblue']<=255)
		$nameblue = intval($_GET['nameblue'] ?? 0);
	else $nameblue=255;
	if (isset($_GET['namesize']) && $_GET['namesize']>=1 && $_GET['namesize']<=5)
		$namesize = intval($_GET['namesize'] ?? 0);
	else $namesize=3;
	if (isset($_GET['namex']) && $_GET['namex']>=0 && $_GET['namex']<=350)
		$namex = intval($_GET['namex'] ?? 0);
	else $namex=10;
	if (isset($_GET['namey']) && $_GET['namey']>=0 && $_GET['namey']<=19)
		$namey = intval($_GET['namey'] ?? 0);
	else $namey=3;
	$name_colour = imagecolorallocate($my_img, $namered, $namegreen, $nameblue);
	imagestring($my_img, $namesize, $namex, $namey, $username, $name_colour);
}

if (empty($_GET['noup']))
{
	if (isset($_GET['upred']) && $_GET['upred']>=0 && $_GET['upred']<=255)
		$upred = intval($_GET['upred'] ?? 0);
	else $upred=0;
	if (isset($_GET['upgreen']) && $_GET['upgreen']>=0 && $_GET['upgreen']<=255)
		$upgreen = intval($_GET['upgreen'] ?? 0);
	else $upgreen=255;
	if (isset($_GET['upblue']) && $_GET['upblue']>=0 && $_GET['upblue']<=255)
		$upblue = intval($_GET['upblue'] ?? 0);
	else $upblue=0;
	if (isset($_GET['upsize']) && $_GET['upsize']>=1 && $_GET['upsize']<=5)
		$upsize = intval($_GET['upsize'] ?? 0);
	else $upsize=3;
	if (isset($_GET['upx']) && $_GET['upx']>=0 && $_GET['upx']<=350)
		$upx = intval($_GET['upx'] ?? 0);
	else $upx=100;
	if (isset($_GET['upy']) && $_GET['upy']>=0 && $_GET['upy']<=19)
		$upy = intval($_GET['upy'] ?? 0);
	else $upy=3;
	$up_colour = imagecolorallocate($my_img, $upred, $upgreen, $upblue);
	imagestring($my_img, $upsize, $upx, $upy, $uploaded, $up_colour);
}

if (empty($_GET['nodown']))
{
	if (isset($_GET['downred']) && $_GET['downred']>=0 && $_GET['downred']<=255)
		$downred = intval($_GET['downred'] ?? 0);
	else $downred=255;
	if (isset($_GET['downgreen']) && $_GET['downgreen']>=0 && $_GET['downgreen']<=255)
		$downgreen = intval($_GET['downgreen'] ?? 0);
	else $downgreen=0;
	if (isset($_GET['downblue']) && $_GET['downblue']>=0 && $_GET['downblue']<=255)
		$downblue = intval($_GET['downblue'] ?? 0);
	else $downblue=0;
	if (isset($_GET['downsize']) && $_GET['downsize']>=1 && $_GET['downsize']<=5)
		$downsize = intval($_GET['downsize'] ?? 0);
	else $downsize=3;
	if (isset($_GET['downx']) && $_GET['downx']>=0 && $_GET['downx']<=350)
		$downx = intval($_GET['downx'] ?? 0);
	else $downx=180;
	if (isset($_GET['downy']) && $_GET['downy']>=0 && $_GET['downy']<=19)
		$downy = $_GET['downy'];
	else $downy=3;
	$down_colour = imagecolorallocate($my_img, $downred, $downgreen, $downblue);
	imagestring($my_img, $downsize, $downx, $downy, $downloaded, $down_colour);
}
imagesavealpha($my_img, true);
ob_start();
imagepng($my_img);
$imgContent = ob_get_contents();
ob_end_clean();
$my_img_string = gzdeflate($imgContent);
$Cache->cache_value('userbar_'.$_SERVER['REQUEST_URI'], $my_img_string, 300);
}
header("Content-type: image/png");
echo gzinflate($my_img_string);
?>

