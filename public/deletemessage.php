<?php
  require "../include/bittorrent.php";
  $id = $_GET["id"];
  if (!is_numeric($id) || $id < 1 || floor($id) != $id)
    die("Invalid ID");

  $type = $_GET["type"];

  dbconn();
  require_once(get_langfile_path());
  loggedinorreturn();
  if ($type == 'in')
  {
  	// make sure message is in CURUSER's Inbox
	  $arrObj = \Nexus\Database\NexusDB::table('messages')->where('id', (int) $id)->select(['receiver', 'location'])->first();
	  if (!$arrObj) die($lang_deletemessage['std_bad_message_id']);
	  $arr = (array) $arrObj;
	  if ($arr["receiver"] != $CURUSER["id"])
	    die($lang_deletemessage['std_not_suggested']);
    if ($arr["location"] == 'in')
	  	\Nexus\Database\NexusDB::table('messages')->where('id', (int) $id)->delete();
    else if ($arr["location"] == 'both')
			\Nexus\Database\NexusDB::table('messages')->where('id', (int) $id)->update(['location' => 'out']);
    else
    	die($lang_deletemessage['std_not_in_inbox']);
  }
	elseif ($type == 'out')
  {
   	// make sure message is in CURUSER's Sentbox
	  $arrObj = \Nexus\Database\NexusDB::table('messages')->where('id', (int) $id)->select(['sender', 'location'])->first();
	  if (!$arrObj) die($lang_deletemessage['std_bad_message_id']);
	  $arr = (array) $arrObj;
	  if ($arr["sender"] != $CURUSER["id"])
	    die($lang_deletemessage['std_not_suggested']);
    if ($arr["location"] == 'out')
	  	\Nexus\Database\NexusDB::table('messages')->where('id', (int) $id)->delete();
    else if ($arr["location"] == 'both')
			\Nexus\Database\NexusDB::table('messages')->where('id', (int) $id)->update(['location' => 'in']);
    else
    	die($lang_deletemessage['std_not_in_sentbox']);
  }
  else
  	die($lang_deletemessage['std_unknown_pm_type']);
  header("Location: " . get_protocol_prefix() . "$BASEURL/messages.php".($type == 'out'?"?out=1":""));
?>
