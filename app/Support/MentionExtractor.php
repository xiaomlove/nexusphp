<?php

namespace App\Support;

use App\Models\User;

/**
 * Extract @username mentions from a free-text body (forum posts, PMs).
 *
 * The supported pattern is `@<word>` where `<word>` is 2..32 letters,
 * digits, dot, dash, or underscore. Mentions inside [code]…[/code]
 * blocks and bare URLs are skipped to avoid noisy false positives.
 *
 * Returned user IDs are deduplicated and pruned to existing accounts.
 */
final class MentionExtractor
{
    /**
     * @return list<int> user IDs of valid @mentions in $text, in
     *                   first-occurrence order, excluding $excludeId.
     */
    public static function userIdsFromText(?string $text, int $excludeId = 0): array
    {
        $names = self::usernamesFromText($text);
        if (empty($names)) {
            return [];
        }

        $rows = User::query()
            ->whereIn('username', $names)
            ->pluck('id', 'username')
            ->all();

        $ids = [];
        $seen = [];
        foreach ($names as $name) {
            $id = (int) ($rows[$name] ?? 0);
            if ($id <= 0 || $id === $excludeId || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @return list<string> raw mention names (pre-DB lookup), unique
     *                      and in source order. Public for testability.
     */
    public static function usernamesFromText(?string $text): array
    {
        if ($text === null || $text === '') {
            return [];
        }

        // Strip [code]…[/code] blocks so usernames there don't trigger
        // notifications — same intent as the BBCode renderer.
        $stripped = preg_replace('~\[code\].*?\[/code\]~is', '', $text);
        if (! is_string($stripped)) {
            $stripped = $text;
        }
        // Also strip any URL — `@user@domain.tld` and `https://…?@x=…`
        // shouldn't ping someone called `domain` or `x`.
        $stripped = preg_replace('~https?://\S+~i', '', $stripped) ?? $stripped;

        if (! preg_match_all('~(?<![A-Za-z0-9_.-])@([A-Za-z0-9_.-]{2,32})~', $stripped, $matches)) {
            return [];
        }

        $names = [];
        $seen = [];
        foreach ($matches[1] as $name) {
            $key = strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $names[] = $name;
        }

        return $names;
    }
}
