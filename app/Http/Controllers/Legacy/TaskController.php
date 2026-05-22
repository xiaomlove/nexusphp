<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Replacement for `public/task.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2".
 *
 * Authed paginated listing of `exams` rows with `type=task` and
 * `status=enabled`. Each row renders a "Claim" button that POSTs
 * to `ajax.php?action=claimTask` (out of scope for this migration
 * — the XHR endpoint is unchanged). The button is `disabled` for
 * tasks the viewer has already claimed.
 *
 * URL preserved exactly so:
 *   - `include/functions.php:2255` (the user-header
 *     `<a href="task.php">` link rendered in legacy chrome),
 *   - `include/functions.php:2509` (the `msgalert(... "task.php" ...)`
 *     redirect target for active task notifications),
 *   - `app/Http/Controllers/Legacy/UncoController.php` (which
 *     references the URL in a doc-comment / message body),
 *   - `public/userdetails.php` (which links to the page),
 *
 * keep working without template/JS changes. `task.php` uses
 * Laravel translations (`nexus_trans('exam.*')`) so no langfile
 * needs to ship with this PR.
 */
class TaskController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            abort(401);
        }

        $query = Exam::query()
            ->where('type', Exam::TYPE_TASK)
            ->where('status', Exam::STATUS_ENABLED);

        $total = (clone $query)->count();
        $totalPages = (int) max(1, ceil($total / self::PER_PAGE));
        $page = max(1, (int) $request->query('page', 1));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PER_PAGE;

        $rows = (clone $query)
            ->offset($offset)
            ->take(self::PER_PAGE)
            ->orderBy('id', 'desc')
            ->withCount('onGoingUsers')
            ->get();

        // Pre-load the viewer's already-claimed tasks so the per-row
        // render can flip the "Claim" button to "Claimed already"
        // without an N+1 lookup.
        $userInfo = User::query()->findOrFail((int) $viewer->id, User::$commonFields);
        $userTasks = $userInfo->onGoingExamAndTasks()
            ->where('type', Exam::TYPE_TASK)
            ->orderBy('id', 'desc')
            ->get()
            ->keyBy('id');

        $title = (string) nexus_trans('exam.type_task');
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $body = $this->renderListing($rows, $userTasks, $title);
        $pager = $this->renderPager($total, $page, $totalPages);
        $js = $this->renderClaimJs();

        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}
{$pager}
<script>{$js}</script>
</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * @param  iterable<Exam>  $rows
     * @param  Collection<int|string,mixed>  $claimedById
     */
    private function renderListing(iterable $rows, $claimedById, string $title): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $headers = [
            'label.name', 'exam.index', 'label.begin', 'label.end',
            'label.exam.filter_formatted', 'exam.success_reward_bonus',
            'exam.fail_deduct_bonus', 'exam.claimed_user_count',
            'label.description', 'exam.action_claim_task',
        ];
        $thead = '';
        foreach ($headers as $key) {
            $thead .= '<td class="colhead">'
                .htmlspecialchars((string) nexus_trans($key), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                .'</td>';
        }

        $tbody = '';
        $claimLabel = (string) nexus_trans('exam.action_claim_task');
        $alreadyLabel = (string) nexus_trans('exam.claimed_already');
        $infiniteLabel = (string) nexus_trans('label.infinite');

        foreach ($rows as $row) {
            $tbody .= $this->renderRow($row, $claimedById, $claimLabel, $alreadyLabel, $infiniteLabel);
        }

        return '<h1 style="text-align:center">'.$titleEsc.'</h1>'
            .'<table id="task-table" border="1" cellspacing="0" cellpadding="5" width="100%">'
            .'<thead><tr>'.$thead.'</tr></thead>'
            .'<tbody>'.$tbody.'</tbody>'
            .'</table>';
    }

    /**
     * @param  Collection<int|string,mixed>  $claimedById
     */
    private function renderRow(
        Exam $row,
        $claimedById,
        string $claimLabel,
        string $alreadyLabel,
        string $infiniteLabel,
    ): string {
        $isClaimed = $claimedById->has($row->id);
        $btnClass = $isClaimed ? '' : 'claim';
        $btnText = $isClaimed ? $alreadyLabel : $claimLabel;
        $btnDisabled = $isClaimed ? ' disabled' : '';

        $claimAction = sprintf(
            '<input type="button" class="%s" data-id="%d" value="%s"%s>',
            htmlspecialchars($btnClass, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            (int) $row->id,
            htmlspecialchars($btnText, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $btnDisabled,
        );

        $maxUsers = (int) ($row->max_user_count ?? 0);
        $onGoing = (int) ($row->on_going_users_count ?? 0);
        $countCell = sprintf(
            '%d/%s',
            $onGoing,
            $maxUsers > 0 ? (string) $maxUsers : $infiniteLabel,
        );

        $columns = [
            '<td class="nowrap"><strong>'.htmlspecialchars((string) $row->name, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</strong></td>',
            '<td class="nowrap">'.htmlspecialchars((string) $row->indexFormatted, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</td>',
            '<td>'.htmlspecialchars((string) $row->getBeginForUser(), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</td>',
            '<td>'.htmlspecialchars((string) $row->getEndForUser(), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</td>',
            '<td>'.htmlspecialchars((string) $row->filterFormatted, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</td>',
            '<td>'.number_format((int) $row->success_reward_bonus).'</td>',
            '<td>'.number_format((int) $row->fail_deduct_bonus).'</td>',
            '<td>'.htmlspecialchars($countCell, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</td>',
            '<td>'.htmlspecialchars((string) $row->description, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</td>',
            '<td>'.$claimAction.'</td>',
        ];

        return '<tr>'.implode('', $columns).'</tr>';
    }

    private function renderPager(int $total, int $page, int $totalPages): string
    {
        if ($total <= self::PER_PAGE) {
            return '';
        }
        $links = '';
        if ($page > 1) {
            $links .= '<a href="/task.php?page='.($page - 1).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.$page.' / '.$totalPages.'</b>';
        if ($page < $totalPages) {
            $links .= ' <a href="/task.php?page='.($page + 1).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>';
    }

    /**
     * Inline jQuery handler that submits the claim through
     * `ajax.php?action=claimTask` — same XHR shape and response
     * envelope the legacy script used. The legacy script also
     * loaded `vendor/jquery-loading/jquery.loading.min.js` for the
     * spinner; we drop the spinner in the chrome-less envelope to
     * keep the controller dependency-free. Phase 5 will rebuild
     * the page as a Livewire component with first-class loading
     * states.
     */
    private function renderClaimJs(): string
    {
        $confirmMsg = (string) nexus_trans('exam.confirm_to_claim');
        $confirmEsc = htmlspecialchars($confirmMsg, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return <<<JS
if (typeof jQuery !== 'undefined') {
    jQuery('.claim').on('click', function () {
        if (!window.confirm("{$confirmEsc}")) return;
        var id = jQuery(this).attr('data-id');
        jQuery.post('ajax.php', {action: 'claimTask', params: {exam_id: id}}, function (response) {
            if (response.ret != 0) { alert(response.msg); return; }
            window.location.reload();
        }, 'json');
    });
}
JS;
    }
}
