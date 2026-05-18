<?php

namespace App\Support;

/**
 * Stateless byte/encoding helpers extracted from `include/functions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * The legacy procedural helpers
 *
 *   - `base64()`     (boolean-dispatched `base64_encode`/`base64_decode` wrapper)
 *   - `code()`       (older IBM-437 → HTML-entity converter using a literal byte
 *                     table and `htmlspecialchars` pass; ONE call site remains
 *                     and it is commented out, but the function is preserved
 *                     since some forks still reach for it)
 *   - `code_new()`   (newer IBM-437 → numeric-HTML-entity converter that walks
 *                     the input byte by byte; this is the variant used by
 *                     `public/viewnfo.php` and `public/details.php` when
 *                     rendering uploaded `.nfo` files)
 *
 * all collapse into the static methods below.
 *
 * Lives under `App\Support` (not `App\Services`) because every method is
 * pure — no DI, no DB, no config, no global state. Same convention as
 * {@see Imdb}, {@see Ratio}, {@see Validators}, {@see Format},
 * {@see Strings}, {@see Time}.
 */
final class Codec
{
    /**
     * High-byte → Unicode codepoint table used by {@see ibm437ToEntities}.
     *
     * Bytes 0x7F..0xFF map to these codepoints, which the renderer emits as
     * numeric HTML entities (`&#NNNN;`). Mirrors the legacy `code_new()`
     * `$cf` table verbatim (indices 127..255 of that 256-entry array — the
     * lower half was an identity map and is folded into the loop below).
     *
     * Source: same table as UNIT3D's `Nfo.php`, referenced in the legacy
     * function's doc-comment.
     *
     * @var array<int, int>
     */
    private const IBM437_CODEPOINTS = [
        127 => 8962, 128 => 199, 129 => 252, 130 => 233, 131 => 226, 132 => 228,
        133 => 224, 134 => 229, 135 => 231, 136 => 234, 137 => 235, 138 => 232,
        139 => 239, 140 => 238, 141 => 236, 142 => 196, 143 => 197, 144 => 201,
        145 => 230, 146 => 198, 147 => 244, 148 => 246, 149 => 242, 150 => 251,
        151 => 249, 152 => 255, 153 => 214, 154 => 220, 155 => 162, 156 => 163,
        157 => 165, 158 => 8359, 159 => 402, 160 => 225, 161 => 237, 162 => 243,
        163 => 250, 164 => 241, 165 => 209, 166 => 170, 167 => 186, 168 => 191,
        169 => 8976, 170 => 172, 171 => 189, 172 => 188, 173 => 161, 174 => 171,
        175 => 187, 176 => 9617, 177 => 9618, 178 => 9619, 179 => 9474, 180 => 9508,
        181 => 9569, 182 => 9570, 183 => 9558, 184 => 9557, 185 => 9571, 186 => 9553,
        187 => 9559, 188 => 9565, 189 => 9564, 190 => 9563, 191 => 9488, 192 => 9492,
        193 => 9524, 194 => 9516, 195 => 9500, 196 => 9472, 197 => 9532, 198 => 9566,
        199 => 9567, 200 => 9562, 201 => 9556, 202 => 9577, 203 => 9574, 204 => 9568,
        205 => 9552, 206 => 9580, 207 => 9575, 208 => 9576, 209 => 9572, 210 => 9573,
        211 => 9561, 212 => 9560, 213 => 9554, 214 => 9555, 215 => 9579, 216 => 9578,
        217 => 9496, 218 => 9484, 219 => 9608, 220 => 9604, 221 => 9612, 222 => 9616,
        223 => 9600, 224 => 945, 225 => 223, 226 => 915, 227 => 960, 228 => 931,
        229 => 963, 230 => 181, 231 => 964, 232 => 934, 233 => 920, 234 => 937,
        235 => 948, 236 => 8734, 237 => 966, 238 => 949, 239 => 8745, 240 => 8801,
        241 => 177, 242 => 8805, 243 => 8804, 244 => 8992, 245 => 8993, 246 => 247,
        247 => 8776, 248 => 176, 249 => 8729, 250 => 183, 251 => 8730, 252 => 8319,
        253 => 178, 254 => 9632, 255 => 160,
    ];

