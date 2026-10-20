#!/usr/bin/env php
<?php
if (php_sapi_name() !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(1); }

// 抑制所有PHP警告和通知
error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING);
ini_set('display_errors', 0);

$pluginDir = __DIR__;

function findSiteRoot(string $startDir): ?string {
    $dir = $startDir;
    for ($i = 0; $i < 7; $i++) {
        if (file_exists($dir . '/vendor/autoload.php') && file_exists($dir . '/include/bittorrent.php')) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) { break; }
        $dir = $parent;
    }
    return null;
}

$siteRoot = findSiteRoot($pluginDir) ?: getenv('SITE_ROOT');
if (!$siteRoot || !is_dir($siteRoot)) {
    fwrite(STDERR, "Failed to locate site root. Set SITE_ROOT env or place this plugin under vendor/.\n");
    exit(2);
}

require_once $siteRoot . '/vendor/autoload.php';
require_once $siteRoot . '/include/bittorrent.php';

use NexusPlugin\BankSystem\BankScheduler;

$hasTable = function(string $table): bool {
    try {
        $rows = \Nexus\Database\NexusDB::select("SHOW TABLES LIKE '" . addslashes($table) . "'");
        return !empty($rows);
    } catch (\Throwable $e) { return false; }
};

$start = microtime(true);
try {
    $sched = new BankScheduler();
    $sched->handleDailyTasks();
    $elapsed = round((microtime(true) - $start) * 1000);
    echo "[BANK_SCHEDULER] OK in {$elapsed}ms\n";

    // 可选：输出执行摘要，便于观察效果
    $verbose = getenv('BANK_VERBOSE');
    if ($verbose === false || $verbose === '' || $verbose === '1' || strtolower((string)$verbose) === 'true') {
        $today = date('Y-m-d');

        $byType = [];
        $demandCandidates = 0;
        try {
            if ($hasTable('bank_interest_records')) {
                $rows = \Nexus\Database\NexusDB::select("SELECT type, COUNT(*) AS cnt, SUM(amount) AS sumamt FROM bank_interest_records WHERE calculation_date = '$today' GROUP BY type");
                foreach ($rows as $r) { $byType[$r['type']] = $r; }
            }
        } catch (\Throwable $e) {}

        // 候选活期账户：balance>0 且 今日尚未计息（用于排障）
        try {
            if ($hasTable('bank_demand_accounts')) {
                $c = \Nexus\Database\NexusDB::select("SELECT COUNT(*) AS c FROM bank_demand_accounts WHERE balance > 0 AND (last_interest_date IS NULL OR last_interest_date < '$today')");
                $demandCandidates = (int)($c[0]['c'] ?? 0);
            }
        } catch (\Throwable $e) {}

        $depCnt = 0; $depAmt = 0.0;
        try {
            if ($hasTable('bank_deposits')) {
                $dep = \Nexus\Database\NexusDB::select("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS sumamt FROM bank_deposits WHERE status='matured' AND DATE(matured_at) = '$today'");
                $depCnt = (int)($dep[0]['cnt'] ?? 0);
                $depAmt = (float)($dep[0]['sumamt'] ?? 0);
            }
        } catch (\Throwable $e) {}

        $ovdCnt = 0;
        try {
            if ($hasTable('bank_loans')) {
                $ovd = \Nexus\Database\NexusDB::select("SELECT COUNT(*) AS cnt FROM bank_loans WHERE status='overdue' AND DATE(updated_at) = '$today'");
                $ovdCnt = (int)($ovd[0]['cnt'] ?? 0);
            }
        } catch (\Throwable $e) {}

        $dedCnt = 0; $dedAmt = 0.0;
        try {
            if ($hasTable('bonus_comments')) {
                $ded = \Nexus\Database\NexusDB::select("SELECT COUNT(*) AS cnt, COALESCE(ABS(SUM(bonus)),0) AS sumamt FROM bonus_comments WHERE DATE(added_at) = '$today' AND comment LIKE '逾期贷款自动扣款：%'");
                $dedCnt = (int)($ded[0]['cnt'] ?? 0);
                $dedAmt = (float)($ded[0]['sumamt'] ?? 0);
            }
        } catch (\Throwable $e) {}

        $fmt = function($n){ return is_numeric($n) ? number_format((float)$n, 2) : '0.00'; };
        echo "[SUMMARY] interest(demand): ".(int)($byType['demand']['cnt'] ?? 0)." rec, ".$fmt($byType['demand']['sumamt'] ?? 0)."\n";
        if ($hasTable('bank_demand_accounts')) {
            echo "[SUMMARY] demand candidates today: {$demandCandidates}\n";
        }
        echo "[SUMMARY] interest(deposit): ".(int)($byType['deposit']['cnt'] ?? 0)." rec, ".$fmt($byType['deposit']['sumamt'] ?? 0)."\n";
        echo "[SUMMARY] interest(loan): ".(int)($byType['loan']['cnt'] ?? 0)." rec, ".$fmt($byType['loan']['sumamt'] ?? 0)."\n";
        echo "[SUMMARY] matured deposits: {$depCnt} rec, ".$fmt($depAmt)."\n";
        echo "[SUMMARY] loans marked overdue today: {$ovdCnt}\n";
        if ($hasTable('bonus_comments')) {
            echo "[SUMMARY] overdue auto-deduction: {$dedCnt} logs, ".$fmt($dedAmt)."\n";
        }
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[BANK_SCHEDULER] FAIL: " . $e->getMessage() . "\n");
    exit(1);
}


