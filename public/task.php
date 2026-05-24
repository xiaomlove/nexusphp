<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
$query = \App\Models\Exam::query()
    ->where('type', \App\Models\Exam::TYPE_TASK)
    ->where("status", \App\Models\Exam::STATUS_ENABLED)
;
$total = (clone $query)->count();
$perPage = 20;
list($paginationTop, $paginationBottom, $limit, $offset) = pager($perPage, $total, "?");
$rows = (clone $query)->offset($offset)->take($perPage)->orderBy('id', 'desc')->withCount("onGoingUsers")->get();
$title = nexus_trans('exam.type_task');
$columnNameLabel = nexus_trans('label.name');
$columnIndexLabel = nexus_trans('exam.index');
$columnBeginTimeLabel = nexus_trans('label.begin');
$columnEndTimeLabel = nexus_trans('label.end');
$columnDurationLabel = nexus_trans('label.duration');
$columnRecurringLabel = nexus_trans('exam.recurring');
$columnTargetUserLabel = nexus_trans('label.exam.filter_formatted');
$columnDescLabel = nexus_trans('label.description');
$columnSuccessRewardLabel = nexus_trans('exam.success_reward_bonus');
$columnFailDeductLabel = nexus_trans('exam.fail_deduct_bonus');
$columnDescriptionDeductLabel = nexus_trans('label.description');
$columnClaimLabel = nexus_trans('exam.action_claim_task');
$columnClaimedUserCountLabel = nexus_trans('exam.claimed_user_count');

$header = '<h1 style="text-align: center">'.$title.'</h1>';
stdhead($title);
begin_main_frame();
$table = <<<TABLE
<table border="1" cellspacing="0" cellpadding="5" width="100%">
<thead>
<tr>
<td class="colhead">$columnNameLabel</td>
<td class="colhead">$columnIndexLabel</td>
<td class="colhead">$columnBeginTimeLabel</td>
<td class="colhead">$columnEndTimeLabel</td>
<td class="colhead">$columnTargetUserLabel</td>
<td class="colhead">$columnSuccessRewardLabel</td>
<td class="colhead">$columnFailDeductLabel</td>
<td class="colhead">$columnClaimedUserCountLabel</td>
<td class="colhead">$columnDescriptionDeductLabel</td>
<td class="colhead">$columnClaimLabel</td>
</tr>
</thead>
TABLE;
$now = now();
$table .= '<tbody>';
$userInfo = \App\Models\User::query()->findOrFail($CURUSER['id'], \App\Models\User::$commonFields);
$userTasks = $userInfo->onGoingExamAndTasks()->where("type", \App\Models\Exam::TYPE_TASK)
    ->orderBy('id', 'desc')
    ->get()
    ->keyBy('id')
;
//dd(last_query());
foreach ($rows as $row) {
    $claimDisabled = $claimClass = '';
    $claimBtnText = nexus_trans("exam.action_claim_task");
    if ($userTasks->has($row->id)) {
        $claimDisabled = " disabled";
        $claimBtnText = nexus_trans("exam.claimed_already");
    } else {
        $claimClass = "claim";
    }
    $claimAction = sprintf(
        '<input type="button" class="%s" data-id="%s" value="%s"%s>',
        $claimClass, $row->id, $claimBtnText, $claimDisabled
    );
    $columns = [];
    $columns[] = sprintf('<td class="nowrap"><strong>%s</strong></td>', $row->name);
    $columns[] = sprintf('<td class="nowrap">%s</td>', $row->indexFormatted);
    $columns[] = sprintf('<td>%s</td>', $row->getBeginForUser());
    $columns[] = sprintf('<td>%s</td>', $row->getEndForUser());
    $columns[] = sprintf('<td>%s</td>', $row->filterFormatted);
    $columns[] = sprintf('<td>%s</td>', number_format($row->success_reward_bonus));
    $columns[] = sprintf('<td>%s</td>', number_format($row->fail_deduct_bonus));
    $columns[] = sprintf('<td>%s</td>', sprintf("%s/%s",$row->on_going_users_count ?? 0, $row->max_user_count ?: nexus_trans("label.infinite")));
    $columns[] = sprintf('<td>%s</td>', $row->description);
    $columns[] = sprintf('<td>%s</td>', $claimAction);
    $table .= sprintf('<tr>%s</tr>', implode("", $columns));
}
$table .= '</tbody></table>';
echo $header . $table . $paginationBottom;
end_main_frame();
$confirmBuyMsg = nexus_trans('exam.confirm_to_claim');
$confirmGiftMsg = nexus_trans('medal.confirm_to_gift');
$js = <<<JS
jQuery('.claim').on('click', function (e) {
    let id = jQuery(this).attr('data-id')
    layer.confirm("{$confirmBuyMsg}", function (index) {
        layer.close(index)
        let params = {
            action: "claimTask",
            params: {exam_id: id}
        }
        console.log(params)
        jQuery('body').loading({
            stoppable: false
        });
        jQuery.post('ajax.php', params, function(response) {
            jQuery('body').loading('stop');
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
\Nexus\Nexus::js('vendor/jquery-loading/jquery.loading.min.js', 'footer', true);
\Nexus\Nexus::js($js, 'footer', false);
stdfoot();

