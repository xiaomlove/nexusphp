<?php

$mechs = array('LOGIN', 'PLAIN', 'CRAM_MD5');

foreach ($mechs as $mech) {
	if (!defined($mech)) {
		define($mech, $mech);
	} elseif (constant($mech) != $mech) {
		trigger_error(sprintf("Constant %s already defined, can't proceed", $mech), E_USER_ERROR);
	}
}

?>