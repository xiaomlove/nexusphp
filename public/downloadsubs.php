<?php

use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
if (! $CURUSER) {
    header('Location: '.get_protocol_prefix()."$BASEURL/");
    exit;
}

$filename = $_GET['subid'];
$dirname = $_GET['torrentid'];

if (! $filename || ! $dirname) {
    exit("File name missing\n");
}

$filename = intval($filename ?? 0);
$dirname = intval($dirname ?? 0);

$arr = NexusDB::table('subs')->where('id', (int) $filename)->first();
$arr = $arr ? (array) $arr : null;
if (! $arr) {
    exit("Not found\n");
}

NexusDB::table('subs')->where('id', (int) $filename)->increment('hits');
$file = ROOT_PATH."$SUBSPATH/$dirname/$filename.$arr[ext]";

if (! is_file($file)) {
    exit("File not found\n");
}
$f = fopen($file, 'rb');
if (! $f) {
    exit("Cannot open file\n");
}
header('Content-Length: '.filesize($file));
header('Content-Type: application/octet-stream');

if (str_replace('Gecko', '', $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT']) {
    header("Content-Disposition: attachment; filename=\"$arr[filename]\" ; charset=utf-8");
} elseif (str_replace('Firefox', '', $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT']) {
    header("Content-Disposition: attachment; filename=\"$arr[filename]\" ; charset=utf-8");
} elseif (str_replace('Opera', '', $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT']) {
    header("Content-Disposition: attachment; filename=\"$arr[filename]\" ; charset=utf-8");
} elseif (str_replace('IE', '', $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT']) {
    header('Content-Disposition: attachment; filename='.str_replace('+', '%20', rawurlencode($arr['filename'])));
} else {
    header('Content-Disposition: attachment; filename='.str_replace('+', '%20', rawurlencode($arr['filename'])));
}

do {
    $s = fread($f, 4096);
    echo $s;
} while (! feof($f));
// closefile($f);
exit;
