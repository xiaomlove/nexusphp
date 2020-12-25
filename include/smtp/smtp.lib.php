<?php

/**
* Allows users to send email without e-mailserver on the localmachine
* @package SMTP
* @author Fredrik Haugbergsmyr <smtp.lib@lagnut.net>
*/

require_once ('net.const.php');

/**
* @version 0.0.2.2
* @access public
* @todo split messages, attachments
*/
class smtp
{


	/**
	* lagnut-smtp version, send in the headers
	*
	* @var string
	* @access private
	*/
	var $_version = '0.0.2.2';


	/**
	* Turn debugon / off
	*
	* @var bool
	* @access private
	*/
	var $_debug = false;


	/**
	* Serverconnection resource
	*
	* @var resource
	* @access private
	*/
	var $_connection = null;


	/**
	* E-mailheaders
	*
	* @var array headers
	* @access private
	*/
	var $_hdrs = array();


	/**
	* E-mailbody
	*
	* @var string
	* @access private
	*/
	var $_body = '';


	/**
	* Default Content type
	*
	* @var string
	* @access private
	*/
	var $_mime = 'text/html';


	/**
	* Default Charset
	*
	* @var string
	* @access private
	*/
	var $_charset = 'UTF-8';

	/**
	* Default Transfer-Content-Encoding
	*
	* @var string
	* @access private
	*/
	var $_CTEncoding = 'base64';
	
	// These are actually not necessary, but for the shitty eYou email system
	/**
	* Charset for Special Case
	*
	* @var string
	* @access private
	*/
	var $_charset_eYou = 'GBK';

	/**
	* Charset for Special Case
	*
	* @var string
	* @access private
	*/
	var $_specialcase = 'eYou';
	
	/**
	* Class contruction, sets client headers
	*
	* @access public
	*/
	function smtp($charset = 'UTF-8', $specialcase = "")
	{
		$this->_specialcase  = $specialcase;
		$this->_charset = $charset;
		$this->_add_hdr('X-Mailer', sprintf('LAGNUT-SMTP/%s', $this->_version));
		$this->_add_hdr('User-Agent', sprintf('LAGNUT-SMTP/%s', $this->_version));
		$this->_add_hdr('MIME-Version', '1.0');
	}


	/**
	* Turn debugging on/off
	*
	* @access public
	* @param bool $debug command
	*/
	function debug($debug)
	{
		$this->_debug = (bool)$debug;
	}


	/**
	* Clean input to prevent injection
	*
	* @param string $input User data
	*/
	function _clean(&$input)
	{
		if (!is_string($input)) {
			return false;
		}
		$input = urldecode($input);
		$input = str_replace("\n", '', str_replace("\r", '', $input));
	}


	/**
	* Send command to server
	*
	* @access private
	* @param string $cmdcommand
	* @param optional $data data
	*/
	function _cmd($cmd, $data = false)
	{
		$this->_clean($cmd);
		$this->_clean($data);

		if ($this->_is_closed()) {
			return false;
		}

		if (!$data) {
			$command = sprintf("%s\r\n", $cmd);
		}else {
			$command = sprintf("%s: %s\r\n", $cmd,$data);
		}

		fwrite($this->_connection, $command);
		$resp = $this->_read();
		if ($this->_debug){
			printf($command);
			printf($resp);
		}
		if ($this->_is_closed($resp)) {
			return false;
		}
		return $resp;
	}


	/**
	* Collects header
	*
	* @access private
	* @param string$key
	* @param string $data
	*/
	function _add_hdr($key, $data)
	{
		$this->_clean($key);
		$this->_clean($data);
		$this->_hdrs[$key] = sprintf("%s: %s\r\n", $key, $data);
	}


	/**
	* Read server output
	*
	* @access private
	* @return string
	*/
	function _read()
	{
		if ($this->_is_closed()) {
			return false;
		}
		$o = '';
		do {
			$str = @fgets($this->_connection, 515);
			if (!$str) {
				break;
			}
			$o .= $str;
			if (substr($str, 3, 1) == ' ') {
				break;
			}
		} while (true);
		return $o;
	}


	/**
	* Checks if server denies more commands
	*
	* @access private
	* @param $int
	* @return bool true if connection is closed
	*/
	function _is_closed($response = false)
	{
		if (!$this->_connection) {
			return true;
		}
		if (isset($response{0}) && ($response{0} == 4|| $response{0}== 5)) {
			$this->close();
			return true;
		}
		return false;
	}


