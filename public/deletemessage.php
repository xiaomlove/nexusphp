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
	  $res = sql_query("SELECT receiver, location FROM messages WHERE id=" . sqlesc($id)) or die("barf");
	  $arr = mysql_fetch_array($res) or die($lang_deletemessage['std_bad_message_id']);
	  if ($arr["receiver"] != $CURUSER["id"])
	    die($lang_deletemessage['std_not_suggested']);
    if ($arr["location"] == 'in')
	  	sql_query("DELETE FROM messages WHERE id=" . sqlesc($id)) or die('delete failed (error code 1).. this should never happen, contact an admin.');
    else if ($arr["location"] == 'both')
			sql_query("UPDATE messages SET location = 'out' WHERE id=" . sqlesc($id)) or die('delete failed (error code 2).. this should never happen, contact an admin.');
    else
    	die($lang_deletemessage['std_not_in_inbox']);
  }
	elseif ($type == 'out')
  {
   	// make sure message is in CURUSER's Sentbox
	  $res = sql_query("SELECT sender, location FROM messages WHERE id=" . sqlesc($id)) or die("barf");
	  $arr = mysql_fetch_array($res) or die($lang_deletemessage['std_bad_message_id']);
	  if ($arr["sender"] != $CURUSER["id"])
	    die($lang_deletemessage['std_not_suggested']);
    if ($arr["location"] == 'out')
	  	sql_query("DELETE FROM messages WHERE id=" . sqlesc($id)) or die('delete failed (error code 3).. this should never happen, contact an admin.');
    else if ($arr["location"] == 'both')
			sql_query("UPDATE messages SET location = 'in' WHERE id=" . sqlesc($id)) or die('delete failed (error code 4).. this should never happen, contact an admin.');
    else
    	die($lang_deletemessage['std_not_in_sentbox']);
  }
  else
  	die($lang_deletemessage['std_unknown_pm_type']);
  header("Location: " . get_protocol_prefix() . "$BASEURL/messages.php".($type == 'out'?"?out=1":""));
?>
