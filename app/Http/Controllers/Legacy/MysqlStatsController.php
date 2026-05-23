<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/mysql_stats.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Sysop-only MySQL server status
 * page (class >= UC_SYSOP, default 15). Renders three sections:
 *
 *   1. Server uptime.
 *   2. Traffic and connection statistics from `SHOW STATUS`.
 *   3. Per-command query stats (all `Com_*` variables).
 *   4. Remaining status variables.
 *
 * No external inputs — purely a `SHOW STATUS` dump. The original
 * script had three file-local helper functions; they are inlined
 * as private methods here to keep the controller self-contained and
 * pass PHPStan at the configured level.
 *
 * URL (`/mysql_stats.php`) preserved exactly so the existing
 * `SysoppanelTableSeeder` menu entry keeps working.
 * The matching nginx exact-location entry lives in
 * `.docker/openresty/sites/app.conf.template`.
 */
class MysqlStatsController extends Controller
{
    /** @var array<int, string> */
    private const BYTE_UNITS = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        // UC_SYSOP = 15; user_can checks against the configured authority level.
        if ((int) $user->class < User::CLASS_SYSOP) {
            abort(403, 'Permission denied.');
        }

        return new Response($this->render());
    }

    // ─── Render ──────────────────────────────────────────────────────────

    private function render(): string
    {
        $statusRows = NexusDB::select('SHOW STATUS');
        $serverStatus = [];
        foreach ($statusRows as $row) {
            $values = array_values((array) $row);
            $serverStatus[$values[0]] = $values[1];
        }

        // Uptime line.
        $uptimeStr = $this->timespanFormat((int) ($serverStatus['Uptime'] ?? 0));
        $uptimeRows = NexusDB::select('SELECT UNIX_TIMESTAMP() - '.(int) ($serverStatus['Uptime'] ?? 0).' AS startedat');
        $startedAt = $uptimeRows ? (int) array_values((array) $uptimeRows[0])[0] : 0;
        $startedStr = $this->localisedDate($startedAt);

        // Separate Com_* query stats.
        $queryStats = [];
        foreach ($serverStatus as $name => $value) {
            if (str_starts_with($name, 'Com_')) {
                $queryStats[str_replace('_', ' ', substr($name, 4))] = $value;
                unset($serverStatus[$name]);
            }
        }

        $body = $this->renderUptimeSection($uptimeStr, $startedStr);
        $body .= $this->renderTrafficSection($serverStatus);
        $body .= $this->renderQuerySection($serverStatus, $queryStats);
        $body .= $this->renderRemainingSection($serverStatus);

        return <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>MySQL Stats</title>
</head>
<body>
<h1 align="center">MySQL Server Status</h1>
<ul>
{$body}
</ul>
</body></html>
HTML;
    }

    // ─── Section renderers ───────────────────────────────────────────────

    private function renderUptimeSection(string $uptime, string $startedAt): string
    {
        return '<table id="torrenttable" border="1"><tr><td>'
            .'This MySQL server has been running for '
            .htmlspecialchars($uptime)
            .'. It started up on '
            .htmlspecialchars($startedAt)
            .'.</td></tr></table>';
    }

    /**
     * @param  array<string,mixed>  $s
     */
    private function renderTrafficSection(array $s): string
    {
        $uptime = max(1, (int) ($s['Uptime'] ?? 1));

        $recv = (float) ($s['Bytes_received'] ?? 0);
        $sent = (float) ($s['Bytes_sent'] ?? 0);
        $total = $recv + $sent;
        $conns = max(1, (int) ($s['Connections'] ?? 1));
        $abConns = (int) ($s['Aborted_connects'] ?? 0);
        $abClients = (int) ($s['Aborted_clients'] ?? 0);

        $rcvFmt = implode(' ', $this->formatByteDown($recv));
        $rcvHFmt = implode(' ', $this->formatByteDown($recv * 3600 / $uptime));
        $sntFmt = implode(' ', $this->formatByteDown($sent));
        $sntHFmt = implode(' ', $this->formatByteDown($sent * 3600 / $uptime));
        $totFmt = implode(' ', $this->formatByteDown($total));
        $totHFmt = implode(' ', $this->formatByteDown($total * 3600 / $uptime));

        $abConnPct = number_format($abConns * 100 / $conns, 2, '.', ',').'&nbsp;%';
        $abClientPct = number_format($abClients * 100 / $conns, 2, '.', ',').'&nbsp;%';

        return <<<HTML
<li>
<b>Server traffic:</b> These tables show the network traffic statistics of this MySQL server since its startup
<br />
<table border="0"><tr>
<td valign="top">
<table id="torrenttable" border="0">
<tr><th colspan="2" bgcolor="lightgrey">&nbsp;Traffic&nbsp;</th><th bgcolor="lightgrey">&nbsp;&nbsp;Per Hour&nbsp;</th></tr>
<tr><td bgcolor="#EFF3FF">&nbsp;Received&nbsp;</td><td bgcolor="#EFF3FF" align="right">&nbsp;{$rcvFmt}&nbsp;</td><td bgcolor="#EFF3FF" align="right">&nbsp;{$rcvHFmt}&nbsp;</td></tr>
<tr><td bgcolor="#EFF3FF">&nbsp;Sent&nbsp;</td><td bgcolor="#EFF3FF" align="right">&nbsp;{$sntFmt}&nbsp;</td><td bgcolor="#EFF3FF" align="right">&nbsp;{$sntHFmt}&nbsp;</td></tr>
<tr><td bgcolor="lightgrey">&nbsp;Total&nbsp;</td><td bgcolor="lightgrey" align="right">&nbsp;{$totFmt}&nbsp;</td><td bgcolor="lightgrey" align="right">&nbsp;{$totHFmt}&nbsp;</td></tr>
</table>
</td>
<td valign="top">
<table id="torrenttable" border="0">
<tr><th colspan="2" bgcolor="lightgrey">&nbsp;Connections&nbsp;</th><th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per Hour&nbsp;</th><th bgcolor="lightgrey">&nbsp;%&nbsp;</th></tr>
<tr><td bgcolor="#EFF3FF">&nbsp;Failed Attempts&nbsp;</td><td bgcolor="#EFF3FF" align="right">&nbsp;{$abConns}&nbsp;</td><td bgcolor="#EFF3FF" align="right">&nbsp;{$this->fmtNum($abConns * 3600 / $uptime)}&nbsp;</td><td bgcolor="#EFF3FF" align="right">&nbsp;{$abConnPct}&nbsp;</td></tr>
<tr><td bgcolor="#EFF3FF">&nbsp;Aborted Clients&nbsp;</td><td bgcolor="#EFF3FF" align="right">&nbsp;{$abClients}&nbsp;</td><td bgcolor="#EFF3FF" align="right">&nbsp;{$this->fmtNum($abClients * 3600 / $uptime)}&nbsp;</td><td bgcolor="#EFF3FF" align="right">&nbsp;{$abClientPct}&nbsp;</td></tr>
<tr><td bgcolor="lightgrey">&nbsp;Total&nbsp;</td><td bgcolor="lightgrey" align="right">&nbsp;{$conns}&nbsp;</td><td bgcolor="lightgrey" align="right">&nbsp;{$this->fmtNum($conns * 3600 / $uptime)}&nbsp;</td><td bgcolor="lightgrey" align="right">&nbsp;100.00&nbsp;%&nbsp;</td></tr>
</table>
</td>
</tr></table>
</li>
HTML;
    }

    /**
     * @param  array<string,mixed>  $s
     * @param  array<string,mixed>  $queryStats
     */
    private function renderQuerySection(array $s, array $queryStats): string
    {
        $uptime = max(1, (int) ($s['Uptime'] ?? 1));
        $questions = (int) ($s['Questions'] ?? 0);
        $conns = max(1, (int) ($s['Connections'] ?? 1));
        $divisor = max(1, $questions - $conns);

        $total = number_format($questions, 0, '.', ',');
        $perHour = $this->fmtNum($questions * 3600 / $uptime);
        $perMin = $this->fmtNum($questions * 60 / $uptime);
        $perSec = $this->fmtNum($questions / $uptime);

        $rows = '';
        $half = (int) ceil(count($queryStats) / 2);
        $count = 0;
        $splitDone = false;
        foreach ($queryStats as $name => $value) {
            $pct = $divisor > 0 ? $this->fmtNum((float) $value * 100 / $divisor).'&nbsp;%' : '0.00&nbsp;%';
            $rows .= '<tr>'
                .'<td bgcolor="#EFF3FF">&nbsp;'.htmlspecialchars($name).'&nbsp;</td>'
                .'<td bgcolor="#EFF3FF" align="right">&nbsp;'.number_format((float) $value, 0, '.', ',').'&nbsp;</td>'
                .'<td bgcolor="#EFF3FF" align="right">&nbsp;'.$this->fmtNum((float) $value * 3600 / $uptime).'&nbsp;</td>'
                .'<td bgcolor="#EFF3FF" align="right">&nbsp;'.$pct.'&nbsp;</td>'
                .'</tr>';
            $count++;
            if (! $splitDone && $count === $half) {
                $splitDone = true;
                $rows .= '</table></td><td valign="top"><table id="torrenttable" border="0">'
                    .'<tr><th colspan="2" bgcolor="lightgrey">&nbsp;Query&nbsp;Type&nbsp;</th>'
                    .'<th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>'
                    .'<th bgcolor="lightgrey">&nbsp;%&nbsp;</th></tr>';
            }
        }

        return <<<HTML
<br />
<li>
<b>Query Statistics:</b> Since its start up, {$total} queries have been sent to the server.
<table border="0"><tr><td colspan="2"><br />
<table id="torrenttable" border="0" align="right">
<tr><th bgcolor="lightgrey">&nbsp;Total&nbsp;</th><th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th><th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Minute&nbsp;</th><th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Second&nbsp;</th></tr>
<tr><td bgcolor="#EFF3FF" align="right">&nbsp;{$total}&nbsp;</td><td bgcolor="#EFF3FF" align="right">&nbsp;{$perHour}&nbsp;</td><td bgcolor="#EFF3FF" align="right">&nbsp;{$perMin}&nbsp;</td><td bgcolor="#EFF3FF" align="right">&nbsp;{$perSec}&nbsp;</td></tr>
</table>
</td></tr>
<tr><td valign="top">
<table id="torrenttable" border="0">
<tr><th colspan="2" bgcolor="lightgrey">&nbsp;Query&nbsp;Type&nbsp;</th><th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th><th bgcolor="lightgrey">&nbsp;%&nbsp;</th></tr>
{$rows}
</table></td></tr></table>
</li>
HTML;
    }

    /**
     * @param  array<string,mixed>  $serverStatus
     */
    private function renderRemainingSection(array $serverStatus): string
    {
        // Remove already-consumed keys.
        foreach (['Aborted_clients', 'Aborted_connects', 'Bytes_received', 'Bytes_sent', 'Connections', 'Questions', 'Uptime'] as $k) {
            unset($serverStatus[$k]);
        }

        if (empty($serverStatus)) {
            return '';
        }

        $total = count($serverStatus);
        $third = (int) ceil($total / 3);
        $twoThirds = (int) ceil($total * 2 / 3);
        $rows = '';
        $count = 0;
        foreach ($serverStatus as $name => $value) {
            $rows .= '<tr>'
                .'<td bgcolor="#EFF3FF">&nbsp;'.htmlspecialchars(str_replace('_', ' ', $name)).'&nbsp;</td>'
                .'<td bgcolor="#EFF3FF" align="right">&nbsp;'.htmlspecialchars((string) $value).'&nbsp;</td>'
                .'</tr>';
            $count++;
            if ($count === $third || $count === $twoThirds) {
                $rows .= '</table></td><td valign="top"><table id="torrenttable" border="0">'
                    .'<tr><th bgcolor="lightgrey">&nbsp;Variable&nbsp;</th><th bgcolor="lightgrey">&nbsp;Value&nbsp;</th></tr>';
            }
        }

        return <<<HTML
<br />
<li>
<b>More status variables</b><br />
<table border="0"><tr><td valign="top">
<table id="torrenttable" border="0">
<tr><th bgcolor="lightgrey">&nbsp;Variable&nbsp;</th><th bgcolor="lightgrey">&nbsp;Value&nbsp;</th></tr>
{$rows}
</table></td></tr></table>
</li>
HTML;
    }

    // ─── Utility methods (ported from legacy file-local functions) ────────

    /**
     * Format a byte value to a human-readable magnitude + unit.
     *
     * @return array{0: string, 1: string}
     */
    private function formatByteDown(float $value, int $limes = 6, int $comma = 0): array
    {
        $units = self::BYTE_UNITS;
        $dh = 10 ** $comma;
        $li = 10 ** $limes;

        for ($d = 6, $ex = 15; $d >= 1; $d--, $ex -= 3) {
            if (isset($units[$d]) && $value >= $li * (10 ** $ex)) {
                $value = round($value / (4 ** $d * 64 ** ($d - 1) / $dh)) / $dh;

                return [number_format($value, $comma, '.', ','), $units[$d]];
            }
        }

        return [number_format($value, 0, '.', ','), $units[0]];
    }

    /** Format seconds as "X Days Y Hours Z Minutes W Seconds". */
    private function timespanFormat(int $seconds): string
    {
        $days = (int) floor($seconds / 86400);
        $seconds -= $days * 86400;
        $hours = (int) floor($seconds / 3600);
        $seconds -= $hours * 3600;
        $minutes = (int) floor($seconds / 60);
        $seconds -= $minutes * 60;

        return $days.' Days '.$hours.' Hours '.$minutes.' Minutes '.$seconds.' Seconds';
    }

    /** Format a Unix timestamp as a human-readable date string. */
    private function localisedDate(int $timestamp = -1): string
    {
        if ($timestamp <= 0) {
            $timestamp = time();
        }

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthIdx = (int) date('n', $timestamp) - 1;
        $month = $months[$monthIdx] ?? '';

        return date('F d, Y', $timestamp).' at '.date('g:i A', $timestamp);
    }

    /** Format a float with 2 decimal places, thousands separator. */
    private function fmtNum(float $n): string
    {
        return number_format($n, 2, '.', ',');
    }
}
