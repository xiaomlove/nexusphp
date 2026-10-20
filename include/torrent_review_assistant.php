<?php

function nova_torrent_review_assistant_enabled(): bool
{
    return \App\Models\Setting::get('torrent.review_assistant_enabled', 'yes') === 'yes';
}

function nova_torrent_review_assistant_can_configure(): bool
{
    return get_user_class() >= UC_ADMINISTRATOR;
}

function nova_torrent_review_assistant_extract_images(string $description): array
{
    $urls = [];
    if (preg_match_all('/\[img(?:=[^\]]*)?\](https?:\/\/[^\[]+)\[\/img\]/i', $description, $matches)) {
        $urls = array_merge($urls, $matches[1]);
    }
    if (preg_match_all('/<img[^>]+src=["\'](https?:\/\/[^"\']+)["\']/i', $description, $matches)) {
        $urls = array_merge($urls, $matches[1]);
    }
    $urls = array_map(static fn($url) => trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8')), $urls);
    $urls = array_values(array_unique(array_filter($urls, static fn($url) => filter_var($url, FILTER_VALIDATE_URL))));
    return array_slice($urls, 0, 40);
}

function nova_torrent_review_assistant_plain_description(string $description): string
{
    $description = preg_replace('/\[img(?:=[^\]]*)?\].*?\[\/img\]/is', ' ', $description);
    $description = preg_replace('/\[[^\]]+\]/', ' ', $description);
    $description = strip_tags($description);
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($description, ENT_QUOTES, 'UTF-8')));
}

function nova_torrent_review_assistant_is_video(string $category, array $taxonomy): bool
{
    $haystack = mb_strtolower($category . ' ' . implode(' ', $taxonomy), 'UTF-8');
    return preg_match('/电影|电视剧|剧集|综艺|纪录|动漫|动画|短剧|体育|movie|tv|series|show|document|anime|sport|video/u', $haystack) === 1;
}

function nova_render_torrent_review_assistant(array $row): void
{
    global $CURUSER;

    $enabled = nova_torrent_review_assistant_enabled();
    $canReview = user_can('torrent-approval');
    $canConfigure = nova_torrent_review_assistant_can_configure();
    if (!$enabled && !$canConfigure) {
        return;
    }

    $taxonomy = [
        'source' => trim((string)($row['source_name'] ?? '')),
        'medium' => trim((string)($row['medium_name'] ?? '')),
        'codec' => trim((string)($row['codec_name'] ?? '')),
        'audioCodec' => trim((string)($row['audiocodec_name'] ?? '')),
        'resolution' => trim((string)($row['standard_name'] ?? '')),
        'processing' => trim((string)($row['processing_name'] ?? '')),
        'team' => trim((string)($row['team_name'] ?? '')),
    ];
    $descriptionRaw = (string)($row['descr'] ?? '');
    $technicalInfo = trim((string)($row['technical_info'] ?? ''));
    $toggleToken = $canConfigure
        ? hash_hmac('sha256', 'nova-review-toggle|' . (int)$CURUSER['id'], (string)config('app.key'))
        : '';
    $actionToken = $canReview
        ? hash_hmac('sha256', 'nova-review-action|' . (int)$CURUSER['id'], (string)config('app.key'))
        : '';
    $payload = [
        'torrentId' => (int)$row['id'],
        'enabled' => $enabled,
        'canReview' => $canReview,
        'canConfigure' => $canConfigure,
        'toggleToken' => $toggleToken,
        'actionToken' => $actionToken,
        'title' => trim((string)($row['name'] ?? '')),
        'subtitle' => trim((string)($row['small_descr'] ?? '')),
        'category' => trim((string)($row['cat_name'] ?? '')),
        'taxonomy' => $taxonomy,
        'isVideo' => nova_torrent_review_assistant_is_video((string)($row['cat_name'] ?? ''), $taxonomy),
        'externalUrl' => trim((string)($row['url'] ?? '')),
        'descriptionText' => nova_torrent_review_assistant_plain_description($descriptionRaw),
        'descriptionContainsMediaInfo' => preg_match('/(?:general|video|audio|概览|视频|音频|disc info|disc size)/i', $descriptionRaw) === 1,
        'technicalInfoPresent' => $technicalInfo !== '',
        'technicalInfoHasBbcode' => preg_match('/\[(?:b|color|size|font|url|img)(?:=[^\]]*)?\]/i', $technicalInfo) === 1,
        'technicalInfoSample' => mb_substr($technicalInfo, 0, 50000, 'UTF-8'),
        'imageUrls' => nova_torrent_review_assistant_extract_images($descriptionRaw),
        'approvalAllow' => \App\Models\Torrent::APPROVAL_STATUS_ALLOW,
        'approvalDeny' => \App\Models\Torrent::APPROVAL_STATUS_DENY,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $stateClass = $enabled ? 'is-enabled' : 'is-disabled';
    $stateText = $enabled ? '已开启' : '已关闭';
    $toggleButton = '';
    if ($canConfigure) {
        $nextState = $enabled ? 'no' : 'yes';
        $buttonText = $enabled ? '关闭助手' : '开启助手';
        $toggleButton = '<button type="button" class="nova-review-btn is-secondary" id="nova-review-toggle" data-enabled="' . $nextState . '">' . $buttonText . '</button>';
    }

    echo '<link rel="stylesheet" href="styles/nova-torrent-review-assistant.css?v=2026081604">';
    echo '<section id="nova-review-assistant" class="nova-review-assistant ' . $stateClass . '">';
    echo '<header class="nova-review-header"><div><span class="nova-review-kicker">NOVAHD REVIEW</span><h2>审种助手</h2></div>';
    echo '<div class="nova-review-header-actions"><span class="nova-review-state">' . $stateText . '</span>' . $toggleButton . '</div></header>';
    if (!$enabled) {
        echo '<div class="nova-review-offline">审种助手当前处于关闭状态</div>';
    } else {
        echo '<div class="nova-review-body">';
        echo '<div class="nova-review-summary"><div><span>错误</span><strong id="nova-review-error-count">0</strong></div><div><span>警告</span><strong id="nova-review-warning-count">0</strong></div><div><span>图片</span><strong id="nova-review-image-count">检查中</strong></div><div class="nova-review-result"><span>结论</span><strong id="nova-review-result">检查中</strong></div></div>';
        echo '<div class="nova-review-columns"><div class="nova-review-list is-error"><h3>需要修正</h3><ul id="nova-review-errors"><li>正在检查</li></ul></div><div class="nova-review-list is-warning"><h3>建议复核</h3><ul id="nova-review-warnings"><li>正在检查</li></ul></div></div>';
        if ($canReview) {
            echo '<div class="nova-review-actions"><button type="button" class="nova-review-btn is-secondary" id="nova-review-rerun">重新检查</button><button type="button" class="nova-review-btn is-notify" id="nova-review-notify" disabled>发送整改通知</button><button type="button" class="nova-review-btn is-primary" id="nova-review-open">打开审核</button><button type="button" class="nova-review-btn is-pass" id="nova-review-pass" disabled>一键通过</button></div>';
        }
        echo '</div>';
    }
    echo '</section>';
    echo '<script>window.NovaTorrentReviewAssistant=' . $json . ';</script>';
    \Nexus\Nexus::js('js/nova-torrent-review-assistant.js?v=2026081603', 'footer', true);
}
