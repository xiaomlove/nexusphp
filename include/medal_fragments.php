<?php
/* NovaHD medal fragments: isolated from the existing medal inventory. */
function medal_fragment_ensure_tables(): void
{
    sql_query("CREATE TABLE IF NOT EXISTS medal_fragment_config (
        medal_id INT UNSIGNED NOT NULL PRIMARY KEY,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or sqlerr(__FILE__, __LINE__);
    sql_query("CREATE TABLE IF NOT EXISTS medal_fragments (
        uid INT UNSIGNED NOT NULL,
        medal_id INT UNSIGNED NOT NULL,
        amount INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (uid, medal_id),
        KEY medal_id (medal_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or sqlerr(__FILE__, __LINE__);
    sql_query("CREATE TABLE IF NOT EXISTS medal_fragment_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        uid INT UNSIGNED NOT NULL,
        medal_id INT UNSIGNED NOT NULL,
        source VARCHAR(32) NOT NULL,
        amount INT NOT NULL,
        detail VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        KEY uid_created (uid, created_at), KEY source_created (source, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or sqlerr(__FILE__, __LINE__);
    sql_query("CREATE TABLE IF NOT EXISTS medal_fragment_trades (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        seller_uid INT UNSIGNED NOT NULL,
        buyer_uid INT UNSIGNED DEFAULT NULL,
        offer_medal_id INT UNSIGNED NOT NULL,
        offer_amount INT UNSIGNED NOT NULL DEFAULT 1,
        want_medal_id INT UNSIGNED NOT NULL,
        want_amount INT UNSIGNED NOT NULL DEFAULT 1,
        status ENUM('open','done','cancelled') NOT NULL DEFAULT 'open',
        created_at DATETIME NOT NULL,
        completed_at DATETIME DEFAULT NULL,
        KEY status_created (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or sqlerr(__FILE__, __LINE__);
    sql_query("CREATE TABLE IF NOT EXISTS medal_fragment_runs (
        run_key VARCHAR(64) NOT NULL PRIMARY KEY,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or sqlerr(__FILE__, __LINE__);
    sql_query("CREATE TABLE IF NOT EXISTS medal_fragment_parts (
        uid INT UNSIGNED NOT NULL,
        medal_id INT UNSIGNED NOT NULL,
        part_no TINYINT UNSIGNED NOT NULL,
        amount INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (uid, medal_id, part_no), KEY medal_uid (medal_id, uid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or sqlerr(__FILE__, __LINE__);
    sql_query("CREATE TABLE IF NOT EXISTS medal_fragment_trades_v2 (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        seller_uid INT UNSIGNED NOT NULL, buyer_uid INT UNSIGNED DEFAULT NULL,
        offer_medal_id INT UNSIGNED NOT NULL, offer_part_no TINYINT UNSIGNED NOT NULL, offer_amount INT UNSIGNED NOT NULL,
        want_medal_id INT UNSIGNED NOT NULL, want_part_no TINYINT UNSIGNED NOT NULL, want_amount INT UNSIGNED NOT NULL,
        status ENUM('open','done','cancelled') NOT NULL DEFAULT 'open', created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL,
        KEY status_created(status,created_at), KEY seller_status(seller_uid,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") or sqlerr(__FILE__, __LINE__);
}

function medal_fragment_is_manager(): bool
{
    return get_user_class() >= UC_STAFFLEADER;
}

function medal_fragment_daily_count(int $uid): int
{
    $row = mysql_fetch_assoc(sql_query("SELECT COALESCE(SUM(amount), 0) AS total FROM medal_fragment_logs WHERE uid={$uid} AND created_at >= CURDATE()"));
    return (int)($row['total'] ?? 0);
}

function medal_fragment_add(int $uid, int $medalId, string $source, string $detail = ''): bool
{
    medal_fragment_ensure_tables();
    if ($uid < 1 || $medalId < 1) return false;
    $cfg = mysql_fetch_assoc(sql_query("SELECT enabled FROM medal_fragment_config WHERE medal_id={$medalId} LIMIT 1"));
    if (!$cfg || !(int)$cfg['enabled']) return false;
    $now = date('Y-m-d H:i:s');
    $partNo = random_int(1, 5);
    sql_query("INSERT INTO medal_fragment_parts(uid,medal_id,part_no,amount,created_at,updated_at) VALUES ({$uid},{$medalId},{$partNo},1,'{$now}','{$now}') ON DUPLICATE KEY UPDATE amount=amount+1,updated_at='{$now}'") or sqlerr(__FILE__, __LINE__);
    // medal_fragments stores legacy unassigned pieces; new pieces live in the
    // five-part table so repeated pieces retain their individual part.
    sql_query("INSERT INTO medal_fragment_logs(uid, medal_id, source, amount, detail, created_at) VALUES ({$uid}, {$medalId}, " . sqlesc($source) . ", 1, " . sqlesc($detail) . ", '{$now}')") or sqlerr(__FILE__, __LINE__);
    return true;
}

function medal_fragment_part_totals(int $uid, int $medalId): array
{
    $parts = array_fill(1, 5, 0);
    $res = sql_query("SELECT part_no,amount FROM medal_fragment_parts WHERE uid={$uid} AND medal_id={$medalId}");
    while ($res && ($row = mysql_fetch_assoc($res))) $parts[(int)$row['part_no']] = (int)$row['amount'];
    return $parts;
}

function medal_fragment_collection_parts(int $uid, int $medalId): array
{
    $parts = medal_fragment_part_totals($uid, $medalId);
    $legacyRow = mysql_fetch_assoc(sql_query("SELECT amount FROM medal_fragments WHERE uid={$uid} AND medal_id={$medalId} LIMIT 1"));
    $legacy = max(0, (int)($legacyRow['amount'] ?? 0));
    for ($partNo = 1; $partNo <= 5 && $legacy > 0; $partNo++) {
        if ($parts[$partNo] < 1) {
            $parts[$partNo] = 1;
            $legacy--;
        }
    }
    return $parts;
}

function medal_fragment_complete_part_count(int $uid, int $medalId): int
{
    return count(array_filter(medal_fragment_collection_parts($uid, $medalId), static fn (int $amount): bool => $amount > 0));
}

function medal_fragment_user_owns(int $uid, int $medalId): bool
{
    $row = mysql_fetch_assoc(sql_query("SELECT 1 AS owned FROM user_medals WHERE uid={$uid} AND medal_id={$medalId} AND status=1 AND (expire_at IS NULL OR expire_at>=NOW()) LIMIT 1"));
    return !empty($row['owned']);
}

function medal_fragment_total(int $uid, int $medalId): int
{
    $parts = medal_fragment_part_totals($uid, $medalId);
    $legacy = mysql_fetch_assoc(sql_query("SELECT amount FROM medal_fragments WHERE uid={$uid} AND medal_id={$medalId} LIMIT 1"));
    return array_sum($parts) + (int)($legacy['amount'] ?? 0);
}

function medal_fragment_consume(int $uid, int $medalId, int $needed): bool
{
    if (medal_fragment_total($uid, $medalId) < $needed) return false;
    $remaining = $needed;
    $res = sql_query("SELECT part_no,amount FROM medal_fragment_parts WHERE uid={$uid} AND medal_id={$medalId} AND amount>0 ORDER BY part_no FOR UPDATE");
    while ($res && ($row = mysql_fetch_assoc($res)) && $remaining > 0) {
        $take = min($remaining, (int)$row['amount']);
        sql_query("UPDATE medal_fragment_parts SET amount=amount-{$take},updated_at=NOW() WHERE uid={$uid} AND medal_id={$medalId} AND part_no=".(int)$row['part_no']);
        $remaining -= $take;
    }
    if ($remaining > 0) sql_query("UPDATE medal_fragments SET amount=amount-{$remaining},updated_at=NOW() WHERE uid={$uid} AND medal_id={$medalId} AND amount>={$remaining}");
    return true;
}

function medal_fragment_consume_complete_set(int $uid, int $medalId): bool
{
    sql_query('START TRANSACTION');
    $parts = array_fill(1, 5, 0);
    $res = sql_query("SELECT part_no,amount FROM medal_fragment_parts WHERE uid={$uid} AND medal_id={$medalId} FOR UPDATE");
    while ($res && ($row = mysql_fetch_assoc($res))) $parts[(int)$row['part_no']] = (int)$row['amount'];
    $legacyRow = mysql_fetch_assoc(sql_query("SELECT amount FROM medal_fragments WHERE uid={$uid} AND medal_id={$medalId} LIMIT 1 FOR UPDATE"));
    $legacy = max(0, (int)($legacyRow['amount'] ?? 0));
    $legacyNeeded = 0;
    for ($partNo = 1; $partNo <= 5; $partNo++) {
        if ($parts[$partNo] > 0) continue;
        if ($legacy <= 0) {
            sql_query('ROLLBACK');
            return false;
        }
        $legacy--;
        $legacyNeeded++;
    }
    for ($partNo = 1; $partNo <= 5; $partNo++) {
        if ($parts[$partNo] <= 0) continue;
        sql_query("UPDATE medal_fragment_parts SET amount=amount-1,updated_at=NOW() WHERE uid={$uid} AND medal_id={$medalId} AND part_no={$partNo} AND amount>0");
        if (mysql_affected_rows() !== 1) {
            sql_query('ROLLBACK');
            return false;
        }
    }
    if ($legacyNeeded > 0) {
        sql_query("UPDATE medal_fragments SET amount=amount-{$legacyNeeded},updated_at=NOW() WHERE uid={$uid} AND medal_id={$medalId} AND amount>={$legacyNeeded}");
        if (mysql_affected_rows() !== 1) {
            sql_query('ROLLBACK');
            return false;
        }
    }
    sql_query('COMMIT');
    return true;
}

function medal_fragment_random_medal(): int
{
    $res = sql_query("SELECT c.medal_id FROM medal_fragment_config c INNER JOIN medals m ON m.id=c.medal_id WHERE c.enabled=1 AND m.display_on_medal_page=1 ORDER BY RAND() LIMIT 1");
    $row = $res ? mysql_fetch_assoc($res) : null;
    return (int)($row['medal_id'] ?? 0);
}

function medal_fragment_hourly_reward(): int
{
    medal_fragment_ensure_tables();
    $runKey = 'hourly:' . date('YmdH');
    sql_query("INSERT IGNORE INTO medal_fragment_runs(run_key,created_at) VALUES (" . sqlesc($runKey) . ",NOW())");
    if (mysql_affected_rows() !== 1) return 0;
    $limit = min(50, max(1, (int)floor(get_row_count('users', "WHERE status='confirmed' AND enabled='yes'") * .01)));
    if ($limit < 1) return 0;
    $res = sql_query("SELECT DISTINCT p.userid FROM peers p INNER JOIN users u ON u.id=p.userid WHERE p.userid>0 AND p.last_action >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) ORDER BY RAND() LIMIT {$limit}");
    $count = 0; $medalId = medal_fragment_random_medal();
    if (!$medalId) return 0;
    while ($row = mysql_fetch_assoc($res)) if (medal_fragment_add((int)$row['userid'], $medalId, 'hourly', '整点在线奖励')) $count++;
    return $count;
}

function medal_fragment_seed_reward(): int
{
    $medalId = medal_fragment_random_medal();
    if (!$medalId) return 0;
    $res = sql_query("SELECT DISTINCT p.userid FROM peers p INNER JOIN torrents t ON t.id=p.torrent INNER JOIN torrent_tags tt ON tt.torrent_id=t.id INNER JOIN tags g ON g.id=tt.tag_id WHERE p.seeder='yes' AND p.started <= DATE_SUB(NOW(), INTERVAL 48 HOUR) AND p.last_action >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND g.name LIKE '%官种%' ORDER BY RAND() LIMIT 200");
    $count = 0;
    while ($res && ($row = mysql_fetch_assoc($res))) {
        if (random_int(1, 100) <= 5 && medal_fragment_add((int)$row['userid'], $medalId, 'seed', '官种保种超过48小时')) $count++;
    }
    return $count;
}

function medal_fragment_attendance_reward(): int
{
    $runKey = 'attendance:' . date('Ymd');
    sql_query("INSERT IGNORE INTO medal_fragment_runs(run_key,created_at) VALUES (" . sqlesc($runKey) . ",NOW())");
    if (mysql_affected_rows() !== 1) return 0;
    $medalId = medal_fragment_random_medal();
    if (!$medalId) return 0;
    $res = sql_query("SELECT uid FROM attendance WHERE days >= 10 AND added >= CURDATE()");
    $count = 0;
    while ($res && ($row = mysql_fetch_assoc($res))) {
        if (random_int(1, 100) <= 2 && medal_fragment_add((int)$row['uid'], $medalId, 'attendance', '连续签到10天以上')) $count++;
    }
    return $count;
}
