<?php
require "../include/bittorrent.php";
dbconn();

$action = $_POST['action'] ?? '';
$params = $_POST['params'] ?? [];

// Phase 2.5 (this PR — batch A of the public/ajax.php cleanup):
// the six Passkey/WebAuthn actions that used to live below have
// been lifted out into dedicated Laravel routes — see
// `App\Http\Controllers\Legacy\PasskeyAjaxController` and the
// `Route::prefix('passkey')` block in `routes/web.php`. With those
// gone, every remaining action requires a logged-in user, so the
// previous two-action carve-out (`getPasskeyGetArgs` /
// `processPasskeyGet`) is no longer needed.
loggedinorreturn();

class AjaxInterface{

    // Phase 2.5 (this PR — batch B of the public/ajax.php cleanup):
    // the four medal actions
    // (`toggleUserMedalStatus`, `buyMedal`, `giftMedal`, `saveUserMedal`)
    // were lifted out into dedicated Laravel routes — see
    // `App\Http\Controllers\Legacy\MedalAjaxController` and the
    // `Route::prefix('medal')` block in `routes/web.php`. The
    // wire-level contract (POST keys / `{ret, msg, data}` envelope)
    // is preserved verbatim so the inline `<script>` callers in
    // `MedalController.php` and the "Save chosen medals" form in
    // `public/userdetails.php` keep working after a single
    // `jQuery.post(...)` URL flip in each.

    // Phase 2.5 (this PR — batch E of the public/ajax.php cleanup):
    // the seven remaining mod/utility actions
    // (`attendanceRetroactive`, `getPtGen`, `removeUserLeechWarn`,
    // `getOffer`, `approvalModal`, `approval`, `clearShoutBox`)
    // were lifted out into dedicated Laravel routes — see
    // `App\Http\Controllers\Legacy\ModAjaxController` and
    // `App\Http\Controllers\Legacy\MiscAjaxController`, plus the
    // `Route::prefix('mod')` and `Route::prefix('misc')` blocks in
    // `routes/web.php`. The wire-level contract (POST keys /
    // `{ret, msg, data}` envelope) is preserved verbatim so the
    // first-party JS callers (`public/userdetails.php`,
    // `public/index.php`, `public/js/ptgen.js`,
    // `AttendanceController.php`) keep working after a single
    // `jQuery.post(...)` URL flip in each.

    public static function addClaim($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\ClaimRepository();
        return $rep->store($CURUSER['id'], $params['torrent_id']);
    }

    public static function removeClaim($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\ClaimRepository();
        return $rep->delete($params['id'], $CURUSER['id']);
    }

    public static function removeUserLeechWarn($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\UserRepository();
        return $rep->removeLeechWarn($CURUSER['id'], $params['uid']);
    }

    public static function getOffer($params)
    {
        $offer = \App\Models\Offer::query()->findOrFail($params['id']);
        return $offer->toArray();
    }

    public static function approvalModal($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\TorrentRepository();
        return $rep->buildApprovalModal($CURUSER['id'], $params['torrent_id']);
    }

    public static function approval($params)
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

    public static function removeHitAndRun($params)
    public static function addSeedBoxRecord($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\SeedBoxRepository();
        $params['uid'] = $CURUSER['id'];
        $params['type'] = \App\Models\SeedBoxRecord::TYPE_USER;
        $params['status'] = \App\Models\SeedBoxRecord::STATUS_UNAUDITED;
        return $rep->store($params);
    }

    public static function removeSeedBoxRecord($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\SeedBoxRepository();
        return $rep->delete($params['id'], $CURUSER['id']);
    }

    public static function clearShoutBox($params)
    {
        global $CURUSER;
        user_can('sbmanage', true);
        \Nexus\Database\NexusDB::table('shoutbox')->delete();
        return true;
    }

    public static function buyMedal($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToBuyMedal($CURUSER['id'], $params['medal_id']);
    }

    public static function claimTask($params)
    public static function giftMedal($params)
    {
        global $CURUSER;
        $rep = new \App\Repositories\BonusRepository();
        return $rep->consumeToGiftMedal($CURUSER['id'], $params['medal_id'], $params['uid']);
    }

    public static function saveUserMedal($params)
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

    public static function addToken($params)
    {
        global $CURUSER;
        if (empty($params['name'])) {
            throw new \InvalidArgumentException("Name is required");
        }
        $user = \App\Models\User::query()->findOrFail($CURUSER['id'], \App\Models\User::$commonFields);
        $user->createToken($params['name']);
        return true;
    }

    public static function removeToken($params)
    {
        global $CURUSER;
        if (empty($params['id'])) {
            throw new \InvalidArgumentException("id is required");
        }
        $user = \App\Models\User::query()->findOrFail($CURUSER['id'], \App\Models\User::$commonFields);
        $user->tokens()->where('id', $params['id'])->delete();
        return true;
    }

    // Phase 2.5 (this PR — batch A of the public/ajax.php cleanup):
    // the six Passkey/WebAuthn actions
    // (`getPasskeyCreateArgs`, `processPasskeyCreate`, `deletePasskey`,
    // `getPasskeyList`, `getPasskeyGetArgs`, `processPasskeyGet`)
    // were lifted out into dedicated Laravel routes — see
    // `App\Http\Controllers\Legacy\PasskeyAjaxController` and the
    // `Route::prefix('passkey')` block in `routes/web.php`. The
    // wire-level contract (POST keys / `{ret, msg, data}` envelope)
    // is preserved verbatim so `public/js/passkey.js` keeps working
    // after a single `apiUrl` map flip.
}

$class = 'AjaxInterface';
$reflection = new \ReflectionClass($class);

try {
    if($reflection->hasMethod($action) && $reflection->getMethod($action)->isStatic()) {
        $result = $class::$action($params);
        exit(json_encode(success($result)));
    } else {
        do_log("hacking attempt made by {$CURUSER['username']},uid {$CURUSER['id']}", 'error');
        throw new \RuntimeException("Invalid action: $action");
    }
}catch(\Throwable $exception){
    do_log($exception->getMessage() . $exception->getTraceAsString(), "error");
    exit(json_encode(fail($exception->getMessage(), $_POST)));
}
