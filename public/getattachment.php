<?php

use Nexus\Database\NexusDB;

require_once '../include/bittorrent.php';
dbconn();
loggedinorreturn();
parked();
$id = (int) $_GET['id'];

if (! $id) {
    exit('Invalid id.');
}
$dlkey = $_GET['dlkey'];

if (! $dlkey) {
    exit('Invalid key');
}
$row = NexusDB::table('attachments')
    ->where('id', (int) $id)
    ->where('dlkey', (string) $dlkey)
    ->first();
$row = $row ? (array) $row : null;
if (! $row) {
    exit('No attachment found.');
}
$filelocation = $httpdirectory_attachment.'/'.$row['location'];
if (! is_file($filelocation) || ! is_readable($filelocation)) {
    exit('File not found or cannot be read.');
}
$f = fopen($filelocation, 'rb');
if (! $f) {
    exit('Cannot open file');
}

header('Content-Length: '.$row['filesize']);
header('Content-Type: application/octet-stream');

if (str_replace('Gecko', '', $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT']) {
    header("Content-Disposition: attachment; filename=\"$row[filename]\" ; charset=utf-8");
} elseif (str_replace('Firefox', '', $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT']) {
    header("Content-Disposition: attachment; filename=\"$row[filename]\" ; charset=utf-8");
} elseif (str_replace('Opera', '', $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT']) {
    header("Content-Disposition: attachment; filename=\"$row[filename]\" ; charset=utf-8");
} elseif (str_replace('IE', '', $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT']) {
    header('Content-Disposition: attachment; filename='.str_replace('+', '%20', rawurlencode($row[filename])));
} else {
    header('Content-Disposition: attachment; filename='.str_replace('+', '%20', rawurlencode($row[filename])));
}

do {
    $s = fread($f, 4096);
    echo $s;
} while (! feof($f));
NexusDB::table('attachments')->where('id', (int) $id)->increment('downloads');
$Cache->delete_value('attachment_'.$dlkey.'_content');
exit;
