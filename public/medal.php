<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
$canManageInventory = get_user_class() >= UC_ADMINISTRATOR;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_add_inventory') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$canManageInventory) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '您没有管理勋章库存的权限'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $amount = (int)($_POST['amount'] ?? 0);
    $medalIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['medal_ids'] ?? [])))));
    if ($amount < 1 || $amount > 1000000) {
        echo json_encode(['success' => false, 'message' => '单次增加数量必须在1至1,000,000之间'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!$medalIds || count($medalIds) > 100) {
        echo json_encode(['success' => false, 'message' => '请选择1至100枚有限库存勋章'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $affected = \App\Models\Medal::query()
        ->where('display_on_medal_page', 1)
        ->whereNotNull('inventory')
        ->whereIn('id', $medalIds)
        ->increment('inventory', $amount);

    if ($affected < 1) {
        echo json_encode(['success' => false, 'message' => '所选勋章均为无限库存或已不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    do_log(sprintf(
        'Bulk medal inventory increment by user %s(%d): medal_ids=%s, amount=%d, affected=%d',
        $CURUSER['username'],
        $CURUSER['id'],
        implode(',', $medalIds),
        $amount,
        $affected
    ));
    echo json_encode([
        'success' => true,
        'message' => "已为 {$affected} 枚勋章分别增加 " . number_format($amount) . ' 个库存',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = \App\Models\Medal::query()->where('display_on_medal_page', 1)
    ->orderBy('priority', 'desc')->orderBy("id", 'desc');
$q = trim((string)($_REQUEST['q'] ?? ''));
if (!empty($q)) {
    $query->where('name', 'like', "%{$q}%");
}
$total = (clone $query)->count();
$perPage = 20;
list($paginationTop, $paginationBottom, $limit, $offset) = pager($perPage, $total, "?q=" . urlencode($q) . "&");
$rows = (clone $query)->offset($offset)->take($perPage)->orderBy('id', 'desc')->get();
$qEscaped = htmlspecialchars($q, ENT_QUOTES, 'UTF-8');
$title = nexus_trans('medal.label');
$columnNameLabel = nexus_trans('label.name');
$columnImageLargeLabel = nexus_trans('medal.fields.image_large');
$columnPriceLabel = nexus_trans('medal.fields.price');
$columnDurationLabel = nexus_trans('medal.fields.duration');
$columnDescriptionLabel = nexus_trans('medal.fields.description');
$columnBuyLabel = nexus_trans('medal.buy_btn');
$columnSaleBeginEndTimeLabel = nexus_trans('medal.fields.sale_begin_end_time');
$columnInventoryLabel = nexus_trans('medal.fields.inventory');
$columnBonusAdditionLabel = nexus_trans('medal.fields.bonus_addition');
$columnGiftLabel = nexus_trans('medal.gift_btn');
$columnGiftFeeLabel = nexus_trans('medal.fields.gift_fee');
$header = '<div class="nova-medal-heading"><div><h1 class="nova-medal-title">'.$title.'</h1><p>浏览并兑换 NovaHD 专属荣誉勋章</p></div><div class="nova-medal-balance"><span>当前魔力</span><strong>'.number_format((int)$CURUSER['seedbonus']).'</strong></div></div>';
$filterForm = <<<FORM
<div class="nova-medal-toolbar">
    <div class="nova-medal-count">共 <strong>{$total}</strong> 枚勋章</div>
    <form id="filterForm" action="medal.php" method="get">
        <input id="q" type="text" name="q" value="{$qEscaped}" placeholder="搜索勋章名称">
        <input type="submit" value="搜索">
        <a href="medal.php" class="nova-medal-reset">重置</a>
    </form>
</div>
FORM;
$inventoryTools = '';
if ($canManageInventory) {
    $inventoryTools = <<<HTML
<div class="nova-medal-admin-tools">
    <div>
        <strong>批量库存管理</strong>
        <span>仅处理当前页中选定的有限库存勋章</span>
    </div>
    <label class="nova-medal-select-all"><input type="checkbox" id="medal-select-all"> 全选当前页</label>
    <input type="number" id="medal-inventory-amount" min="1" max="1000000" step="1" placeholder="增加数量">
    <button type="button" id="medal-inventory-submit">批量增加库存</button>
</div>
HTML;
}
stdhead($title);
begin_main_frame();
$style = <<<'STYLE'
<style>
.nova-medal-page {
    width: 94%;
    max-width: 1455px;
    margin: 0 auto 30px;
    color: #1d3841;
}

.nova-medal-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    min-height: 96px;
    margin: 14px 0 12px;
    padding: 20px 24px;
    border: 1px solid rgba(255,255,255,.58);
    border-radius: 8px;
    background: linear-gradient(112deg, #102f3a 0%, #11555a 58%, #0f8276 100%);
    box-shadow: 0 12px 28px rgba(28,73,81,.14);
    box-sizing: border-box;
}

.nova-medal-title {
    margin: 0 0 6px !important;
    color: #fff !important;
    font-size: 25px !important;
    line-height: 1.3 !important;
    text-align: left !important;
}

.nova-medal-title::after {
    display: none !important;
    content: none !important;
}

.nova-medal-heading p {
    margin: 0;
    color: rgba(255,255,255,.76);
    font-size: 13px;
}

.nova-medal-balance {
    min-width: 150px;
    padding-left: 22px;
    border-left: 1px solid rgba(255,255,255,.18);
    text-align: right;
}

.nova-medal-balance span,
.nova-medal-balance strong {
    display: block;
}

.nova-medal-balance span {
    margin-bottom: 3px;
    color: rgba(255,255,255,.7);
    font-size: 11px;
}

.nova-medal-balance strong {
    color: #fff4b8;
    font-size: 22px;
    line-height: 1.25;
}

.nova-medal-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 14px;
    padding: 11px 14px;
    border: 1px solid #d5e5e7;
    border-radius: 8px;
    background: rgba(255,255,255,.9);
    box-shadow: 0 6px 18px rgba(39,73,81,.06);
}

.nova-medal-count {
    color: #71878e;
    font-size: 12px;
    white-space: nowrap;
}

.nova-medal-count strong {
    color: #0b8879;
}

.nova-medal-toolbar form {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.nova-medal-toolbar input[type="text"] {
    width: 240px;
    height: 36px !important;
    min-height: 36px !important;
    padding: 4px 10px !important;
    border-color: #c9dcde !important;
    border-radius: 6px !important;
    box-sizing: border-box;
}

.nova-medal-toolbar input[type="submit"],
.nova-medal-reset {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    min-width: 76px;
    height: 36px !important;
    min-height: 36px !important;
    margin: 0 !important;
    padding: 0 14px !important;
    border: 0 !important;
    border-radius: 6px !important;
    background: #0f9f8d !important;
    color: #fff !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    line-height: 36px !important;
    text-align: center !important;
    text-decoration: none !important;
    box-shadow: none !important;
    box-sizing: border-box;
}

.nova-medal-reset {
    border: 1px solid #bed8d6 !important;
    background: #fff !important;
    color: #14766e !important;
}

.nova-medal-admin-tools {
    display: grid;
    grid-template-columns: minmax(240px, 1fr) auto 150px auto;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
    padding: 13px 14px;
    border: 1px solid #c9e2dc;
    border-radius: 8px;
    background: linear-gradient(110deg, #f1faf7, #fffaf0);
    box-shadow: 0 6px 18px rgba(39,73,81,.06);
    box-sizing: border-box;
}

.nova-medal-admin-tools strong,
.nova-medal-admin-tools span {
    display: block;
}

.nova-medal-admin-tools strong {
    color: #176d65;
    font-size: 13px;
}

.nova-medal-admin-tools span {
    margin-top: 2px;
    color: #71878e;
    font-size: 11px;
}

.nova-medal-select-all {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #315761;
    font-size: 12px;
    white-space: nowrap;
    cursor: pointer;
}

.nova-medal-admin-tools input[type="number"] {
    width: 150px !important;
    height: 36px !important;
    min-height: 36px !important;
    margin: 0 !important;
    padding: 4px 10px !important;
    border: 1px solid #c9dcde !important;
    border-radius: 6px !important;
    box-sizing: border-box;
}

.nova-medal-admin-tools button {
    height: 36px !important;
    min-height: 36px !important;
    margin: 0 !important;
    padding: 0 16px !important;
    border: 0 !important;
    border-radius: 6px !important;
    background: #147f73 !important;
    color: #fff !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    cursor: pointer;
}

.nova-medal-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.nova-medal-card {
    position: relative;
    min-width: 0;
    padding: 18px;
    border: 1px solid #dce7e9;
    border-top: 3px solid #0f9f8d;
    border-radius: 8px;
    background: rgba(255,255,255,.93);
    box-shadow: 0 7px 20px rgba(35,70,78,.07);
    box-sizing: border-box;
}

.nova-medal-card:nth-child(4n + 2) { border-top-color: #397b96; }
.nova-medal-card:nth-child(4n + 3) { border-top-color: #d09a31; }
.nova-medal-card:nth-child(4n + 4) { border-top-color: #b45b6a; }

.nova-medal-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 13px;
}

.nova-medal-card-header h2 {
    margin: 0 0 3px !important;
    color: #213d46 !important;
    font-size: 17px !important;
    line-height: 1.4 !important;
}

.nova-medal-card-header h2::after {
    display: none !important;
    content: none !important;
}

.nova-medal-id {
    color: #8a9aa0;
    font-size: 10px;
}

.nova-medal-state {
    flex: 0 0 auto;
    padding: 4px 8px;
    border-radius: 999px;
    background: #edf5f6;
    color: #61777e;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.nova-medal-card.is-available .nova-medal-state {
    background: #e8f8f3;
    color: #087965;
}

.nova-medal-card.is-owned .nova-medal-state {
    background: #fff5d8;
    color: #9a6814;
}

.nova-medal-card.is-owned {
    border-color: #e0bd5e;
    border-top-color: #d89b20;
    background: linear-gradient(140deg, rgba(255,253,244,.98), rgba(255,247,218,.92));
    box-shadow: 0 9px 24px rgba(180,127,25,.16), inset 0 0 0 1px rgba(255,224,126,.28);
}

.nova-medal-card-status {
    display: flex;
    align-items: center;
    gap: 8px;
}

.nova-medal-inventory-pick {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 7px;
    border: 1px solid #c9dcde;
    border-radius: 5px;
    background: #fff;
    color: #41636c;
    font-size: 10px;
    white-space: nowrap;
    cursor: pointer;
}

.nova-medal-inventory-pick input {
    margin: 0;
}

.nova-medal-card-body {
    display: grid;
    grid-template-columns: 152px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
}

.nova-medal-image {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 152px;
    height: 168px;
    margin: 0;
    border: 1px solid #e3ebed;
    border-radius: 8px;
    background: #f7fafb;
    overflow: hidden;
    box-sizing: border-box;
}

.nova-medal-preview {
    cursor: zoom-in;
    outline: none;
}

.nova-medal-preview:focus-visible {
    border-color: #0f9f8d;
    box-shadow: 0 0 0 3px rgba(15,159,141,.18);
}

.nova-medal-image img {
    width: 138px;
    height: 154px;
    object-fit: contain;
    transition: transform .18s ease, filter .18s ease;
}

.nova-medal-card:hover .nova-medal-image img {
    transform: scale(1.035);
}

.nova-medal-card.is-unavailable .nova-medal-image img {
    filter: grayscale(.62);
    opacity: .72;
}

.nova-medal-card.is-owned .nova-medal-image {
    border-color: #e6c96d;
    background: linear-gradient(145deg, #fffef8, #fff5ce);
}

.nova-medal-card.is-owned .nova-medal-image img {
    opacity: 1;
    filter: drop-shadow(0 7px 10px rgba(143,95,9,.2)) saturate(1.16) brightness(1.06);
}

.nova-medal-owned-mark {
    position: absolute;
    right: 7px;
    bottom: 7px;
    padding: 4px 8px;
    border: 1px solid rgba(151,102,10,.18);
    border-radius: 5px;
    background: rgba(255,244,196,.94);
    color: #90600c;
    font-size: 10px;
    font-weight: 800;
    line-height: 1.2;
    pointer-events: none;
}

.nova-medal-preview-content {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 320px;
    min-height: 360px;
    padding: 22px;
    background: linear-gradient(145deg, #f6fbfa, #fffaf0);
    box-sizing: border-box;
}

.nova-medal-preview-content img {
    display: block;
    max-width: min(70vw, 520px);
    max-height: 70vh;
    object-fit: contain;
    filter: drop-shadow(0 14px 24px rgba(22,59,66,.22));
}

.nova-medal-preview-caption {
    margin-top: 12px;
    color: #21434b;
    font-size: 14px;
    font-weight: 800;
    text-align: center;
}

.nova-medal-content {
    min-width: 0;
}

.nova-medal-description {
    display: -webkit-box;
    min-height: 38px;
    margin: 0 0 10px;
    overflow: hidden;
    color: #667d84;
    font-size: 12px;
    line-height: 1.55;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.nova-medal-sale {
    margin-bottom: 9px;
    padding: 7px 9px;
    border-left: 3px solid #397b96;
    border-radius: 0 5px 5px 0;
    background: #f4f8fa;
    color: #5d747c;
    font-size: 11px;
    line-height: 1.45;
}

.nova-medal-meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
    margin: 0 0 10px;
}

.nova-medal-meta div {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-width: 0;
    padding: 7px 9px;
    border-radius: 5px;
    background: #f7f9fa;
}

.nova-medal-meta dt,
.nova-medal-meta dd {
    margin: 0;
    font-size: 11px;
    white-space: nowrap;
}

.nova-medal-meta dt { color: #768990; }
.nova-medal-meta dd { color: #264650; font-weight: 800; }
.nova-medal-meta dd.is-price { color: #0b8879; }
.nova-medal-meta dd.is-bonus { color: #256f9b; }

.nova-medal-actions {
    display: grid;
    grid-template-columns: minmax(0, .85fr) minmax(210px, 1.15fr);
    gap: 8px;
    align-items: start;
}

.nova-medal-buy-action input,
.nova-medal-gift-controls input[type="button"] {
    width: 100% !important;
    min-height: 36px !important;
    height: 36px !important;
    margin: 0 !important;
    padding: 0 10px !important;
    border: 0 !important;
    border-radius: 6px !important;
    background: #0f9f8d !important;
    color: #fff !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    line-height: 36px !important;
    text-align: center !important;
    text-indent: 0 !important;
    box-shadow: none !important;
    box-sizing: border-box;
}

.nova-medal-buy-action input:disabled,
.nova-medal-gift-controls input[type="button"]:disabled {
    background: #a8b3bb !important;
    color: #fff !important;
    cursor: not-allowed;
}

.nova-medal-gift-controls {
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr);
    gap: 6px;
}

.nova-medal-gift-controls input.uid {
    width: 72px !important;
    height: 36px !important;
    min-height: 36px !important;
    margin: 0 !important;
    padding: 4px 7px !important;
    border-color: #cbdcdf !important;
    border-radius: 6px !important;
    text-align: center;
    box-sizing: border-box;
}

.nova-medal-gift-fee {
    display: block;
    grid-column: 1 / -1;
    margin-top: 3px;
    color: #819198;
    font-size: 10px;
    text-align: right;
}

.nova-medal-empty {
    grid-column: 1 / -1;
    padding: 46px 20px;
    border: 1px dashed #cbdcdf;
    border-radius: 8px;
    background: rgba(255,255,255,.78);
    color: #7b8f95;
    text-align: center;
}

.nova-medal-pagination {
    margin-top: 16px;
    text-align: center;
}

@media (max-width: 1050px) {
    .nova-medal-grid { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 680px) {
    .nova-medal-page { width: calc(100% - 18px); }
    .nova-medal-heading { align-items: flex-start; flex-direction: column; padding: 20px; }
    .nova-medal-balance { width: 100%; padding: 10px 0 0; border-top: 1px solid rgba(255,255,255,.18); border-left: 0; text-align: left; }
    .nova-medal-toolbar { align-items: stretch; flex-direction: column; }
    .nova-medal-toolbar form { display: grid; grid-template-columns: 1fr 1fr; }
    .nova-medal-toolbar input[type="text"] { grid-column: 1 / -1; width: 100%; }
    .nova-medal-admin-tools { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
    .nova-medal-admin-tools > div { grid-column: 1 / -1; }
    .nova-medal-admin-tools input[type="number"] { width: 100% !important; }
    .nova-medal-admin-tools button { grid-column: 1 / -1; }
    .nova-medal-card-body { grid-template-columns: minmax(0, 1fr); }
    .nova-medal-image { width: 100%; height: 190px; }
    .nova-medal-image img { width: 156px; height: 174px; }
    .nova-medal-actions { grid-template-columns: minmax(0, 1fr); }
}
</style>
STYLE;
$now = now();
$user = \App\Models\User::query()->findOrFail($CURUSER['id']);
$cards = '<div class="nova-medal-grid">';
$userMedals = $user->valid_medals->keyBy('id');
foreach ($rows as $row) {
    $buyDisabled = $giftDisabled = ' disabled';
    $buyClass = $giftClass = '';
    try {
        $row->checkCanBeBuy();
        if ($userMedals->has($row->id)) {
            $buyBtnText = nexus_trans('medal.buy_already');
        } elseif ($CURUSER['seedbonus'] < $row->price) {
            $buyBtnText = nexus_trans('medal.require_more_bonus');
        } else {
            $buyBtnText = nexus_trans('medal.buy_btn');
            $buyDisabled = '';
            $buyClass = 'buy';
        }
        if ($CURUSER['seedbonus'] < $row->price * (1 + ($row->gift_fee_factor ?? 0))) {
            $giftBtnText = nexus_trans('medal.require_more_bonus');
        } else {
            $giftBtnText = nexus_trans('medal.gift_btn');
            $giftDisabled = '';
            $giftClass = 'gift';
        }
    } catch (\Exception $exception) {
        $buyBtnText = $giftBtnText = $exception->getMessage();
    }
    $buyAction = sprintf(
        '<input type="button" class="%s" data-id="%s" value="%s"%s>',
        $buyClass, $row->id, htmlspecialchars($buyBtnText, ENT_QUOTES, 'UTF-8'), $buyDisabled
    );
    $giftAction = sprintf(
        '<input type="number" class="uid" %s placeholder="UID"><input type="button" class="%s" data-id="%s" value="%s"%s><span class="nova-medal-gift-fee">%s: %s</span>',
         $giftDisabled, $giftClass, $row->id, htmlspecialchars($giftBtnText, ENT_QUOTES, 'UTF-8'), $giftDisabled, $columnGiftFeeLabel, (($row->gift_fee_factor ?? 0) * 100).'%'
    );
    $isOwned = $userMedals->has($row->id);
    $stateClass = $isOwned ? 'is-owned' : ($buyClass === 'buy' ? 'is-available' : 'is-unavailable');
    $stateText = $isOwned ? '已拥有' : ($buyClass === 'buy' ? '可购买' : '暂不可购买');
    $description = trim(strip_tags((string)$row->description));
    if ($description === '') {
        $description = 'NovaHD 专属荣誉勋章，可用于个人资料展示并记录站点贡献与纪念时刻。';
    }
    $saleBegin = $row->sale_begin_time ? $row->sale_begin_time->format('Y-m-d H:i') : nexus_trans('nexus.no_limit');
    $saleEnd = $row->sale_end_time ? $row->sale_end_time->format('Y-m-d H:i') : nexus_trans('nexus.no_limit');
    $saleText = (!$row->sale_begin_time && !$row->sale_end_time) ? '长期开放' : $saleBegin . ' 至 ' . $saleEnd;
    $bonusText = (($row->bonus_addition_factor ?? 0) * 100) . '%';
    $inventoryText = $row->inventory ?? nexus_trans('label.infinite');
    $medalNameEscaped = htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8');
    $medalImageEscaped = htmlspecialchars($row->image_large, ENT_QUOTES, 'UTF-8');
    $ownedMark = $isOwned ? '<span class="nova-medal-owned-mark">已点亮</span>' : '';
    $inventoryControl = '';
    if ($canManageInventory && $row->inventory !== null) {
        $inventoryControl = sprintf(
            '<label class="nova-medal-inventory-pick"><input type="checkbox" class="nova-medal-inventory-check" value="%d"> 选择</label>',
            (int)$row->id
        );
    }
    $cards .= sprintf(
        '<article class="nova-medal-card %s"><header class="nova-medal-card-header"><div><h2>%s</h2><span class="nova-medal-id">MEDAL #%s</span></div><div class="nova-medal-card-status">%s<span class="nova-medal-state">%s</span></div></header><div class="nova-medal-card-body"><figure class="nova-medal-image nova-medal-preview" role="button" tabindex="0" data-full="%s" data-name="%s" title="点击预览"><img src="%s" alt="%s" loading="lazy">%s</figure><div class="nova-medal-content"><p class="nova-medal-description">%s</p><div class="nova-medal-sale">销售时间：%s</div><dl class="nova-medal-meta"><div><dt>%s</dt><dd>%s</dd></div><div><dt>%s</dt><dd class="is-bonus">%s</dd></div><div><dt>%s</dt><dd class="is-price">%s</dd></div><div><dt>%s</dt><dd>%s</dd></div></dl><div class="nova-medal-actions"><div class="nova-medal-buy-action">%s</div><div class="nova-medal-gift-controls">%s</div></div></div></div></article>',
        $stateClass,
        $medalNameEscaped,
        (int)$row->id,
        $inventoryControl,
        $stateText,
        $medalImageEscaped,
        $medalNameEscaped,
        $medalImageEscaped,
        $medalNameEscaped,
        $ownedMark,
        htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($saleText, ENT_QUOTES, 'UTF-8'),
        $columnDurationLabel,
        htmlspecialchars($row->durationText, ENT_QUOTES, 'UTF-8'),
        $columnBonusAdditionLabel,
        htmlspecialchars($bonusText, ENT_QUOTES, 'UTF-8'),
        $columnPriceLabel,
        number_format($row->price),
        $columnInventoryLabel,
        htmlspecialchars((string)$inventoryText, ENT_QUOTES, 'UTF-8'),
        $buyAction,
        $giftAction
    );
}
$cards .= $rows->isEmpty() ? '<div class="nova-medal-empty">没有找到匹配的勋章</div>' : '';
$cards .= '</div>';
echo $style . '<div class="nova-medal-page">' . $header . $filterForm . $inventoryTools . $cards . '<div class="nova-medal-pagination">' . $paginationBottom . '</div></div>';
end_main_frame();
$confirmBuyMsg = nexus_trans('medal.confirm_to_buy');
$confirmGiftMsg = nexus_trans('medal.confirm_to_gift');
$js = <<<JS
jQuery('.nova-medal-preview').on('click keydown', function (e) {
    if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
        return
    }
    e.preventDefault()
    let trigger = jQuery(this)
    let content = jQuery('<div class="nova-medal-preview-content"></div>')
    let inner = jQuery('<div></div>')
    jQuery('<img>').attr({src: trigger.attr('data-full'), alt: trigger.attr('data-name')}).appendTo(inner)
    jQuery('<div class="nova-medal-preview-caption"></div>').text(trigger.attr('data-name')).appendTo(inner)
    content.append(inner)
    layer.open({
        type: 1,
        title: false,
        shadeClose: true,
        area: ['auto', 'auto'],
        content: content
    })
})

jQuery('#medal-select-all').on('change', function () {
    jQuery('.nova-medal-inventory-check').prop('checked', this.checked)
})

jQuery('.nova-medal-inventory-check').on('change', function () {
    let all = jQuery('.nova-medal-inventory-check')
    jQuery('#medal-select-all').prop('checked', all.length > 0 && all.filter(':checked').length === all.length)
})

jQuery('#medal-inventory-submit').on('click', function () {
    let button = jQuery(this)
    let medalIds = jQuery('.nova-medal-inventory-check:checked').map(function () {
        return jQuery(this).val()
    }).get()
    let amount = Number(jQuery('#medal-inventory-amount').val())
    if (!medalIds.length) {
        layer.alert('请先选择需要增加库存的勋章')
        return
    }
    if (!Number.isInteger(amount) || amount < 1 || amount > 1000000) {
        layer.alert('增加数量必须是1至1,000,000之间的整数')
        return
    }
    layer.confirm('确定为选中的 ' + medalIds.length + ' 枚勋章分别增加 ' + amount.toLocaleString() + ' 个库存吗？', function (index) {
        layer.close(index)
        button.prop('disabled', true).text('处理中...')
        jQuery.post('medal.php', {
            action: 'bulk_add_inventory',
            amount: amount,
            medal_ids: medalIds
        }, function (response) {
            if (!response.success) {
                layer.alert(response.message || '库存增加失败')
                button.prop('disabled', false).text('批量增加库存')
                return
            }
            layer.alert(response.message, function () {
                window.location.reload()
            })
        }, 'json').fail(function (xhr) {
            let message = '库存增加失败，请稍后重试'
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message
            }
            layer.alert(message)
            button.prop('disabled', false).text('批量增加库存')
        })
    })
})

jQuery('.buy').on('click', function (e) {
    let medalId = jQuery(this).attr('data-id')
    layer.confirm("{$confirmBuyMsg}", function (index) {
        let params = {
            action: "buyMedal",
            params: {medal_id: medalId}
        }
        console.log(params)
        jQuery.post('ajax.php', params, function(response) {
            console.log(response)
            if (response.ret != 0) {
                layer.alert(response.msg)
                return
            }
            window.location.reload()
        }, 'json')
    })
})
jQuery('.gift').on('click', function (e) {
    let medalId = jQuery(this).attr('data-id')
    let uid = jQuery(this).prev().val()
    if (!uid) {
        layer.alert('Require UID')
        return
    }
    layer.confirm("{$confirmGiftMsg}" + uid + " ?", function (index) {
        let params = {
            action: "giftMedal",
            params: {medal_id: medalId, uid: uid}
        }
        console.log(params)
        jQuery.post('ajax.php', params, function(response) {
            console.log(response)
            if (response.ret != 0) {
                layer.alert(response.msg)
                return
            }
            window.location.reload()
        }, 'json')
    })
})
JS;
\Nexus\Nexus::js($js, 'footer', false);
stdfoot();
