<?php

namespace App\Support;

/**
 * Tiny line-level diff for the edit-history viewer. Computes the
 * longest common subsequence (LCS) between the lines of two strings
 * and walks both sides to emit a list of operations:
 *
 *   ['op' => 'unchanged'|'added'|'removed', 'text' => string]
 *
 * Inputs are split on \r?\n; trailing empty trailing newlines are
 * preserved to keep blank-line edits visible. The implementation is
 * dependency-free (no sebastian/diff dependency in production).
 *
 * It is O(n*m) memory and time, which is fine for forum posts —
 * typical posts are 1–200 lines. For very large posts (>~500 lines)
 * the caller should fall back to the legacy 'show full body' view.
 */
final class PostDiff
{
    /**
     * @return list<array{op: string, text: string}>
     */
    public static function lineDiff(string $before, string $after): array
    {
        if ($before === $after) {
            // Fast path — caller can suppress rendering entirely.
            return [];
        }
        $a = self::splitLines($before);
        $b = self::splitLines($after);

        $n = count($a);
        $m = count($b);

        // Cap to keep memory reasonable. Anything larger falls back
        // to a single removed/added pair.
        if ($n > 800 || $m > 800) {
            return [
                ['op' => 'removed', 'text' => $before],
                ['op' => 'added', 'text' => $after],
            ];
        }

        // LCS length matrix.
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                if ($a[$i - 1] === $b[$j - 1]) {
                    $lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
                }
            }
        }

        // Backtrack to produce the edit script.
        $ops = [];
        $i = $n;
        $j = $m;
        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $a[$i - 1] === $b[$j - 1]) {
                array_unshift($ops, ['op' => 'unchanged', 'text' => $a[$i - 1]]);
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
                array_unshift($ops, ['op' => 'added', 'text' => $b[$j - 1]]);
                $j--;
            } else {
                array_unshift($ops, ['op' => 'removed', 'text' => $a[$i - 1]]);
                $i--;
            }
        }

        return $ops;
    }

    /**
     * @return list<string>
     */
    private static function splitLines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return preg_split('~\r?\n~', $text) ?: [];
    }
}
