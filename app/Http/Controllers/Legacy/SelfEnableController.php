<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\BonusLogs;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBanLog;
use App\Repositories\BonusRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SelfEnableController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly UserRepository $users,
        private readonly BonusRepository $bonus,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (($user->parked ?? 'no') === 'yes') {
            abort(403, 'Your account is parked.');
        }

        $title = nexus_trans('self-enable.title');
        $unit = (int) Setting::getByName('bonus.self_enable', BonusLogs::DEFAULT_BONUS_SELF_ENABLE);

        if ($unit <= 0) {
            return $this->envelope($title, $this->notice($title, nexus_trans('self-enable.feature_disabled')));
        }

        if ($user->enabled === User::ENABLED_YES) {
            return $this->envelope($title, $this->notice($title, nexus_trans('self-enable.enable_status_normal')));
        }

        $latestBanLog = UserBanLog::query()
            ->where('uid', $user->id)
            ->orderBy('id', 'desc')
            ->first();
        if ($latestBanLog === null) {
            return $this->envelope($title, $this->notice($title, nexus_trans('self-enable.no_ban_info')));
        }

        $elapsedDay = (int) ceil((time() - $latestBanLog->created_at->getTimestamp()) / 86400);
        $total = $unit * $elapsedDay;
        $userBonus = (float) $user->seedbonus;
        $isUserBonusEnough = $userBonus >= $total;
        $userBonusNotEnoughTip = nexus_trans('self-enable.bonus_not_enough', ['bonus' => $userBonus]);

        if ($request->isMethod('POST') && $request->input('submit') !== null) {
            if (! $isUserBonusEnough) {
                return $this->envelope(
                    $title,
                    $this->renderBanInfo($title, $latestBanLog, $unit, $elapsedDay, $total, false, $userBonusNotEnoughTip, $userBonusNotEnoughTip),
                );
            }

            $operator = User::query()->find($user->id);
            $this->bonus->consumeUserBonus($user->id, $total, BonusLogs::BUSINESS_TYPE_SELF_ENABLE, $title);
            $this->users->enableUser($operator, $user->id, $title);

            return redirect('/index.php');
        }

        return $this->envelope(
            $title,
            $this->renderBanInfo($title, $latestBanLog, $unit, $elapsedDay, $total, $isUserBonusEnough, $userBonusNotEnoughTip, null),
        );
    }

    private function notice(string $title, string $msg): string
    {
        return '<h1>'.htmlspecialchars($title).'</h1>'."\n"
            .'<h3>'.htmlspecialchars($msg).'</h3>';
    }

    private function renderBanInfo(
        string $title,
        UserBanLog $banLog,
        int $unit,
        int $elapsedDay,
        int $total,
        bool $isBonusEnough,
        string $notEnoughTip,
        ?string $errorTip,
    ): string {
        $out = '<h1>'.htmlspecialchars($title).'</h1>'."\n";
        if ($errorTip !== null) {
            $out .= '<p class="striking">'.htmlspecialchars($errorTip).'</p>'."\n";
        }
        $out .= '<h3>'.htmlspecialchars(nexus_trans('self-enable.latest_ban_info')).'</h3>'."\n";
        $out .= '<table id="ban-info" border="1" cellpadding="5" cellspacing="0"><tbody>'."\n";
        $out .= '<tr><th>UID：</th><td>'.htmlspecialchars((string) $banLog->uid).'</td></tr>'."\n";
        $out .= '<tr><th>Username：</th><td>'.htmlspecialchars((string) $banLog->username).'</td></tr>'."\n";
        $out .= '<tr><th>Reason：</th><td>'.htmlspecialchars((string) $banLog->reason).'</td></tr>'."\n";
        $out .= '<tr><th>CreatedAt：</th><td>'.htmlspecialchars((string) $banLog->created_at).'</td></tr>'."\n";
        $out .= '</tbody></table>'."\n";
        $out .= '<p>'.htmlspecialchars(nexus_trans('self-enable.deduct_bonus_per_day', ['unit' => number_format($unit)])).'</p>'."\n";
        $out .= '<p>'.htmlspecialchars(nexus_trans('self-enable.deduct_bonus_total', ['days' => number_format($elapsedDay), 'total' => number_format($total)])).'</p>'."\n";

        if ($isBonusEnough) {
            $out .= '<p>'.htmlspecialchars(nexus_trans('self-enable.enable_desc')).'</p>'."\n";
            $out .= '<form method="post" action="self-enable.php">'
                .'<input type="hidden" name="submit" value="1">'
                .'<input type="submit" value="'.htmlspecialchars(nexus_trans('self-enable.enable_button')).'">'
                .'</form>';
        } else {
            $out .= '<p>'.htmlspecialchars($notEnoughTip).'</p>';
        }

        return $out;
    }

    private function envelope(string $title, string $body): Response
    {
        $titleEsc = htmlspecialchars($title);
        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
<style>#ban-info td {border: none}</style>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }
}
