<?php
require_once('../include/bittorrent.php');
require_once('../include/nova_sticky_promotion.php');
dbconn();
loggedinorreturn();
header('Content-Type: application/json; charset=utf-8');

$torrentId = intval($_GET['torrent_id'] ?? 0);
$campaign = $torrentId ? nova_sticky_active_campaign($torrentId) : null;
if (!$campaign) {
    echo json_encode(['active' => false], JSON_UNESCAPED_UNICODE);
    exit;
}
$isSystemOfficial = (int)$campaign['sponsor_id'] === 0;
$rankings = function_exists('nova_sticky_system_seed_rankings') && $isSystemOfficial
    ? nova_sticky_system_seed_rankings((int)$campaign['id'], nova_sticky_config()['ranking_limit'])
    : nova_sticky_rankings((int)$campaign['id'], nova_sticky_config()['ranking_limit']);
foreach ($rankings as &$ranking) {
    $anonymous = ($ranking['is_anonymous'] ?? 'no') === 'yes';
    $ranking['display_html'] = nova_sticky_user_display((int)$ranking['user_id'], $anonymous);
    if ($anonymous) {
        unset($ranking['user_id'], $ranking['username']);
    }
}
unset($ranking);
echo json_encode([
    'active' => true,
    'is_system_official' => $isSystemOfficial,
    'campaign' => [
        'id' => (int)$campaign['id'],
        'ends_at' => $campaign['ends_at'],
        'bid_bonus' => (float)$campaign['bid_bonus'],
        'reward_pool' => (float)$campaign['reward_pool'],
        'winner_count' => (int)$campaign['winner_count'],
    ],
    'rankings' => $rankings,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
