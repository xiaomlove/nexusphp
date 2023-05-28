<?php
/* $Id: mysql_stats.php,v 1.0 2005/06/20 22:52:24 CoLdFuSiOn Exp $ */
// vim: expandtab sw=4 ts=4 sts=4:


require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
/**
 * Checks if the user is allowed to do what he tries to...
 */
if (get_user_class() < UC_SYSOP)
	stderr("Error", "Permission denied.");

$GLOBALS["byteUnits"] = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB');

$day_of_week = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
$month = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
// See http://www.php.net/manual/en/function.strftime.php to define the
// variable below
$datefmt = '%B %d, %Y at %I:%M %p';
$timespanfmt = '%s days, %s hours, %s minutes and %s seconds';
////////////////// FUNCTION LIST /////////////////////////
    /**
     * Formats $value to byte view
     *
     * @param    double   the value to format
     * @param    integer  the sensitiveness
     * @param    integer  the number of decimals to retain
     *
     * @return   array    the formatted value and its unit
     *
     * @access  public
     *
     * @author   staybyte
     * @version  1.0 - 20 July 2005
     */
    function formatByteDown($value, $limes = 6, $comma = 0)
    {
        $dh           = pow(10, $comma);
        $li           = pow(10, $limes);
        $return_value = $value;
        $unit         = $GLOBALS['byteUnits'][0];

        for ( $d = 6, $ex = 15; $d >= 1; $d--, $ex-=3 ) {
            if (isset($GLOBALS['byteUnits'][$d]) && $value >= $li * pow(10, $ex)) {
                $value = round($value / ( pow(1024, $d) / $dh) ) /$dh;
                $unit = $GLOBALS['byteUnits'][$d];
                break 1;
            } // end if
        } // end for

        if ($unit != $GLOBALS['byteUnits'][0]) {
            $return_value = number_format($value, $comma, '.', ',');
        } else {
            $return_value = number_format($value, 0, '.', ',');
        }

        return array($return_value, $unit);
    } // end of the 'formatByteDown' function

    /**
     * Returns a given timespan value in a readable format.
     *
     * @param  int     the timespan
     *
     * @return string  the formatted value
     */
    function timespanFormat($seconds)
    {
        $return_string = '';
        $days = floor($seconds / 86400);
        if ($days > 0) {
            $seconds -= $days * 86400;
        }
        $hours = floor($seconds / 3600);
        if ($days > 0 || $hours > 0) {
            $seconds -= $hours * 3600;
        }
        $minutes = floor($seconds / 60);
        if ($days > 0 || $hours > 0 || $minutes > 0) {
            $seconds -= $minutes * 60;
        }
        return (string)$days." Days ". (string)$hours." Hours ". (string)$minutes." Minutes ". (string)$seconds." Seconds ";
    }


   /**
     * Writes localised date
     *
     * @param   string   the current timestamp
     *
     * @return  string   the formatted date
     *
     * @access  public
     */
    function localisedDate($timestamp = -1, $format = '')
    {
        global $datefmt, $month, $day_of_week;

        if ($format == '') {
            $format = $datefmt;
        }

        if ($timestamp == -1) {
            $timestamp = time();
        }

        $date = preg_replace('@%[aA]@', $day_of_week[(int)strftime('%w', $timestamp)], $format);
        $date = preg_replace('@%[bB]@', $month[(int)strftime('%m', $timestamp)-1], $date);

        return strftime($date, $timestamp);
    } // end of the 'localisedDate()' function
    
////////////////////// END FUNCTION LIST /////////////////////////////////////


stdhead("Stats");

/**
 * Displays the sub-page heading
 */
echo '<h1 align=center>' . "\n"
   . '    Mysql Server Status'  . "\n"
   . '</h1>' . "\n";





/**
 * Sends the query and buffers the result
 */
$res = @sql_query('SHOW STATUS') or Die(mysql_error());
	while ($row = mysql_fetch_row($res)) {
		$serverStatus[$row[0]] = $row[1];
	}
@mysql_free_result($res);
unset($res);
unset($row);


/**
 * Displays the page
 */
//Uptime calculation
$res = @sql_query('SELECT UNIX_TIMESTAMP() - ' . $serverStatus['Uptime']);
$row = mysql_fetch_row($res);
//echo sprintf("Server Status Uptime", timespanFormat($serverStatus['Uptime']), localisedDate($row[0])) . "\n";
?>

	<table id="torrenttable" border="1"><tr><td>

<?php
print("This MySQL server has been running for ". timespanFormat($serverStatus['Uptime']) .". It started up on ". localisedDate($row[0])) . "\n";
?>

	</td></tr></table>

<?php
mysql_free_result($res);
unset($res);
unset($row);
//Get query statistics
$queryStats = array();
$tmp_array = $serverStatus;
	foreach($tmp_array AS $name => $value) {
		if (substr($name, 0, 4) == 'Com_') {
			$queryStats[str_replace('_', ' ', substr($name, 4))] = $value;
			unset($serverStatus[$name]);
		}
	}
unset($tmp_array);
?>

