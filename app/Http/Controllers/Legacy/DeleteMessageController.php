<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/deletemessage.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Original legacy flow:
 *
 *   1. `id` must be a positive integer; otherwise `die("Invalid ID")`.
 *   2. `type` must be `'in'` or `'out'`.
 *   3. For `'in'`: load `messages.{receiver, location}` by id; reject
 *      if it isn't the current user's; if `location='in'` delete;
 *      if `location='both'` move to sentbox-only (`out`); else die.
 *   4. For `'out'`: symmetric for sender + sentbox.
 *   5. Redirect to `/messages.php` (or `/messages.php?out=1` if
 *      sentbox).
 *
 * The legacy script terminates with plain-text `die("Bad message id")`
 * etc. — those are HTTP 200 + plain body, NOT 404s. We translate them
 * to plain-text responses with the legacy strings so the JS callers
 * (if any read the body) see the same text. Status code is upgraded
 * to 4xx where appropriate — the legacy 200 was an accident, not a
 * contract.
 *
 * Auth: same `auth.nexus:nexus-web` group as the other Phase 2 routes.
 */
class DeleteMessageController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): RedirectResponse|Response
    {
        $user = $this->context->user();
        if ($user === null) {
            // Auth middleware should have caught this — surface a
            // typed error rather than crashing downstream.
            return new Response('Unauthenticated.', 401);
        }

        $rawId = (string) $request->query('id', '');
        if (! is_numeric($rawId) || (int) $rawId < 1 || floor((float) $rawId) !== (float) (int) $rawId) {
            return new Response('Invalid ID', 400);
        }
        $messageId = (int) $rawId;

        $type = (string) $request->query('type', '');
        $userId = (int) $user->id;
        $lang = $this->langMap();

        if ($type === 'in') {
            $error = $this->processInbox($messageId, $userId, $lang);
            if ($error !== null) {
                return $error;
            }

            return new RedirectResponse('/messages.php');
        }

        if ($type === 'out') {
            $error = $this->processSentbox($messageId, $userId, $lang);
            if ($error !== null) {
                return $error;
            }

            return new RedirectResponse('/messages.php?out=1');
        }

        return new Response($lang['std_unknown_pm_type'], 400);
    }

    /**
     * @param  array{std_bad_message_id:string,std_not_suggested:string,std_not_in_inbox:string,std_not_in_sentbox:string,std_unknown_pm_type:string}  $lang
     */
    private function processInbox(int $messageId, int $userId, array $lang): ?Response
    {
        $rowObj = NexusDB::table('messages')
            ->where('id', $messageId)
            ->select(['receiver', 'location'])
            ->first();
        if ($rowObj === null) {
            return new Response($lang['std_bad_message_id'], 404);
        }
        $row = (array) $rowObj;
        if ((int) $row['receiver'] !== $userId) {
            return new Response($lang['std_not_suggested'], 403);
        }

        if ($row['location'] === 'in') {
            NexusDB::table('messages')->where('id', $messageId)->delete();

            return null;
        }
        if ($row['location'] === 'both') {
            NexusDB::table('messages')->where('id', $messageId)->update(['location' => 'out']);

            return null;
        }

        return new Response($lang['std_not_in_inbox'], 400);
    }

    /**
     * @param  array{std_bad_message_id:string,std_not_suggested:string,std_not_in_inbox:string,std_not_in_sentbox:string,std_unknown_pm_type:string}  $lang
     */
    private function processSentbox(int $messageId, int $userId, array $lang): ?Response
    {
        $rowObj = NexusDB::table('messages')
            ->where('id', $messageId)
            ->select(['sender', 'location'])
            ->first();
        if ($rowObj === null) {
            return new Response($lang['std_bad_message_id'], 404);
        }
        $row = (array) $rowObj;
        if ((int) $row['sender'] !== $userId) {
            return new Response($lang['std_not_suggested'], 403);
        }

        if ($row['location'] === 'out') {
            NexusDB::table('messages')->where('id', $messageId)->delete();

            return null;
        }
        if ($row['location'] === 'both') {
            NexusDB::table('messages')->where('id', $messageId)->update(['location' => 'in']);

            return null;
        }

        return new Response($lang['std_not_in_sentbox'], 400);
    }

    /**
     * Pull the deletemessage strings out of the legacy `$lang_*` global.
     * Falls back to the legacy English defaults if the lang file isn't
     * loaded (e.g. a test that hasn't run `dbconn()`).
     *
     * @return array{std_bad_message_id:string,std_not_suggested:string,std_not_in_inbox:string,std_not_in_sentbox:string,std_unknown_pm_type:string}
     */
    private function langMap(): array
    {
        $defaults = [
            'std_bad_message_id' => 'Bad message id',
            'std_not_suggested' => 'Action not suggested',
            'std_not_in_inbox' => 'Message is not in your inbox',
            'std_not_in_sentbox' => 'Message is not in your sentbox',
            'std_unknown_pm_type' => 'Unknown pm type',
        ];

        $loaded = $GLOBALS['lang_deletemessage'] ?? [];
        if (! is_array($loaded)) {
            return $defaults;
        }

        return [
            'std_bad_message_id' => (string) ($loaded['std_bad_message_id'] ?? $defaults['std_bad_message_id']),
            'std_not_suggested' => (string) ($loaded['std_not_suggested'] ?? $defaults['std_not_suggested']),
            'std_not_in_inbox' => (string) ($loaded['std_not_in_inbox'] ?? $defaults['std_not_in_inbox']),
            'std_not_in_sentbox' => (string) ($loaded['std_not_in_sentbox'] ?? $defaults['std_not_in_sentbox']),
            'std_unknown_pm_type' => (string) ($loaded['std_unknown_pm_type'] ?? $defaults['std_unknown_pm_type']),
        ];
    }
}
