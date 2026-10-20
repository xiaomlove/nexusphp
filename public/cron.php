<?php
require_once("../include/bittorrent.php");
dbconn();
require_once("../include/medal_fragments.php"); medal_fragment_ensure_tables(); medal_fragment_hourly_reward(); medal_fragment_seed_reward(); medal_fragment_attendance_reward();
if ($useCronTriggerCleanUp) {
	$return = autoclean();
	if ($return) {
		echo $return."\n";
	} else {
		echo "Clean-up not triggered.\n";
	}
} else {
	echo "Forbidden. Clean-up is set to be browser-triggered.\n";
}
