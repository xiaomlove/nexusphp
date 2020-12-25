<?php

/**
* SASL Mechanisms
* @package SASL
* @author Fredrik Haugbergsmyr <smtp.lib@lagnut.net>
*/
$rootpath = './';
require_once ($rootpath . 'include/smtp/net.const.php');

/**
* @version 0.0.1
* @access public
* @todo phpdoc
*/
class sasl
{


	function _hmac_md5($key, $data)
	{
		if (strlen($key) > 64) {
			$key = pack('H32', md5($key));
        	}

		if (strlen($key) < 64) {
			$key = str_pad($key, 64, chr(0));
		}

		$k_ipad = substr($key, 0, 64) ^ str_repeat(chr(0x36), 64);
		$k_opad = substr($key, 0, 64) ^ str_repeat(chr(0x5C), 64);

		$inner = pack('H32', md5($k_ipad . $data));
		$digest = md5($k_opad . $inner);

		return $digest;
	}

	function cram_md5($user, $pass, $challenge)
	{
		var_dump($challenge);
		$chall = base64_decode($challenge);
		var_dump($chall);
		return base64_encode(sprintf('%s %s', $user, $this->_hmac_md5($pass, $chall)));
	}

	function plain($username, $password)
	{
		return base64_encode(sprintf('%c%s%c%s', 0, $username, 0, $password));
	}

	function login($input)
	{
		return base64_encode(sprintf('%s', $input));
	}
}

?>