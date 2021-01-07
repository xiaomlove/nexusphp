<?php
/**
 * Socket wrapper class used by Socket Adapter
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

/** Exception classes for HTTP_Request2 package */
require_once 'HTTP/Request2/Exception.php';

/**
 * Socket wrapper class used by Socket Adapter
 *
 * Needed to properly handle connection errors, global timeout support and
 * similar things. Loosely based on Net_Socket used by older HTTP_Request.
 *
 * @category HTTP
 * @package  HTTP_Request2
 * @author   Alexey Borzov <avb@php.net>
 * @license  http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @version  Release: 2.4.2
 * @link     http://pear.php.net/package/HTTP_Request2
 * @link     http://pear.php.net/bugs/bug.php?id=19332
 * @link     http://tools.ietf.org/html/rfc1928
 */
class HTTP_Request2_SocketWrapper
{
    /**
     * PHP warning messages raised during stream_socket_client() call
     * @var array
     */
    protected $connectionWarnings = [];

    /**
     * Connected socket
     * @var resource
     */
    protected $socket;

    /**
     * Sum of start time and global timeout, exception will be thrown if request continues past this time
     * @var float
     */
    protected $deadline;

    /**
     * Global timeout value, mostly for exception messages
     * @var integer
     */
    protected $timeout;

    /**
     * Class constructor, tries to establish connection
     *
     * @param string $address        Address for stream_socket_client() call,
     *                               e.g. 'tcp://localhost:80'
     * @param int    $timeout        Connection timeout (seconds)
     * @param array  $contextOptions Context options
     *
     * @throws HTTP_Request2_LogicException
     * @throws HTTP_Request2_ConnectionException
     */
    public function __construct($address, $timeout, array $contextOptions = [])
    {
        if (!empty($contextOptions)
            && !isset($contextOptions['socket']) && !isset($contextOptions['ssl'])
        ) {
            // Backwards compatibility with 2.1.0 and 2.1.1 releases
            $contextOptions = ['ssl' => $contextOptions];
        }
        if (isset($contextOptions['ssl'])) {
            $contextOptions['ssl'] += [
                // Using "Intermediate compatibility" cipher bundle from
                // https://wiki.mozilla.org/Security/Server_Side_TLS
                'ciphers' =>             'TLS_AES_128_GCM_SHA256:'
                                         . 'TLS_AES_256_GCM_SHA384:'
                                         . 'TLS_CHACHA20_POLY1305_SHA256:'
                                         . 'ECDHE-ECDSA-AES128-GCM-SHA256:'
                                         . 'ECDHE-RSA-AES128-GCM-SHA256:'
                                         . 'ECDHE-ECDSA-AES256-GCM-SHA384:'
                                         . 'ECDHE-RSA-AES256-GCM-SHA384:'
                                         . 'ECDHE-ECDSA-CHACHA20-POLY1305:'
                                         . 'ECDHE-RSA-CHACHA20-POLY1305:'
                                         . 'DHE-RSA-AES128-GCM-SHA256:'
                                         . 'DHE-RSA-AES256-GCM-SHA384',
                'disable_compression' => true,
                'crypto_method'       => STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                                         | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
            ];
        }
        $context = stream_context_create();
        foreach ($contextOptions as $wrapper => $options) {
            foreach ($options as $name => $value) {
                if (!stream_context_set_option($context, $wrapper, $name, $value)) {
                    throw new HTTP_Request2_LogicException(
                        "Error setting '{$wrapper}' wrapper context option '{$name}'"
                    );
                }
            }
        }
        set_error_handler([$this, 'connectionWarningsHandler']);
        $this->socket = stream_socket_client(
            $address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context
        );
        restore_error_handler();
        // if we fail to bind to a specified local address (see request #19515),
        // connection still succeeds, albeit with a warning. Throw an Exception
        // with the warning text in this case as that connection is unlikely
        // to be what user wants and as Curl throws an error in similar case.
        if ($this->connectionWarnings) {
            if ($this->socket) {
                fclose($this->socket);
            }
            $error = $errstr ? $errstr : implode("\n", $this->connectionWarnings);
            throw new HTTP_Request2_ConnectionException(
                "Unable to connect to {$address}. Error: {$error}", 0, $errno
            );
        }
        // Run socket in non-blocking mode, to prevent possible problems with
        // HTTPS requests not timing out properly (see bug #21229)
        stream_set_blocking($this->socket, false);
    }

    /**
     * Destructor, disconnects socket
     */
    public function __destruct()
    {
        fclose($this->socket);
    }

    /**
     * Wrapper around fread(), handles global request timeout
     *
     * @param int $length Reads up to this number of bytes
     *
     * @return   string|false Data read from socket by fread()
     * @throws   HTTP_Request2_MessageException     In case of timeout
     */
    public function read($length)
    {
        // Looks like stream_select() may return true, but then fread() will return an empty string...
        // For some reason or other happens mostly with servers behind Cloudflare.
        // Let's do the fread() call in a loop until either an error/eof or non-empty string:
        do {
            $data     = false;
            $timeouts = $this->_getTimeoutsForStreamSelect();

            $r = [$this->socket];
            $w = [];
            $e = [];
            if (stream_select($r, $w, $e, $timeouts[0], $timeouts[1])) {
                $data = fread($this->socket, $length);
            }

            $this->checkTimeout();
        } while ('' === $data && !$this->eof());

        return $data;
    }

