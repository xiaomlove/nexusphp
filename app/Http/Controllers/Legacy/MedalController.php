<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Medal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/medal.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `require bittorrent.php` → `dbconn()` + `loggedinorreturn()`.
 *   2. Queries `Medal` model for `display_on_medal_page = 1`,
 *      paginated (20/page) with optional `?q=` name search.
 *   3. For each medal: checks buy/gift eligibility, renders table
 *      row with buy/gift buttons.
 *   4. JS at the bottom: jQuery `.buy` / `.gift` click handlers
 *      POST to `ajax.php` with `action=buyMedal` / `giftMedal`.
 *   5. Uses `stdhead()` / `stdfoot()` chrome.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - Authenticated → 200 with chrome-less HTML envelope.
 *   - Pagination via `?page=<n>` (20 per page).
 *   - Optional `?q=<name>` filter.
 *   - Buy/gift buttons + JS preserved so the existing `ajax.php`
 *     endpoint keeps receiving the XHR calls.
 */
class MedalController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        $query = Medal::query()
            ->where('display_on_medal_page', 1)
            ->orderByDesc('priority')
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        $total = (clone $query)->count();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PER_PAGE;

        $rows = (clone $query)->offset($offset)->limit(self::PER_PAGE)->get();

        $userModel = User::query()->findOrFail($user->id);
        $userMedals = $userModel->valid_medals->keyBy('id');
        $userBonus = (float) $user->seedbonus;

        $qEsc = htmlspecialchars($q);
        $title = nexus_trans('medal.label');

        $body = '<h1 style="text-align: center">'.htmlspecialchars($title).'</h1>'."\n";
        $body .= '<div><form id="filterForm" action="medal.php" method="get">'
            .'<input id="q" type="text" name="q" value="'.$qEsc.'" placeholder="name">'
            .'<input type="submit">'
            .'<input type="reset" onclick="document.getElementById(\'q\').value=\'\';document.getElementById(\'filterForm\').submit();">'
            .'</form></div>'."\n";

        $body .= '<table border="1" cellspacing="0" cellpadding="5" width="100%"><thead><tr>'
            .'<td class="colhead">ID</td>'
            .'<td class="colhead">'.htmlspecialchars(nexus_trans('medal.fields.image_large')).'</td>'
            .'<td class="colhead">'.htmlspecialchars(nexus_trans('medal.fields.description')).'</td>'
            .'<td class="colhead" style="width: 115px">'.htmlspecialchars(nexus_trans('medal.fields.sale_begin_end_time')).'</td>'
            .'<td class="colhead">'.htmlspecialchars(nexus_trans('medal.fields.duration')).'</td>'
            .'<td class="colhead">'.htmlspecialchars(nexus_trans('medal.fields.bonus_addition')).'</td>'
            .'<td class="colhead">'.htmlspecialchars(nexus_trans('medal.fields.price')).'</td>'
            .'<td class="colhead">'.htmlspecialchars(nexus_trans('medal.fields.inventory')).'</td>'
            .'<td class="colhead">'.htmlspecialchars(nexus_trans('medal.buy_btn')).'</td>'
            .'<td class="colhead">'.htmlspecialchars(nexus_trans('medal.gift_btn')).'</td>'
            .'</tr></thead><tbody>'."\n";

        foreach ($rows as $row) {
            $buyDisabled = $giftDisabled = ' disabled';
            $buyClass = $giftClass = '';

            try {
                $row->checkCanBeBuy();
                if ($userMedals->has($row->id)) {
                    $buyBtnText = nexus_trans('medal.buy_already');
                } elseif ($userBonus < $row->price) {
                    $buyBtnText = nexus_trans('medal.require_more_bonus');
                } else {
                    $buyBtnText = nexus_trans('medal.buy_btn');
                    $buyDisabled = '';
                    $buyClass = 'buy';
                }
                $giftCost = $row->price * (1 + ($row->gift_fee_factor ?? 0));
                if ($userBonus < $giftCost) {
                    $giftBtnText = nexus_trans('medal.require_more_bonus');
                } else {
                    $giftBtnText = nexus_trans('medal.gift_btn');
                    $giftDisabled = '';
                    $giftClass = 'gift';
                }
            } catch (\Exception $e) {
                $buyBtnText = $giftBtnText = $e->getMessage();
            }

            $giftFeeLabel = htmlspecialchars(nexus_trans('medal.fields.gift_fee'));
            $giftFeePercent = (($row->gift_fee_factor ?? 0) * 100).'%';

            $body .= '<tr>'
                .'<td>'.(int) $row->id.'</td>'
                .'<td><img src="'.htmlspecialchars((string) $row->image_large).'" style="max-width: 60px;max-height: 60px;" class="preview" /></td>'
                .'<td><h1>'.htmlspecialchars((string) $row->name).'</h1>'.($row->description ?? '').'</td>'
                .'<td>'.htmlspecialchars((string) ($row->sale_begin_time ?? nexus_trans('nexus.no_limit'))).' ~<br>'.htmlspecialchars((string) ($row->sale_end_time ?? nexus_trans('nexus.no_limit'))).'</td>'
                .'<td>'.htmlspecialchars((string) $row->durationText).'</td>'
                .'<td>'.(($row->bonus_addition_factor ?? 0) * 100).'%</td>'
                .'<td>'.number_format((float) $row->price).'</td>'
                .'<td>'.htmlspecialchars((string) ($row->inventory ?? nexus_trans('label.infinite'))).'</td>'
                .'<td><input type="button" class="'.$buyClass.'" data-id="'.$row->id.'" value="'.htmlspecialchars($buyBtnText).'"'.$buyDisabled.'></td>'
                .'<td><input type="number" class="uid"'.$giftDisabled.' style="width: 60px" placeholder="UID">'
                .'<input type="button" class="'.$giftClass.'" data-id="'.$row->id.'" value="'.htmlspecialchars($giftBtnText).'"'.$giftDisabled.'>'
                .'<span class="nowrap">'.$giftFeeLabel.': '.$giftFeePercent.'</span></td>'
                .'</tr>'."\n";
        }

        $body .= '</tbody></table>'."\n";
        $body .= $this->renderPager($total, $page, $totalPages, $qEsc);

        // JS for buy/gift
        $confirmBuyMsg = nexus_trans('medal.confirm_to_buy');
        $confirmGiftMsg = nexus_trans('medal.confirm_to_gift');
        $body .= <<<JS
