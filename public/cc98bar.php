<?php
require "../include/bittorrent.php";
dbconn();
$pattern = "/.*cc98bar\.php\/(nn([0,1]{1}))?(nr([0-9]+))?(ng([0-9]+))?(nb([0-9]+))?(ns([1-5]{1}))?(nx([0-9]+))?(ny([0-9]+))?(nu([0,1]{1}))?(ur([0-9]+))?(ug([0-9]+))?(ub([0-9]+))?(us([1-5]{1}))?(ux([0-9]+))?(uy([0-9]+))?(nd([0,1]{1}))?(dr([0-9]+))?(dg([0-9]+))?(db([0-9]+))?(ds([1-5]{1}))?(dx([0-9]+))?(dy([0-9]+))?(bg([0-9]+))?id([0-9]+)\.png$/i";
if (!preg_match($pattern, $_SERVER['REQUEST_URI'])){
echo "Error! Invalid URL format.";
	die;
}
if (!$my_img = $Cache->get_value('userbar_'.$_SERVER['REQUEST_URI'])){
$nn = preg_replace($pattern, "\\2", $_SERVER['REQUEST_URI']);
$nr = preg_replace($pattern, "\\4", $_SERVER['REQUEST_URI']);
$ng = preg_replace($pattern, "\\6", $_SERVER['REQUEST_URI']);
$nb = preg_replace($pattern, "\\8", $_SERVER['REQUEST_URI']);
$ns = preg_replace($pattern, "\\10", $_SERVER['REQUEST_URI']);
$nx = preg_replace($pattern, "\\12", $_SERVER['REQUEST_URI']);
$ny = preg_replace($pattern, "\\14", $_SERVER['REQUEST_URI']);
$nu = preg_replace($pattern, "\\16", $_SERVER['REQUEST_URI']);
$ur = preg_replace($pattern, "\\18", $_SERVER['REQUEST_URI']);
$ug = preg_replace($pattern, "\\20", $_SERVER['REQUEST_URI']);
$ub = preg_replace($pattern, "\\22", $_SERVER['REQUEST_URI']);
$us = preg_replace($pattern, "\\24", $_SERVER['REQUEST_URI']);
$ux = preg_replace($pattern, "\\26", $_SERVER['REQUEST_URI']);
$uy = preg_replace($pattern, "\\28", $_SERVER['REQUEST_URI']);
$nd = preg_replace($pattern, "\\30", $_SERVER['REQUEST_URI']);
$dr = preg_replace($pattern, "\\32", $_SERVER['REQUEST_URI']);
$dg = preg_replace($pattern, "\\34", $_SERVER['REQUEST_URI']);
$db = preg_replace($pattern, "\\36", $_SERVER['REQUEST_URI']);
$ds = preg_replace($pattern, "\\38", $_SERVER['REQUEST_URI']);
$dx = preg_replace($pattern, "\\40", $_SERVER['REQUEST_URI']);
$dy = preg_replace($pattern, "\\42", $_SERVER['REQUEST_URI']);
$bg = (int)preg_replace($pattern, "\\44", $_SERVER['REQUEST_URI']);
$id = preg_replace($pattern, "\\45", $_SERVER['REQUEST_URI']);

$res = sql_query("SELECT username, uploaded, downloaded, class, privacy FROM users WHERE id=".sqlesc($id)." LIMIT 1");
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

$my_img=imagecreatefrompng("pic/userbar/".$bg.".png");
imagealphablending($my_img, false);

if (!$nn)
{
	if ($nr != "" && $nr >=0 && $nr <=255)
		$namered = $nr;
	else $namered=255;
	if ($ng != "" && $ng >=0 && $ng <=255)
		$namegreen = $ng;
	else $namegreen=255;
	if ($nb != "" && $nb >=0 && $nb <=255)
		$nameblue = $nb;
	else $nameblue=255;
	if ($ns != "" && $ns >=1 && $ns <=5)
		$namesize = $ns;
	else $namesize=3;
	if ($nx != "" && $nx >=0 && $nx <=350)
		$namex = $nx;
	else $namex=10;
	if ($ny != "" && $ny >=0 && $ny <=19)
		$namey = $ny;
	else $namey=3;
	$name_colour = imagecolorallocate($my_img, $namered, $namegreen, $nameblue);
	imagestring($my_img, $namesize, $namex, $namey, $username, $name_colour);
}

if (!$nu)
{
	if ($ur != "" && $ur >=0 && $ur <=255)
		$upred = $ur;
	else $upred=0;
	if ($ug != "" && $ug >=0 && $ug <=255)
		$upgreen = $ug;
	else $upgreen=255;
	if ($ub != "" && $ub >=0 && $ub <=255)
		$upblue = $ub;
	else $upblue=0;
	if ($us != "" && $us >=1 && $us <=5)
		$upsize = $us;
	else $upsize=3;
	if ($ux != "" && $ux >=0 && $ux <=350)
		$upx = $ux;
	else $upx=100;
	if ($uy != "" && $uy >=0 && $uy <=19)
		$upy = $uy;
	else $upy=3;
	$up_colour = imagecolorallocate($my_img, $upred, $upgreen, $upblue);
	imagestring($my_img, $upsize, $upx, $upy, $uploaded, $up_colour);
}

if (!$nd)
{
	if ($dr != "" && $dr >=0 && $dr <=255)
		$downred = $dr;
	else $downred=255;
	if ($dg != "" && $dg >=0 && $dg <=255)
		$downgreen = $dg;
	else $downgreen=0;
	if ($dg != "" && $db >=0 && $db <=255)
		$downblue = $db;
	else $downblue=0;
	if ($ds != "" && $ds >=1 && $ds <=5)
		$downsize = $ds;
	else $downsize=3;
	if ($dx != "" && $dx >=0 && $dx <=350)
		$downx = $dx;
	else $downx=180;
	if ($dy != "" && $dy >=0 && $dy <=19)
		$downy = $dy;
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