    /**
     * Reads until either the end of the socket or a newline, whichever comes first
     *
     * Strips the trailing newline from the returned data, handles global
     * request timeout. Method idea borrowed from Net_Socket PEAR package.
     *
     * @param int $bufferSize   buffer size to use for reading
     * @param int $localTimeout timeout value to use just for this call
     *                          (used when waiting for "100 Continue" response)
     *
     * @return   string Available data up to the newline (not including newline)
     * @throws   HTTP_Request2_MessageException     In case of timeout
     */
    public function readLine($bufferSize, $localTimeout = null)
    {
        $line = '';
        while (!feof($this->socket)) {
            if (null !== $localTimeout) {
                $timeouts = [$localTimeout, 0];
                $started  = microtime(true);
            } else {
                $timeouts = $this->_getTimeoutsForStreamSelect();
            }

            $r = [$this->socket];
            $w = [];
            $e = [];
            if (stream_select($r, $w, $e, $timeouts[0], $timeouts[1])) {
                $line .= @fgets($this->socket, $bufferSize);
            }

            if (null === $localTimeout) {
                $this->checkTimeout();
            } elseif (microtime(true) - $started > $localTimeout) {
                throw new HTTP_Request2_MessageException(
                    "readLine() call timed out", HTTP_Request2_Exception::TIMEOUT
                );
            }
            if (substr($line, -1) == "\n") {
                return rtrim($line, "\r\n");
            }
        }
        return $line;
    }

    /**
     * Wrapper around fwrite(), handles global request timeout
     *
     * @param string $data String to be written
     *
     * @return int
     * @throws HTTP_Request2_MessageException
     */
    public function write($data)
    {
        $totalWritten = 0;
        while (strlen($data)) {
            $written  = 0;
            $timeouts = $this->_getTimeoutsForStreamSelect();

            $r = [];
            $w = [$this->socket];
            $e = [];
            if (stream_select($r, $w, $e, $timeouts[0], $timeouts[1])) {
                // Notice: fwrite(): send of #### bytes failed with errno=10035
                // A non-blocking socket operation could not be completed immediately.
                $written = @fwrite($this->socket, $data);
            }
            $this->checkTimeout();

            // http://www.php.net/manual/en/function.fwrite.php#96951
            if (0 === (int)$written) {
                throw new HTTP_Request2_MessageException('Error writing request');
            }
            $data = substr($data, $written);
            $totalWritten += $written;
        }
        return $totalWritten;
    }

    /**
     * Tests for end-of-file on a socket
     *
     * @return bool
     */
    public function eof()
    {
        return feof($this->socket);
    }

    /**
     * Sets request deadline
     *
     * If null is passed for $deadline then deadline will be calculated based
     * on default_socket_timeout PHP setting. This is done to keep BC with previous
     * versions that used blocking sockets.
     *
     * @param float|null $deadline Exception will be thrown if request continues
     *                             past this time
     * @param int $timeout         Original request timeout value, to use in
     *                             Exception message
     */
    public function setDeadline($deadline, $timeout)
    {
        if (null === $deadline && 0 < ($defaultTimeout = (int)ini_get('default_socket_timeout'))) {
            $deadline = microtime(true) + $defaultTimeout;
        }
        $this->deadline = $deadline;
        $this->timeout  = $timeout;
    }

    /**
     * Turns on encryption on a socket
     *
     * @throws HTTP_Request2_ConnectionException
     */
    public function enableCrypto()
    {
        $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                        | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

        try {
            stream_set_blocking($this->socket, true);
            if (!stream_socket_enable_crypto($this->socket, true, $cryptoMethod)) {
                throw new HTTP_Request2_ConnectionException(
                    'Failed to enable secure connection when connecting through proxy'
                );
            }
        } finally {
            stream_set_blocking($this->socket, false);
        }
    }

    /**
     * Throws an Exception if stream timed out
     *
     * @throws HTTP_Request2_MessageException
     */
    protected function checkTimeout()
    {
        $info = stream_get_meta_data($this->socket);
        if ($info['timed_out'] || $this->deadline && microtime(true) > $this->deadline) {
            $reason = $this->timeout
                ? "after {$this->timeout} second(s)"
                : 'due to default_socket_timeout php.ini setting';
            throw new HTTP_Request2_MessageException(
                "Request timed out {$reason}", HTTP_Request2_Exception::TIMEOUT
            );
        }
    }

    /**
     * Returns timeouts based on deadline for use with stream_select()
     *
     * @return array First element is $tv_sec parameter for stream_select(),
     *               second element is $tv_usec
     */
    private function _getTimeoutsForStreamSelect()
    {
        if (!$this->deadline) {
            return [null, null];
        }
        $parts = array_map(
            'intval',
            explode('.', sprintf('%.6F', $this->deadline - microtime(true)))
        );
        if (0 > $parts[0] || 0 === $parts[0] && $parts[1] < 50000) {
            return [0, 50000];
        }
        return $parts;
    }

    /**
     * Error handler to use during stream_socket_client() call
     *
     * One stream_socket_client() call may produce *multiple* PHP warnings
     * (especially OpenSSL-related), we keep them in an array to later use for
     * the message of HTTP_Request2_ConnectionException
     *
     * @param int    $errno  error level
     * @param string $errstr error message
     *
     * @return bool
     */
    protected function connectionWarningsHandler($errno, $errstr)
    {
        if ($errno & E_WARNING) {
            array_unshift($this->connectionWarnings, $errstr);
        }
        return true;
    }
}
?>