<ul>
    <li>
        <b>Server traffic:</b> These tables show the network traffic statistics of this MySQL server since its startup
        <br />
        <table border="0">
            <tr>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Traffic&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&nbsp;Per Hour&nbsp;</th>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Received&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo join(' ', formatByteDown($serverStatus['Bytes_received'])); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo join(' ', formatByteDown($serverStatus['Bytes_received'] * 3600 / $serverStatus['Uptime'])); ?>&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Sent&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo join(' ', formatByteDown($serverStatus['Bytes_sent'])); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo join(' ', formatByteDown($serverStatus['Bytes_sent'] * 3600 / $serverStatus['Uptime'])); ?>&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="lightgrey">&nbsp;Total&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;<?php echo join(' ', formatByteDown($serverStatus['Bytes_received'] + $serverStatus['Bytes_sent'])); ?>&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;<?php echo join(' ', formatByteDown(($serverStatus['Bytes_received'] + $serverStatus['Bytes_sent']) * 3600 / $serverStatus['Uptime'])); ?>&nbsp;</td>
                        </tr>
                    </table>
                </td>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Connections&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per Hour&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;%&nbsp;</th>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Failed Attempts&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format($serverStatus['Aborted_connects'], 0, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(($serverStatus['Aborted_connects'] * 3600 / $serverStatus['Uptime']), 2, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo ($serverStatus['Connections'] > 0 ) ? number_format(($serverStatus['Aborted_connects'] * 100 / $serverStatus['Connections']), 2, '.', ',') . '&nbsp;%' : '---'; ?>&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Aborted Clients&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format($serverStatus['Aborted_clients'], 0, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(($serverStatus['Aborted_clients'] * 3600 / $serverStatus['Uptime']), 2, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo ($serverStatus['Connections'] > 0 ) ? number_format(($serverStatus['Aborted_clients'] * 100 / $serverStatus['Connections']), 2 , '.', ',') . '&nbsp;%' : '---'; ?>&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="lightgrey">&nbsp;Total&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;<?php echo number_format($serverStatus['Connections'], 0, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;<?php echo number_format(($serverStatus['Connections'] * 3600 / $serverStatus['Uptime']), 2, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;<?php echo number_format(100, 2, '.', ','); ?>&nbsp;%&nbsp;</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </li>
    <br />
    <li>
        <?php print("<b>Query Statistics:</b> Since it's start up, ". number_format($serverStatus['Questions'], 0, '.', ',')." queries have been sent to the server.\n"); ?>
        <table border="0">
            <tr>
                <td colspan="2">
                    <br />
                    <table id="torrenttable" border="0" align="right">
                        <tr>
                            <th bgcolor="lightgrey">&nbsp;Total&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Minute&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Second&nbsp;</th>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format($serverStatus['Questions'], 0, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(($serverStatus['Questions'] * 3600 / $serverStatus['Uptime']), 2, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(($serverStatus['Questions'] * 60 / $serverStatus['Uptime']), 2, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(($serverStatus['Questions'] / $serverStatus['Uptime']), 2, '.', ','); ?>&nbsp;</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Query&nbsp;Type&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;%&nbsp;</th>
                        </tr>
<?php

$useBgcolorOne = TRUE;
$countRows = 0;
foreach ($queryStats as $name => $value) {

// For the percentage column, use Questions - Connections, because
// the number of connections is not an item of the Query types
// but is included in Questions. Then the total of the percentages is 100.
?>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;<?php echo htmlspecialchars($name); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format($value, 0, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(($value * 3600 / $serverStatus['Uptime']), 2, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(($value * 100 / ($serverStatus['Questions'] - $serverStatus['Connections'])), 2, '.', ','); ?>&nbsp;%&nbsp;</td>
                        </tr>
<?php
    $useBgcolorOne = !$useBgcolorOne;
    if (++$countRows == ceil(count($queryStats) / 2)) {
        $useBgcolorOne = TRUE;
?>
                    </table>
                </td>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Query&nbsp;Type&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;%&nbsp;</th>
                        </tr>
<?php
    }
}
unset($countRows);
unset($useBgcolorOne);
?>
                    </table>
                </td>
            </tr>
        </table>
    </li>
<?php
//Unset used variables
unset($serverStatus['Aborted_clients']);
unset($serverStatus['Aborted_connects']);
unset($serverStatus['Bytes_received']);
unset($serverStatus['Bytes_sent']);
unset($serverStatus['Connections']);
unset($serverStatus['Questions']);
unset($serverStatus['Uptime']);

if (!empty($serverStatus)) {
?>
    <br />
    <li>
        <b>More status variables</b><br />
        <table border="0">
            <tr>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th bgcolor="lightgrey">&nbsp;Variable&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;Value&nbsp;</th>
                        </tr>
<?php
    $useBgcolorOne = TRUE;
    $countRows = 0;
    foreach($serverStatus AS $name => $value) {
?>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;<?php echo htmlspecialchars(str_replace('_', ' ', $name)); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo htmlspecialchars($value); ?>&nbsp;</td>
                        </tr>
<?php
        $useBgcolorOne = !$useBgcolorOne;
        if (++$countRows == ceil(count($serverStatus) / 3) || $countRows == ceil(count($serverStatus) * 2 / 3)) {
            $useBgcolorOne = TRUE;
?>
                    </table>
                </td>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th bgcolor="lightgrey">&nbsp;Variable&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;Value&nbsp;</th>
                        </tr>
<?php
        }
    }
    unset($useBgcolorOne);
?>
                    </table>
                </td>
            </tr>
        </table>
    </li>
<?php
}

?>
</ul>


<?php
stdfoot();
