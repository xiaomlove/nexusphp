<?php
function benc($obj) {
	if (!is_array($obj) || !isset($obj["type"]) || !isset($obj["value"]))
		return;
	$c = $obj["value"];
	switch ($obj["type"]) {
		case "string":
			return benc_str($c);
		case "integer":
			return benc_int($c);
		case "list":
			return benc_list($c);
		case "dictionary":
			return benc_dict($c);
		default:
			return;
	}
}
function benc_str($s) {
	return strlen($s) . ":$s";
}
function benc_int($i) {
	return "i" . $i . "e";
}
function benc_list($a) {
	$s = "l";
	foreach ($a as $e) {
		$s .= benc($e);
	}
	$s .= "e";
	return $s;
}
function benc_dict($d) {
	$s = "d";
	$keys = array_keys($d);
	sort($keys);
	foreach ($keys as $k) {
		$v = $d[$k];
		$s .= benc_str($k);
		$s .= benc($v);
	}
	$s .= "e";
	return $s;
}
function bdec_file($f, $ms) {
	$fp = fopen($f, "rb");
	if (!$fp)
		return;
	$e = fread($fp, $ms);
	fclose($fp);
	return bdec($e);
}
function bdec($s) {
	if (preg_match('/^(\d+):/', $s, $m)) {
		$l = $m[1];
		$pl = strlen($l) + 1;
		$v = substr($s, $pl, $l);
		$ss = substr($s, 0, $pl + $l);
		if (strlen($v) != $l)
			return;
		return array('type' => "string", 'value' => $v, 'strlen' => strlen($ss), 'string' => $ss);
	}
	if (preg_match('/^i(\d+)e/', $s, $m)) {
		$v = $m[1];
		$ss = "i" . $v . "e";
		if ($v === "-0")
			return;
		if ($v[0] == "0" && strlen($v) != 1)
			return;
		return array('type' => "integer", 'value' => $v, 'strlen' => strlen($ss), 'string' => $ss);
	}
	switch ($s[0]) {
		case "l":
			return bdec_list($s);
		case "d":
			return bdec_dict($s);
		default:
			return;
	}
}
function bdec_list($s) {
	if ($s[0] != "l")
		return;
	$sl = strlen($s);
	$i = 1;
	$v = array();
	$ss = "l";
	for (;;) {
		if ($i >= $sl)
			return;
		if ($s[$i] == "e")
			break;
		$ret = bdec(substr($s, $i));
		if (!isset($ret) || !is_array($ret))
			return;
		$v[] = $ret;
		$i += $ret["strlen"];
		$ss .= $ret["string"];
	}
	$ss .= "e";
	return array('type' => "list", 'value' => $v, 'strlen' => strlen($ss), 'string' => $ss);
}
function bdec_dict($s) {
	if ($s[0] != "d")
		return;
	$sl = strlen($s);
	$i = 1;
	$v = array();
	$ss = "d";
	for (;;) {
		if ($i >= $sl)
			return;
		if ($s[$i] == "e")
			break;
		$ret = bdec(substr($s, $i));
		if (!isset($ret) || !is_array($ret) || $ret["type"] != "string")
			return;
		$k = $ret["value"];
		$i += $ret["strlen"];
		$ss .= $ret["string"];
		if ($i >= $sl)
			return;
		$ret = bdec(substr($s, $i));
		if (!isset($ret) || !is_array($ret))
			return;
		$v[$k] = $ret;
		$i += $ret["strlen"];
		$ss .= $ret["string"];
	}
	$ss .= "e";
	return array('type' => "dictionary", 'value' => $v, 'strlen' => strlen($ss), 'string' => $ss);
}
?>