<script>
jQuery('.buy').on('click', function (e) {
    let medalId = jQuery(this).attr('data-id')
    layer.confirm("{$confirmBuyMsg}", function (index) {
        jQuery.post('ajax.php', {action: "buyMedal", params: {medal_id: medalId}}, function(response) {
            if (response.ret != 0) { layer.alert(response.msg); return; }
            window.location.reload()
        }, 'json')
    })
})
jQuery('.gift').on('click', function (e) {
    let medalId = jQuery(this).attr('data-id')
    let uid = jQuery(this).prev().val()
    if (!uid) { layer.alert('Require UID'); return; }
    layer.confirm("{$confirmGiftMsg}" + uid + " ?", function (index) {
        jQuery.post('ajax.php', {action: "giftMedal", params: {medal_id: medalId, uid: uid}}, function(response) {
            if (response.ret != 0) { layer.alert(response.msg); return; }
            window.location.reload()
        }, 'json')
    })
})
</script>
JS;

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Medal</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    private function renderPager(int $total, int $page, int $totalPages, string $q): string
    {
        if ($total <= self::PER_PAGE) {
            return '';
        }
        $params = $q !== '' ? '&q='.$q : '';
        $links = '';
        if ($page > 1) {
            $links .= '<a href="medal.php?page='.($page - 1).$params.'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.$page.' / '.$totalPages.'</b>';
        if ($page < $totalPages) {
            $links .= ' <a href="medal.php?page='.($page + 1).$params.'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>'."\n";
    }
}