	/**
	* Open connection to server
	*
	* @access public
	* @param string $server SMTP server
	* @param int $port Server port
	*/
	function open($server, $port = 25)
	{
		$this->_connection = fsockopen($server, $port, $e, $er, 8);

		if ($this->_is_closed()) {
			return false;
		}

		$init= $this->_read();
		if ($this->_debug){
			printf($init);
		}

		if ($this->_is_closed($init)) {
			return false;
		}
		
		$lhost = (isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1');

		if (strpos($init,'ESMTP') === false){
			$this->_cmd('HELO '. gethostbyaddr($lhost));
		} else {
			$this->_cmd('EHLO '. gethostbyaddr($lhost));
		}
	}


	/**
	* Start TLS communication
	*
	* @access public
	*/
	function start_tls()
	{
		if (!function_exists('stream_socket_enable_crypto')) {
			trigger_error('TLS is not supported', E_USER_ERROR);
			return false;
		}
		$this->_cmd('STARTTLS');
		stream_socket_enable_crypto($this->_connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
	}


	/**
	* Performs SMTP authentication
	*
	* @access public
	* @param string $username username
	* @param string $password password
	* @param int authentication mecanism
	*/
	function auth($username, $password, $type = LOGIN)
	{
		
		include_once ('sasl.lib.php');
		$sasl =& new sasl($sasl, $username, $password);
		switch ($type) {
			case PLAIN:
				$this->_cmd('AUTH PLAIN');
				$this->_cmd($sasl->plain($username, $password));
				break;
			case LOGIN:
				$this->_cmd('AUTH LOGIN');
				$this->_cmd($sasl->login($username));
				$this->_cmd($sasl->login($password));
				break;
			case CRAM_MD5:
				$resp = explode(' ', $this->_cmd('AUTH CRAM-MD5'));
				$this->_cmd($sasl->cram_md5($username, $password, trim($resp[1])));
				break;
		}
	}


	/**
	* Closes connection to the server
	*
	* @access public
	*/
	function close()
	{
		if ($this->_is_closed()) {
			return false;
		}

		$this->_cmd('RSET');
		$this->_cmd('QUIT');
		fclose($this->_connection);
		$this->_connection = null;
	}


	/**
	* E-mail sender
	*
	* @access public
	* @param string $from Sender
	*/
	function from($email, $name = '')
	{
		$from = !empty($name) ? sprintf('%s <%s>', $name, $email) : $email;
		$this->_cmd('MAIL FROM', sprintf('<%s>', $email));
		$this->_add_hdr('FROM', $from);
		$this->_add_hdr('Return-path', $email);
	}

	/**
	* Set BCC header
	*
	* @access public
	* @param string $tolist recipients whose email address should be concealed
	*/
	function bcc($tolist)
	{
		$this->_add_hdr('Bcc', $tolist);
	}
	
	
	/**
	* Send reply-to header
	*
	* @param string $to
	*/
	function reply_to($email, $name = '')
	{
		$to = !empty($name) ? sprintf('%s <%s>', $name, $email) : $email;
		$this->_add_hdr('REPLY-TO', $to);
	}


	/**
	* E-mail reciever
	*
	* @access public
	* @param string $to Reciever
	*/
	function to($email, $name = '')
	{
		$to = !empty($name) ? sprintf('%s <%s>', $name, $email) : $email;
		$this->_cmd('RCPT TO', sprintf('<%s>', $email));
		$this->_add_hdr('TO', $to);
	}

	/**
	* Multiple E-mail reciever
	*
	* @access public
	* @param string $email Reciever, with out other recepients info disclosed
	*/
	function multi_to($email)
	{
		$this->_cmd('RCPT TO', sprintf('<%s>', $email));
	}
	
	/**
	* E-mail reciever
	*
	* @access public
	* @param string $email TO head on mass mailing
	*/
	function multi_to_head($to)
	{
		$this->_add_hdr('TO', $to);
	}
		
	/**
	* MIME type
	*
	* @access public
	* @param string $mime MIME type
	*/
	function mime_charset($mime = 'text/html',$charset = 'UTF-8')
	{
		$this->_charset = $charset;
		$this->_mime = $mime;
		$this->_add_hdr('Content-type', sprintf('%s; charset=%s', $this->_mime, $this->_charset));
	}

	/**
	* MIME Content-Transfer-Encoding
	*
	* @access public
	* @param string $mime MIME type
	*/
	function mime_content_transfer_encoding($CTEncoding = 'base64')
	{
		$this->_CTEncoding = $CTEncoding;
		$this->_add_hdr('Content-Transfer-Encoding', sprintf('%s', $this->_CTEncoding));
	}
	
	/**
	* E-mail subject
	*
	* @access public
	* @param string $subject subject
	*/
	function subject($subject)
	{
		$this->_clean($subject);
		
		if($this->_specialcase = "")
			$this->_add_hdr('SUBJECT', $this->encode_hdrs($subject));
		elseif($this->_specialcase = "eYou")
		{
			$temp = $this->_charset;
			$this->_charset = $this->_charset_eYou;
			$this->_add_hdr('SUBJECT', $this->encode_hdrs($subject));
			$this->_charset = $temp;
		}
	}


	/**
	* E-mail body
	*
	* @access public
	* @param string $body body
	*/
	function body($body)
	{
		$body = preg_replace("/([\n|\r])\.([\n|\r])/", "$1..$2", $body);
		
		if($this->_CTEncoding == 'base64')
			$this->_body = sprintf("\r\n%s", base64_encode($body));
	}


	/**
	* Send the mail
	*
	* @access public
	*/
	function send()
	{
		$resp = $this->_cmd('DATA');
		if ($this->_is_closed($resp)) {
			$this->close();
			return false;
		}
		foreach ($this->_hdrs as $header) {
			fwrite($this->_connection, $header);
			if ($this->_debug) {
				printf($header);
			}
		}
		fwrite($this->_connection,$this->_body);
		fwrite($this->_connection, "\r\n.\r\n");
		$resp = trim($this->_read());
		if ($this->_debug){
			printf("%s\r\n", $this->_body);
			printf("\r\n.\r\n");
			printf('%s', $resp);
		}
		if ((int)$resp{0} != 2) {
			return false;
		} else {
			return true;
		}
	}


	/**
	* encode headers
	*
	* @access private
	* @param string $input
	* @return string
	*/
	function encode_hdrs($input)
	{
		$replacement = preg_replace('/([\x80-\xFF])/e', '"=" . strtoupper(dechex(ord("\1")))', $input);
		
		$input = str_replace($input, sprintf('=?%s?Q?%s?=', $this->_charset, $replacement), $input);
		return $input;
	}


}

?>
