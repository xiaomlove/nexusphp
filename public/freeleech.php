<?php

use App\Events\TorrentPromotionChanged;
use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
loggedinorreturn();
if (get_user_class() < UC_ADMINISTRATOR) {
    stderr('Error', 'Permission denied.');
}

$action = isset($_POST['action']) ? htmlspecialchars($_POST['action']) : (isset($_GET['action']) ? htmlspecialchars($_GET['action']) : 'main');
if ($action == 'setallfree') {
    NexusDB::table('torrents_state')->update(['global_sp_state' => 2]);
    $Cache->delete_value('global_promotion_state');
    TorrentPromotionChanged::dispatch(0, 2, true);
    stderr('Success', 'All torrents have been set free..');
} elseif ($action == 'setall2up') {
    NexusDB::table('torrents_state')->update(['global_sp_state' => 3]);
    $Cache->delete_value('global_promotion_state');
    TorrentPromotionChanged::dispatch(0, 3, true);
    stderr('Success', 'All torrents have been set 2x up..');
} elseif ($action == 'setall2up_free') {
    NexusDB::table('torrents_state')->update(['global_sp_state' => 4]);
    $Cache->delete_value('global_promotion_state');
    TorrentPromotionChanged::dispatch(0, 4, true);
    stderr('Success', 'All torrents have been set 2x up and free..');
} elseif ($action == 'setallhalf_down') {
    NexusDB::table('torrents_state')->update(['global_sp_state' => 5]);
    $Cache->delete_value('global_promotion_state');
    TorrentPromotionChanged::dispatch(0, 5, true);
    stderr('Success', 'All torrents have been set half down..');
} elseif ($action == 'setall2up_half_down') {
    NexusDB::table('torrents_state')->update(['global_sp_state' => 6]);
    $Cache->delete_value('global_promotion_state');
    TorrentPromotionChanged::dispatch(0, 6, true);
    stderr('Success', 'All torrents have been set half down..');
} elseif ($action == 'setallnormal') {
    NexusDB::table('torrents_state')->update(['global_sp_state' => 1]);
    $Cache->delete_value('global_promotion_state');
    TorrentPromotionChanged::dispatch(0, 1, true);
    stderr('Success', 'All torrents have been set normal..');
} elseif ($action == 'main') {
    stderr('Select action', 'Click <a class=altlink href=freeleech.php?action=setallfree>here</a> to set all torrents free.. <br /> Click <a class=altlink href=freeleech.php?action=setall2up>here</a> to set all torrents 2x up..<br /> Click <a class=altlink href=freeleech.php?action=setall2up_free>here</a> to set all torrents 2x up and free.. <br />Click <a class=altlink href=freeleech.php?action=setallhalf_down>here</a> to set all torrents half down..<br />Click <a class=altlink href=freeleech.php?action=setall2up_half_down>here</a> to set all torrents 2x up and half down..<br />Click <a class=altlink href=freeleech.php?action=setallnormal>here</a> to set all torrents normal..', false);
}