    /**
     * Pre-formatted HTML entity table used by {@see ibm437ToEntitiesLegacy}.
     *
     * Bytes 0x80..0xFF map to these hex entities (`&#x00NN;`). Mirrors the
     * legacy `code()` `$tablehtml` array verbatim. The legacy code keeps
     * these as strings (not codepoints) because it consumes them via
     * `str_replace`, not a sprintf loop.
     *
     * @var array<int, string>
     */
    private const IBM437_HTML_ENTITIES = [
        '&#x00c7;', '&#x00fc;', '&#x00e9;', '&#x00e2;', '&#x00e4;', '&#x00e0;',
        '&#x00e5;', '&#x00e7;', '&#x00ea;', '&#x00eb;', '&#x00e8;', '&#x00ef;',
        '&#x00ee;', '&#x00ec;', '&#x00c4;', '&#x00c5;', '&#x00c9;', '&#x00e6;',
        '&#x00c6;', '&#x00f4;', '&#x00f6;', '&#x00f2;', '&#x00fb;', '&#x00f9;',
        '&#x00ff;', '&#x00d6;', '&#x00dc;', '&#x00a2;', '&#x00a3;', '&#x00a5;',
        '&#x20a7;', '&#x0192;', '&#x00e1;', '&#x00ed;', '&#x00f3;', '&#x00fa;',
        '&#x00f1;', '&#x00d1;', '&#x00aa;', '&#x00ba;', '&#x00bf;', '&#x2310;',
        '&#x00ac;', '&#x00bd;', '&#x00bc;', '&#x00a1;', '&#x00ab;', '&#x00bb;',
        '&#x2591;', '&#x2592;', '&#x2593;', '&#x2502;', '&#x2524;', '&#x2561;',
        '&#x2562;', '&#x2556;', '&#x2555;', '&#x2563;', '&#x2551;', '&#x2557;',
        '&#x255d;', '&#x255c;', '&#x255b;', '&#x2510;', '&#x2514;', '&#x2534;',
        '&#x252c;', '&#x251c;', '&#x2500;', '&#x253c;', '&#x255e;', '&#x255f;',
        '&#x255a;', '&#x2554;', '&#x2569;', '&#x2566;', '&#x2560;', '&#x2550;',
        '&#x256c;', '&#x2567;', '&#x2568;', '&#x2564;', '&#x2565;', '&#x2559;',
        '&#x2558;', '&#x2552;', '&#x2553;', '&#x256b;', '&#x256a;', '&#x2518;',
        '&#x250c;', '&#x2588;', '&#x2584;', '&#x258c;', '&#x2590;', '&#x2580;',
        '&#x03b1;', '&#x00df;', '&#x0393;', '&#x03c0;', '&#x03a3;', '&#x03c3;',
        '&#x03bc;', '&#x03c4;', '&#x03a6;', '&#x0398;', '&#x03a9;', '&#x03b4;',
        '&#x221e;', '&#x03c6;', '&#x03b5;', '&#x2229;', '&#x2261;', '&#x00b1;',
        '&#x2265;', '&#x2264;', '&#x2320;', '&#x2321;', '&#x00f7;', '&#x2248;',
        '&#x00b0;', '&#x2219;', '&#x00b7;', '&#x221a;', '&#x207f;', '&#x00b2;',
        '&#x25a0;', '&#x00a0;',
    ];

    /**
     * Base64-encode a string. One-line wrapper over the PHP built-in;
     * preserved so existing call sites in
     * `include/globalfunctions.php` keep working through the
     * `base64($s, true)` proxy.
     */
    public static function base64Encode(string $value): string
    {
        return base64_encode($value);
    }

    /**
     * Base64-decode a string. Returns the raw decoded bytes — no
     * `strict` flag is passed, matching the legacy `base64_decode($s)`
     * behaviour (silently strips invalid chars).
     */
    public static function base64Decode(string $value): string
    {
        return (string) base64_decode($value);
    }

    /**
     * Render an IBM-437 byte string as numeric HTML entities (`&#NNNN;`).
     *
     * Corresponds to the legacy `code_new()` — the active path used by
     * `public/viewnfo.php` and `public/details.php` to display uploaded
     * `.nfo` files. Walks the input byte by byte: each byte with ordinal
     * `>= 127` becomes a numeric entity looked up in
     * {@see IBM437_COEDPOINTS}, anything else is appended verbatim.
     *
     * If `$view === 'magic'` the Swedish-letter remapping is applied
     * before the entity substitution (preserves `code_new()`'s
     * `$swedishmagic` block exactly).
     */
    public static function ibm437ToEntities(string $input, string $view): string
    {
        $out = '';
        $len = strlen($input);
        for ($c = 0; $c < $len; $c++) {
            $byte = $input[$c];
            $ord = ord($byte);
            if ($ord >= 127) {
                $out .= '&#'.self::IBM437_CODEPOINTS[$ord].';';
            } else {
                $out .= $byte;
            }
        }

        if ($view === 'magic') {
            $out = self::applySwedishMagic($out);
        }

        return $out;
    }

