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
 * Exception that represents error in the program logic
 *
 * This exception usually implies a programmer's error, like passing invalid
 * data to methods or trying to use PHP extensions that weren't installed or
 * enabled. Usually exceptions of this kind will be thrown before request even
 * starts.
 *
 * The exception will usually contain a package error code.
 *
 * @category HTTP
 * @package  HTTP_Request2
 * @author   Alexey Borzov <avb@php.net>
 * @license  http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @version  Release: 2.4.2
 * @link     http://pear.php.net/package/HTTP_Request2
 */
class HTTP_Request2_LogicException extends HTTP_Request2_Exception
{
}

?>