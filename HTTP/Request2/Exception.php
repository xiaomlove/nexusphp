<?php
/**
 * Exception classes for HTTP_Request2 package
 *
 * PHP version 5
 *
 * LICENSE
 *
 * This source file is subject to BSD 3-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.github.com/pear/HTTP_Request2/trunk/docs/LICENSE
 *
 * @category  HTTP
 * @package   HTTP_Request2
 * @author    Alexey Borzov <avb@php.net>
 * @copyright 2008-2020 Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link      http://pear.php.net/package/HTTP_Request2
 */

/**
 * Base class for exceptions in PEAR
 */
require_once 'PEAR/Exception.php';

/**
 * Base exception class for HTTP_Request2 package
 *
 * @category HTTP
 * @package  HTTP_Request2
 * @author   Alexey Borzov <avb@php.net>
 * @license  http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @version  Release: 2.4.2
 * @link     http://pear.php.net/package/HTTP_Request2
 * @link     http://pear.php.net/pepr/pepr-proposal-show.php?id=132
 */
class HTTP_Request2_Exception extends PEAR_Exception
{
    /** An invalid argument was passed to a method */
    const INVALID_ARGUMENT   = 1;
    /** Some required value was not available */
    const MISSING_VALUE      = 2;
    /** Request cannot be processed due to errors in PHP configuration */
    const MISCONFIGURATION   = 3;
    /** Error reading the local file */
    const READ_ERROR         = 4;

    /** Server returned a response that does not conform to HTTP protocol */
    const MALFORMED_RESPONSE = 10;
    /** Failure decoding Content-Encoding or Transfer-Encoding of response */
    const DECODE_ERROR       = 20;
    /** Operation timed out */
    const TIMEOUT            = 30;
    /** Number of redirects exceeded 'max_redirects' configuration parameter */
    const TOO_MANY_REDIRECTS = 40;
    /** Redirect to a protocol other than http(s):// */
    const NON_HTTP_REDIRECT  = 50;

    /**
     * Native error code
     * @var int
     */
    private $_nativeCode;

    /**
     * Constructor, can set package error code and native error code
     *
     * @param string $message    exception message
     * @param int    $code       package error code, one of class constants
     * @param int    $nativeCode error code from underlying PHP extension
     */
    public function __construct($message = null, $code = null, $nativeCode = null)
    {
        parent::__construct($message, $code);
        $this->_nativeCode = $nativeCode;
    }

    /**
     * Returns error code produced by underlying PHP extension
     *
     * For Socket Adapter this may contain error number returned by
     * stream_socket_client(), for Curl Adapter this will contain error number
     * returned by curl_errno()
     *
     * @return integer
     */
    public function getNativeCode()
    {
        return $this->_nativeCode;
    }
}

// backwards compatibility, include the child exceptions if installed with PEAR installer
require_once 'HTTP/Request2/ConnectionException.php';
require_once 'HTTP/Request2/LogicException.php';
require_once 'HTTP/Request2/MessageException.php';
require_once 'HTTP/Request2/NotImplementedException.php';

?>