    /**
     * Older IBM-437 → HTML-entity renderer using `htmlspecialchars` and
     * `str_replace` over the {@see IBM437_HTML_ENTITIES} table.
     *
     * Corresponds to the legacy `code()`. Currently has no live call
     * sites (the one in `public/viewnfo.php` is commented out) but the
     * proxy is kept for forks. Quirks preserved:
     *
     *   - `htmlspecialchars()` is called with default flags, so on
     *     PHP 8.1+ single quotes are entity-encoded too — that's how
     *     the function behaves today, we don't change it.
     *   - The control-char strip uses `\012` and `\015` as comments
     *     (i.e. LF and CR are NOT stripped); we keep that exactly.
     *   - `$swedishmagic` is applied BEFORE the high-byte table swap,
     *     matching the legacy ordering.
     */
    public static function ibm437ToEntitiesLegacy(string $input, string $view): string
    {
        $s = htmlspecialchars($input);

        // Control chars 0x00-0x09, 0x0B, 0x0C, 0x0E-0x1F, 0x7F → space.
        // LF (0x0A) and CR (0x0D) are intentionally NOT stripped.
        $control = [
            "\000", "\001", "\002", "\003", "\004", "\005", "\006", "\007",
            "\010", "\011", "\013", "\014", "\016", "\017",
            "\020", "\021", "\022", "\023", "\024", "\025", "\026", "\027",
            "\030", "\031", "\032", "\033", "\034", "\035", "\036", "\037",
            "\177",
        ];
        $s = str_replace($control, ' ', $s);

        if ($view === 'magic') {
            $s = self::applySwedishMagic($s);
        }

        $table437 = array_map('chr', range(0x80, 0xFF));

        return str_replace($table437, self::IBM437_HTML_ENTITIES, $s);
    }

    /**
     * Swedish-letter remapping shared by {@see ibm437ToEntities} and
     * {@see ibm437ToEntitiesLegacy}.
     *
     * Replaces ISO-8859-1 byte sequences for the Swedish characters
     * å/ä/ö/Å/Ä/Ö/É/é with their IBM-437 byte equivalents, so the
     * subsequent entity table renders them correctly. The order
     * matters: `\305`/`\304`/`\326` are surrounded by a `[ -~]`
     * character class to avoid eating bytes that are already part of
     * other multibyte sequences (this is the legacy regex; we don't
     * change it).
     */
    private static function applySwedishMagic(string $s): string
    {
        $s = str_replace("\345", "\206", $s);
        $s = str_replace("\344", "\204", $s);
        $s = str_replace("\366", "\224", $s);
        $s = preg_replace("/([ -~])\305([ -~])/", "\\1\217\\2", $s) ?? $s;
        $s = preg_replace("/([ -~])\304([ -~])/", "\\1\216\\2", $s) ?? $s;
        $s = preg_replace("/([ -~])\326([ -~])/", "\\1\231\\2", $s) ?? $s;
        $s = str_replace("\311", "\220", $s);

        return str_replace("\351", "\202", $s);
    }

    /**
     * Serialize a scalar / array value into a PHP source-code literal
     * suitable for `eval`-style re-import. Mirrors the legacy
     * `getExportedValue()` exactly — same idiosyncratic output format:
     *
     *   - strings are single-quoted with `\` and `'` escaped
     *   - arrays open with `array(\r` (carriage return, not `\n`!),
     *     each entry is `<indent>\t<key> => <value>,\n`, and the
     *     closing paren sits at the parent's indent depth
     *   - integers / floats / doubles all stringify as `'<value>'`
     *     (the legacy quirk — numeric values are emitted as STRINGS,
     *     not as bare PHP numeric literals)
     *   - booleans → `true` / `false` (lowercase, unquoted)
     *   - `null` → `NULL` (uppercase, unquoted)
     *   - unknown types fall through to `NULL`
     *
     * `$indent` is the parent-row indent string; the helper recurses
     * with `$indent . "\t"` to nest array bodies. Pass `null` (the
     * default) for top-level calls — that matches the legacy default
     * argument.
     *
     * Only call site today is the `WriteConfig()` helper that
     * snapshots `config/allconfig.php` after admin edits. The output
     * format must round-trip back through PHP's parser, so the quirks
     * above are part of the contract — do not "modernise" them.
     *
     * @param  mixed  $value
     */
    public static function phpExport($value, ?string $indent = null): string
    {
        switch (gettype($value)) {
            case 'string':
                return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
            case 'array':
                $output = "array(\r";
                foreach ($value as $key => $entry) {
                    $output .= $indent."\t".self::phpExport($key, $indent."\t").' => '.self::phpExport($entry, $indent."\t");
                    $output .= ",\n";
                }
                $output .= $indent.')';

                return $output;
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'NULL':
                return 'NULL';
            case 'integer':
            case 'double':
            case 'float':
                return "'".(string) $value."'";
        }

        return 'NULL';
    }
}
