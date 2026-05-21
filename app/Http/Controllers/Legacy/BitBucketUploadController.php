<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/bitbucket-upload.php` (deleted in the
 * same PR).
 *
 * Phase 2 batch — see `docs/legacy-strategy.md` § "Phase 2" and
 * `docs/migration-recipe.md`. The user-facing avatar-upload tool:
 * an authenticated, non-parked user uploads an image, which is
 * GD-resampled to fit `200×150` (preserving aspect ratio, never
 * scaling up), saved into `public/<main.bitbucket>/<basename>`,
 * recorded in the `bitbucket` table, and used as the new
 * `users.avatar` URL.
 *
 * Original legacy flow (`public/bitbucket-upload.php`, 93 LOC):
 *   1. `dbconn();` + `loggedinorreturn();` + `parked();` bootstrap.
 *   2. `if ($enablebitbucket_main != 'yes') permissiondenied();`.
 *   3. POST: validate `$_FILES["file"]` (non-empty, ≤ 256 KB,
 *      basename-safe, `getimagesize()` type matching the extension);
 *      bail with `stderr()` on each branch.
 *   4. Resolve target path `getFullDirectory("$bitbucket/$filename")`;
 *      bail with `stderr()` if `file_exists()`.
 *   5. Compute `($scale, newwidth, newheight)` so the image fits
 *      within `200×150` (`$scaleh × $scalew`), only ever scaling
 *      DOWN (`$scale = 1` when both dimensions already fit).
 *   6. `imagecreatefromgif/jpeg/png()` → `imagecreatetruecolor()` →
 *      `imagecopyresampled()` → `imagegif/jpeg/png()` to disk.
 *   7. `INSERT INTO bitbucket (owner, name, added, public)` and
 *      `UPDATE users SET avatar = '<url>'` for the uploader.
 *   8. `stderr($success_text, ..., false)` — the legacy `$die=true`
 *      default kicks in *despite* the third-arg `false` (which is
 *      `$htmlstrip`, not `$die`), so the success page is the only
 *      response body.
 *   9. GET (or fall-through after a non-blocking branch): render the
 *      `<form action="bitbucket-upload.php" enctype="multipart/form-data">`
 *      block. The form prints an inline warning when the upload
 *      directory is not writable.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Parked user → `abort(403, 'Your account is parked.')` (legacy
 *     `parked()` rendered HTTP 200 envelope; tightened, same as
 *     `GetAttachmentController`).
 *   - `Setting::get('main.enablebitbucket') !== 'yes'` →
 *     `abort(403, 'Permission denied.')` (legacy
 *     `permissiondenied()` was HTTP 200; tightened).
 *   - GET → 200 chrome-less HTML form. Pre-renders the legacy
 *     "upload directory unwritable" notice when the configured
 *     bucket path is not writable, so admins can spot the
 *     filesystem-permission misconfiguration without trying an
 *     upload first.
 *   - POST with missing / oversize / non-image file → 422 with the
 *     legacy error string (`Nothing received.`, `File too large.`,
 *     `Invalid image format.`) as the abort message.
 *   - POST with a basename that doesn't round-trip
 *     `pathinfo()['basename']` → 422 (`Bad file name.`).
 *   - POST when the target file already exists → 422
 *     (`File already exists: <name>.`). Mirrors the legacy
 *     `std_file_already_exists` envelope.
 *   - POST when `imagecreatefrom*()` returns `false` → 500
 *     (`Image processing failed.`). The legacy `stderr()` was
 *     HTTP 200; tightened so monitoring catches the GD failure.
 *   - Happy path → 200 chrome-less HTML success page (legacy
 *     parity: a "use this URL / upload another / preview" block).
 *     The `bitbucket` row is inserted, the uploader's `avatar` is
 *     set to the public URL, and the resampled image lands on
 *     disk before the response is sent.
 *
 * URL preserved exactly so:
 *   - the FAQ entries seeded by `database/seeders/FaqTableSeeder.php`
 *     (`href="bitbucket-upload.php"`),
 *   - the per-locale `lang/<locale>/lang_usercp.php`
 *     `text_bitbucket_note` paragraph rendered on
 *     `public/usercp.php:255`,
 *   - any user bookmarks
 *
 * keep working without template changes. The matching nginx
 * exact-location entry lives in
 * `.docker/openresty/sites/app.conf.template`.
 *
 * UI strings: hardcoded English, same trade-off as
 * `StaffMessController` / `IncrementBulkController`. The
 * `lang/<locale>/lang_bitbucket-upload.php` files (if any) stay
 * in tree for a Phase 5 sweep to re-introduce localisation
 * cleanly.
 */
class BitBucketUploadController extends Controller
{
    public const MAX_BYTES = 256 * 1024;

    private const SCALE_HEIGHT = 200;

    private const SCALE_WIDTH = 150;

    /**
     * `getimagesize()` returns one of the `IMAGETYPE_*` constants in
     * the `[2]` slot. We map only the three the legacy script
     * accepted.
     *
     * @var array<int, string>
     */
    private const SUPPORTED_TYPES = [
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
    ];

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (($user->parked ?? 'no') === 'yes') {
            abort(403, 'Your account is parked.');
        }
        if ((string) Setting::get('main.enablebitbucket') !== 'yes') {
            abort(403, 'Permission denied.');
        }

        if ($request->isMethod('POST')) {
            return $this->handleUpload($request);
        }

        return $this->renderForm($this->bucketWritable());
    }

    private function handleUpload(Request $request): Response
    {
        $validator = Validator::make(
            $request->all() + ['file' => $request->file('file')],
            [
                'file' => ['required', 'file', 'image', 'mimes:gif,jpg,jpeg,png', 'max:'.((int) (self::MAX_BYTES / 1024))],
                'public' => ['nullable', 'in:yes'],
            ],
            [
                'file.required' => 'Nothing received.',
                'file.file' => 'Nothing received.',
                'file.image' => 'Invalid image format.',
                'file.mimes' => 'Invalid image format.',
                'file.max' => 'File too large.',
            ],
        );
        if ($validator->fails()) {
            abort(422, $validator->errors()->first());
        }

        $file = $request->file('file');
        if (! $file instanceof UploadedFile || ($file->getSize() === false || $file->getSize() < 1)) {
            abort(422, 'Nothing received.');
        }

        $filename = (string) $file->getClientOriginalName();
        if (! $this->isSafeBasename($filename)) {
            abort(422, 'Bad file name.');
        }

        $bucketDir = $this->bucketDirName();
        $bucketPath = public_path($bucketDir);
        if (! is_dir($bucketPath) || ! is_writable($bucketPath)) {
            abort(500, 'Upload directory is not writable.');
        }

        $tgtfile = $bucketPath.'/'.$filename;
        if (file_exists($tgtfile)) {
            abort(422, 'File already exists: '.$filename.'.');
        }

        $size = @getimagesize($file->getRealPath());
        if ($size === false || ! isset($size[0], $size[1], $size[2])) {
            abort(422, 'Invalid image format.');
        }
        $width = (int) $size[0];
        $height = (int) $size[1];
        $imageType = (int) $size[2];

        if (! isset(self::SUPPORTED_TYPES[$imageType])) {
            abort(422, 'Invalid image format.');
        }
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $expected = self::SUPPORTED_TYPES[$imageType];
        $extensionOk = $extension === $expected
            || ($imageType === IMAGETYPE_JPEG && $extension === 'jpeg');
        if (! $extensionOk) {
            abort(422, 'Invalid image format.');
        }

        [$newWidth, $newHeight] = $this->resampleDimensions($width, $height);

        $orig = match ($imageType) {
            IMAGETYPE_GIF => @imagecreatefromgif($file->getRealPath()),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
        };
        if ($orig === false) {
            abort(500, 'Sorry, the uploaded image failed processing.');
        }

        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        if ($thumb === false) {
            imagedestroy($orig);
            abort(500, 'Sorry, the uploaded image failed processing.');
        }

        imagecopyresampled(
            $thumb,
            $orig,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $width,
            $height,
        );

        $writeOk = match ($imageType) {
            IMAGETYPE_GIF => imagegif($thumb, $tgtfile),
            IMAGETYPE_JPEG => imagejpeg($thumb, $tgtfile),
            IMAGETYPE_PNG => imagepng($thumb, $tgtfile),
        };

        imagedestroy($thumb);
        imagedestroy($orig);

        if (! $writeOk) {
            abort(500, 'Sorry, the uploaded image failed processing.');
        }

        $url = $this->publicUrl($bucketDir, $filename);
        $public = ((string) $request->input('public', '')) === 'yes' ? '1' : '0';

        $userId = (int) $this->context->user()->id;

        NexusDB::insert('bitbucket', [
            'owner' => $userId,
            'name' => $filename,
            'added' => date('Y-m-d H:i:s'),
            'public' => $public,
        ]);
        NexusDB::table('users')->where('id', $userId)->update(['avatar' => $url]);

        $rescaledNote = ($newWidth === $width && $newHeight === $height)
            ? 'Image needed no rescaling.'
            : 'Image rescaled from '.$height.' x '.$width.' to '.$newHeight.' x '.$newWidth.'.';

        $body = '<h1>Avatar upload</h1>'."\n"
            .'<p>Use the following URL to embed the image:</p>'."\n"
            .'<p><b><a href="'.htmlspecialchars($url).'">'.htmlspecialchars($url).'</a></b></p>'."\n"
            .'<p><a href="bitbucket-upload.php">Upload another file</a>.</p>'."\n"
            .'<p><img src="'.htmlspecialchars($url).'" border="0"></p>'."\n"
            .'<p>'.htmlspecialchars($rescaledNote).' Profile updated.</p>'."\n";

        return new Response($this->wrap('Avatar upload', $body));
    }

    private function renderForm(bool $bucketWritable): Response
    {
        $unwritableNotice = '';
        if (! $bucketWritable) {
            $unwritableNotice = '<tr><td align="left" colspan="2">'
                .'<b>Warning:</b> Upload directory is not writable. Contact a sysop.'
                .'</td></tr>'."\n";
        }

        $disclaimer = sprintf(
            'Maximum image dimensions: %dpx tall, %dpx wide. Maximum file size: %s bytes.',
            self::SCALE_HEIGHT,
            self::SCALE_WIDTH,
            number_format(self::MAX_BYTES),
        );

        $body = '<h1>Avatar upload</h1>'."\n"
            .'<form method="post" action="bitbucket-upload.php" enctype="multipart/form-data">'."\n"
            .'<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .$unwritableNotice
            .'<tr><td align="left" colspan="2">'.htmlspecialchars($disclaimer).'</td></tr>'."\n"
            .'<tr><td class="rowhead">File</td>'
            .'<td class="rowfollow"><input type="file" name="file" size="60"></td></tr>'."\n"
            .'<tr><td colspan="2" align="left" class="toolbox">'
            .'<input class="checkbox" type="checkbox" name="public" value="yes"> '
            .'Share this avatar with other users '
            .'<input type="submit" value="Upload">'
            .'</td></tr>'."\n"
            .'</table>'."\n"
            .'</form>'."\n";

        return new Response($this->wrap('Avatar upload', $body));
    }

    /**
     * Mirror the legacy scaling rule:
     *   - if both dimensions already fit, scale = 1 (no resize),
     *   - otherwise, scale by the larger of the two ratios so the
     *     output fits within the box.
     *
     * Returns floored integer dimensions (legacy used `floor()`).
     *
     * @return array{0:int,1:int}
     */
    private function resampleDimensions(int $width, int $height): array
    {
        $hScale = $height / self::SCALE_HEIGHT;
        $wScale = $width / self::SCALE_WIDTH;
        $scale = ($hScale < 1 && $wScale < 1) ? 1.0 : max($hScale, $wScale);
        $newWidth = (int) floor($width / $scale);
        $newHeight = (int) floor($height / $scale);

        return [$newWidth, $newHeight];
    }

    private function isSafeBasename(string $name): bool
    {
        if ($name === '') {
            return false;
        }
        $basename = pathinfo($name, PATHINFO_BASENAME);
        if ($basename !== $name) {
            return false;
        }

        return ! str_contains($name, '/')
            && ! str_contains($name, '\\')
            && ! str_contains($name, "\0");
    }

    private function bucketDirName(): string
    {
        $value = (string) (Setting::get('main.bitbucket') ?? 'bitbucket');

        return $value === '' ? 'bitbucket' : trim($value, '/');
    }

    private function bucketWritable(): bool
    {
        $path = public_path($this->bucketDirName());

        return is_dir($path) && is_writable($path);
    }

    private function publicUrl(string $bucketDir, string $filename): string
    {
        $base = (string) get_setting('main.baseurl');
        $base = $base === '' ? '' : rtrim($base, '/');
        $protocol = function_exists('get_protocol_prefix') ? (string) get_protocol_prefix() : '';
        $rawUrl = ($protocol === '' ? '' : $protocol).$base.'/'.$bucketDir.'/'.$filename;

        return str_replace(' ', '%20', htmlspecialchars($rawUrl));
    }

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title);

        return <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}</body>
</html>
HTML;
    }
}
