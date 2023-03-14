<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();

$action = $_POST['action'] ?? 'noAction';
$params = $_POST['params'] ?? [];

function noAction()
{
    throw new \RuntimeException("no Action");
}


try {
    $result = call_user_func($action, $params);
    exit(json_encode(success($result)));
} catch (\Throwable $exception) {
    exit(json_encode(fail($exception->getMessage(), $_POST)));
}

function toggleUserMedalStatus($params)
{
    global $CURUSER;
    $rep = new \App\Repositories\MedalRepository();
    return $rep->toggleUserMedalStatus($params['id'], $CURUSER['id']);
}


function attendanceRetroactive($params)
{
    global $CURUSER;
    $rep = new \App\Repositories\AttendanceRepository();
    return $rep->retroactive($CURUSER['id'], $params['timestamp']);
}

function getPtGen($params)
{
    $rep = new Nexus\PTGen\PTGen();
    $result = $rep->generate($params['url']);
    if ($rep->isRawPTGen($result)) {
        return $result;
    } elseif ($rep->isIyuu($result)) {
        return $result['data'];
    } else {
        return '';
    }
}

function addClaim($params)
{
    global $CURUSER;
    $rep = new \App\Repositories\ClaimRepository();
    return $rep->store($CURUSER['id'], $params['torrent_id']);
}

function removeClaim($params)
{
    global $CURUSER;
    $rep = new \App\Repositories\ClaimRepository();
    return $rep->delete($params['id'], $CURUSER['id']);
}

function removeUserLeechWarn($params)
{
    global $CURUSER;
    $rep = new \App\Repositories\UserRepository();
    return $rep->removeLeechWarn($CURUSER['id'], $params['uid']);
}

function getOffer($params)
{
    $offer = \App\Models\Offer::query()->findOrFail($params['id']);
    return $offer->toArray();
}

function approvalModal($params)
{
    global $CURUSER;
    $rep = new \App\Repositories\TorrentRepository();
    return $rep->buildApprovalModal($CURUSER['id'], $params['torrent_id']);
}

function approval($params)
{
    global $CURUSER;
    foreach (['torrent_id', 'approval_status',] as $field) {
        if (!isset($params[$field])) {
            throw new \InvalidArgumentException("Require $field");
        }
    }
    $rep = new \App\Repositories\TorrentRepository();
    return $rep->approval($CURUSER['id'], $params);
}

function addSeedBoxRecord($params)
{
    global $CURUSER;
    $rep = new \App\Repositories\SeedBoxRepository();
    $params['uid'] = $CURUSER['id'];
    $params['type'] = \App\Models\SeedBoxRecord::TYPE_USER;
    $params['status'] = \App\Models\SeedBoxRecord::STATUS_UNAUDITED;
    return $rep->store($params);
}

function removeSeedBoxRecord($params)
{
    global $CURUSER;
    $rep = new \App\Repositories\SeedBoxRepository();
    return $rep->delete($params['id'], $CURUSER['id']);
}

function removeHitAndRun($params)
{
    global $CURUSER;
    $rep = new \App\Repositories\BonusRepository();
    return $rep->consumeToCancelHitAndRun($CURUSER['id'], $params['id']);
}

function consumeBenefit($params)
{
    global $CURUSER;
    $rep = new \App\Repositories\UserRepository();
    return $rep->consumeBenefit($CURUSER['id'], $params);
}

function clearShoutBox($params)
{
    global $CURUSER;
    user_can('sbmanage', true);
    \Nexus\Database\NexusDB::table('shoutbox')->delete();
    return true;
}

function buyMedal($params)
{
    global $CURUSER;
    $rep = new \App\Repositories\BonusRepository();
    return $rep->consumeToBuyMedal($CURUSER['id'], $params['medal_id']);
}

function giftMedal($params)
{
    global $CURUSER;
    $rep = new \App\Repositories\BonusRepository();
    return $rep->consumeToGiftMedal($CURUSER['id'], $params['medal_id'], $params['uid']);
}

function saveUserMedal($params)
{
    global $CURUSER;
    $data = [];
    foreach ($params as $param) {
        $fieldAndId = explode('_', $param['name']);
        $field = $fieldAndId[0];
        $id = $fieldAndId[1];
        $value = $param['value'];
        $data[$id][$field] = $value;
    }
//    dd($params, $data);
    $rep = new \App\Repositories\MedalRepository();
    return $rep->saveUserMedal($CURUSER['id'], $data);
}

function claimAllSeeding()
{
    global $CURUSER;
    $rep = new \App\Repositories\ClaimRepository();
    return $rep->claimAllSeeding($CURUSER['id']);
}
