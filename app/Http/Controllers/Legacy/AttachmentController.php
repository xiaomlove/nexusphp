<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Attachment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Attachment\Storage;
use Nexus\Database\NexusDB;
use Throwable;

/**
 * Replacement for `public/attachment.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * The "attach a file to a post" iframe widget. The compose helper
 * in `include/functions.php:1026` opens this URL in an iframe;
 * after a successful upload the iframe writes back to the parent
 * window via:
 *
 *   parent.tag_extimage('[attach]<dlkey>[/attach]')
 *
 * (or `parent.<callback_func>(<dlkey>, <url>)` for custom-field
 * preview helpers, which is the alternative shape the legacy
 * script accepted via `?callback_func=preview_custom_field_image_<id>`).
 *
 * Original legacy flow (`public/attachment.php`, ~292 LOC):
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `new ATTACHMENT($CURUSER['id'])` — quotas (`$count_limit`,
 *      `$count_left`, `$size_limit`, `$allowed_exts`).
 *   3. `if ($Attach->enable_attachment())`:
 *      - GET (or POST fall-through): render the upload `<form>`
 *        rendered inside a chrome-less `<html class="inframe">`
 *        page, alongside the quota readout
 *        (`<count_left> of <count_limit>`, `<size_limit>`, allowed
 *        extensions).
 *      - POST `$_FILES['file']`: validate (non-empty, count quota,
 *        size quota, extension-allow-list, banned-ext-list), do
 *        GD-resampling for images (optional thumbnail + watermark),
 *        write to disk OR delegate to remote `Storage` driver,
 *        INSERT row into `attachments`, echo
 *        `<script>parent.tag_extimage(...)</script>` to drive the
 *        compose helper.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - Attachments globally disabled (`$Attach->enable_attachment()
 *     === false`) → 200 with an empty `<table>` body, mirroring
 *     legacy parity (the legacy script left the form unrendered).
 *   - GET → 200 chrome-less iframe HTML with the upload `<form>`.
 *     The form respects `?callback_func=<name>` — the same value
 *     is round-tripped on POST so the JS handler picks the right
 *     callback (default: `tag_extimage`).
 *   - POST happy path → 200 with a `<script>parent.<callback>(...)
 *     </script>` body and the same form rendered after, so the
 *     iframe self-refreshes its quota readout.
 *   - POST validation failure → 200 with the form rendered + an
 *     inline `<span class="striking">…</span>` warning. Legacy
 *     parity — the legacy script never used 4xx for upload
 *     validation; the form-rendering branch always runs.
 *   - All side effects of the legacy upload pipeline are preserved
 *     verbatim: GD thumbnail / watermark, image-vs-non-image
 *     branching, remote `Storage` driver fallback, `attachments`
 *     INSERT, and the `parent.<callback>(...)` compose-helper
 *     hand-off.
 *
 * URL preserved exactly so the iframe `<a target="iframe" …>` /
 * `window.open(...)` callers in `include/functions.php:1026`,
 * `nexus/Field/Field.php` (custom-field image-preview helper),
 * and any user-side compose-form templates keep working without
 * template / JS changes.
 *
 * Output chrome: `<html class="inframe">` (no site-wide chrome —
 * this page is always rendered inside an iframe). The
 * `theme.css` link mirrors the legacy file byte-for-byte so the
 * iframe's visual style matches the parent compose form.
 *
 * Localisation: legacy `lang/<locale>/lang_attachment.php` (19
 * locales) loaded through `require_once
 * get_langfile_path('attachment')`. The 19 dictionaries are NOT
 * deleted in this PR; porting them to Laravel translations is
 * deferred to Phase 5.
 *
 * CSRF: the legacy iframe `<form enctype="multipart/form-data">`
 * had no `@csrf` field. The route is therefore listed in
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 */
class AttachmentController extends Controller
{
    /**
     * Hard cap from the legacy script (`$filesize >= 5242880`):
     * never accept a file larger than 5 MB regardless of the
     * configured per-class quota.
     */
    private const HARD_LIMIT_BYTES = 5_242_880;

    /**
     * Extensions the legacy script blocked unconditionally
     * (`$banned_ext`), even when present in the per-class
     * allow-list.
     */
    private const BANNED_EXTENSIONS = ['exe', 'com', 'bat', 'msi'];

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            // Belt-and-braces; the auth middleware redirects guests.
            abort(401);
        }

        require_once get_langfile_path('attachment');

        // Bring the legacy ATTACHMENT class into scope. PSR-4
        // autoload would normally find it via the `classes/`
        // classmap, but the legacy bootstrap path uses a defensive
        // `include_once` here for parity.
        require_once base_path('classes/class_attachment.php');

        /** @var \ATTACHMENT $attach */
        $attach = new \ATTACHMENT((int) $viewer->id);

        $callbackFunc = (string) $request->input('callback_func', '');
        $altsize = (string) $request->input('altsize', '');
        $warning = '';

        if (! $attach->enable_attachment()) {
            // Legacy parity: render an empty body with no form.
            return $this->envelope('');
        }

        if ($request->isMethod('POST')) {
            $warning = $this->processUpload(
                $request,
                $attach,
                (int) $viewer->id,
                $callbackFunc,
                $altsize,
            );
        }

        $body = $this->renderForm($attach, $callbackFunc, $altsize, $warning);

        return $this->envelope($body);
    }

    /**
     * Run the legacy upload pipeline. Returns the warning string to
     * show in the form (empty string on success — the success path
     * has already echoed the `parent.<callback>(...)` JS to drive
     * the compose helper).
     */
    private function processUpload(
        Request $request,
        \ATTACHMENT $attach,
        int $userId,
        string $callbackFunc,
        string $altsize,
    ): string {
        global $lang_attachment;
        global $savedirectorytype_attachment, $savedirectory_attachment, $httpdirectory_attachment;
        global $thumbnailtype_attachment, $thumbwidth_attachment, $thumbheight_attachment;
        global $altthumbwidth_attachment, $altthumbheight_attachment, $thumbquality_attachment;
        global $watermarkpos_attachment, $watermarkwidth_attachment, $watermarkheight_attachment;
        global $watermarkquality_attachment;

        $file = $request->file('file');
        if ($file === null) {
            return $this->langOr('text_nothing_received', 'Nothing received.');
        }

        $origfilename = (string) $file->getClientOriginalName();
        $filesize = (int) $file->getSize();
        $filetype = (string) $file->getMimeType();
        $tmpName = (string) $file->getRealPath();

        if ($filesize === 0 || $origfilename === '') {
            return $this->langOr('text_nothing_received', 'Nothing received.');
        }

        $countLeft = (int) $attach->get_count_left();
        if ($countLeft <= 0) {
            return $this->langOr('text_file_number_limit_reached', 'Attachment count limit reached.');
        }

        $sizeLimit = (int) $attach->get_size_limit_byte();
        if ($filesize > $sizeLimit || $filesize >= self::HARD_LIMIT_BYTES) {
            return $this->langOr('text_file_size_too_big', 'File too large.');
        }

        $ext = $this->extractExtension($origfilename);
        $allowedExts = (array) $attach->get_allowed_ext();
        if (! in_array($ext, $allowedExts, true) || in_array($ext, self::BANNED_EXTENSIONS, true)) {
            return $this->langOr('text_file_extension_not_allowed', 'File extension not allowed.');
        }

        $isImage = in_array($ext, Attachment::IMG_EXTENSIONS, true);
        $imageSize = $isImage ? @getimagesize($tmpName) : false;
        $width = is_array($imageSize) ? (int) ($imageSize[0] ?? 0) : 0;
        $height = is_array($imageSize) ? (int) ($imageSize[1] ?? 0) : 0;

        // Decide the storage path.
        $storageDriver = (string) get_setting('image_hosting.driver', 'local');
        $useLocal = ($storageDriver === 'local' || ! $isImage);

        $location = '';
        $abandonOriginal = false;
        $hasThumb = false;

        if ($useLocal) {
            $savePath = $this->buildSavePath((string) $savedirectorytype_attachment);
            $filemd5 = md5_file($tmpName);
            $filename = date('YmdHis').$filemd5;
            $fileLocation = make_folder((string) $savedirectory_attachment.'/', $savePath).$filename;
            do_log("file_location: $fileLocation");
            $location = $savePath.$filename.'.'.$ext;

            // Image processing — thumbnail + watermark.
            if ($isImage && is_array($imageSize)) {
                $imagetype = (int) ($imageSize[2] ?? 0);
                $isAnimatedGif = ($imagetype === IMAGETYPE_GIF) && $attach->is_gif_ani($tmpName);

                if (! $isAnimatedGif) {
                    [$ext, $filetype, $width, $height, $abandonOriginal, $hasThumb, $location] = $this->maybeResampleImage(
                        $tmpName,
                        $fileLocation,
                        $ext,
                        $filetype,
                        $width,
                        $height,
                        $imagetype,
                        $altsize === 'yes',
                        (string) $thumbnailtype_attachment,
                        (int) $thumbwidth_attachment,
                        (int) $thumbheight_attachment,
                        (int) $altthumbwidth_attachment,
                        (int) $altthumbheight_attachment,
                        (int) $thumbquality_attachment,
                        (string) $watermarkpos_attachment,
                        (int) $watermarkwidth_attachment,
                        (int) $watermarkheight_attachment,
                        (int) $watermarkquality_attachment,
                        $savePath,
                        $filename,
                    );
                    if ($abandonOriginal) {
                        $filesize = (int) (@filesize($fileLocation.'.'.$ext) ?: $filesize);
                    }
                }
            } elseif ($isImage && ! is_array($imageSize)) {
                return $this->langOr('text_invalid_image_file', 'Invalid image file.');
            }

            if (! $abandonOriginal) {
                if (! @move_uploaded_file($tmpName, $fileLocation.'.'.$ext)
                    && ! @rename($tmpName, $fileLocation.'.'.$ext)
                    && ! @copy($tmpName, $fileLocation.'.'.$ext)
                ) {
                    return $this->langOr('text_cannot_move_file', 'Could not save uploaded file.');
                }
            }

            $url = (string) $httpdirectory_attachment.'/'.$location;
            if ($hasThumb) {
                $url .= '.thumb.jpg';
            }
        } else {
            // Remote storage driver.
            try {
                $driver = Storage::getDriver();
                $location = (string) $driver->uploadGetLocation($tmpName, $origfilename);
                do_log("location: $location");
                $url = (string) $driver->getImageUrl($location);
            } catch (Exception|Throwable $exception) {
                do_log('upload failed: '.$exception->getMessage().$exception->getTraceAsString(), 'error');

                return (string) $exception->getMessage();
            }
        }

        // Persist + drive parent compose helper.
        $dlkey = md5($location.microtime(true));
        NexusDB::insert('attachments', [
            'userid' => $userId,
            'width' => $width,
            'added' => date('Y-m-d H:i:s'),
            'filename' => $origfilename,
            'filetype' => $filetype,
            'filesize' => $filesize,
            'location' => $location,
            'dlkey' => $dlkey,
            'isimage' => $isImage ? 1 : 0,
            'thumb' => $hasThumb ? 1 : 0,
            'driver' => $storageDriver,
        ]);

        // Echo the parent-window callback. Custom-field image
        // previewers (`preview_custom_field_image_<n>`) take
        // `(dlkey, url)`; the default compose helper takes the
        // bbcode token.
        if ($callbackFunc !== '' && preg_match('/^preview_custom_field_image_\d+$/', $callbackFunc) === 1) {
            echo sprintf(
                '<script type="text/javascript">parent.%s("%s", "%s")</script>',
                $callbackFunc,
                $dlkey,
                $url,
            );
        } else {
            echo '<script type="text/javascript">parent.tag_extimage(\'[attach]'.$dlkey.'[/attach]\');</script>';
        }

        return '';
    }

    /**
     * @return array{0:string,1:string,2:int,3:int,4:bool,5:bool,6:string}
     *                                                                     `[ext, mime, width, height, abandonOriginal, hasThumb, location]`
     */
    private function maybeResampleImage(
        string $tmpName,
        string $fileLocation,
        string $ext,
        string $filetype,
        int $width,
        int $height,
        int $imagetype,
        bool $altsize,
        string $thumbnailType,
        int $thumbWidth,
        int $thumbHeight,
        int $altThumbWidth,
        int $altThumbHeight,
        int $thumbQuality,
        string $watermarkPos,
        int $watermarkMinWidth,
        int $watermarkMinHeight,
        int $watermarkQuality,
        string $savePath,
        string $filename,
    ): array {
        $abandonOriginal = false;
        $hasThumb = false;
        $maybeCreateThumb = false;
        $thumb = null;

        // Thumbnail.
        if ($thumbnailType !== 'no') {
            $targetWidth = $altsize ? $altThumbWidth : $thumbWidth;
            $targetHeight = $altsize ? $altThumbHeight : $thumbHeight;
            if ($targetHeight <= 0 || $targetWidth <= 0) {
                $scale = 1;
            } else {
                $hScale = $height / $targetHeight;
                $wScale = $width / $targetWidth;
                $scale = ($hScale < 1 && $wScale < 1) ? 1 : (($hScale > $wScale) ? $hScale : $wScale);
            }
            if ($scale > 1) {
                $newwidth = (int) floor($width / $scale);
                $newheight = (int) floor($height / $scale);
                $orig = $this->createImage($tmpName, $imagetype);
                if ($orig !== null) {
                    $thumb = imagecreatetruecolor($newwidth, $newheight);
                    if ($thumb !== false) {
                        imagecopyresampled($thumb, $orig, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
                        if ($thumbnailType === 'createthumb') {
                            $hasThumb = true;
                            imagejpeg($thumb, $fileLocation.'.'.$ext.'.thumb.jpg', $thumbQuality);
                        } elseif ($thumbnailType === 'resizebigimg') {
                            $ext = 'jpg';
                            $filetype = 'image/jpeg';
                            $imagetype = IMAGETYPE_JPEG;
                            $width = $newwidth;
                            $height = $newheight;
                            $maybeCreateThumb = true;
                            $abandonOriginal = true;
                        }
                    }
                }
            }
        }

        // Watermark.
        $watermarkPath = base_path('public/pic/watermark.png');
        if ($watermarkPos !== 'no'
            && $width > $watermarkMinWidth
            && $height > $watermarkMinHeight
            && is_file($watermarkPath)
        ) {
            $resource = $abandonOriginal && $thumb !== null
                ? $thumb
                : (function () use ($tmpName, $imagetype, $width, $height) {
                    $blank = imagecreatetruecolor($width, $height);
                    $orig = $this->createImage($tmpName, $imagetype);
                    if ($blank !== false && $orig !== null) {
                        imagecopy($blank, $orig, 0, 0, 0, 0, $width, $height);
                    }

                    return $blank;
                })();

            $watermark = @imagecreatefrompng($watermarkPath);
            if ($resource !== false && $watermark !== false) {
                $wmw = imagesx($watermark);
                $wmh = imagesy($watermark);
                [$wmx, $wmy] = $this->resolveWatermarkPosition(
                    $watermarkPos,
                    $width,
                    $height,
                    $wmw,
                    $wmh,
                );
                imagecopy($resource, $watermark, $wmx, $wmy, 0, 0, $wmw, $wmh);
                if ($imagetype === IMAGETYPE_GIF) {
                    imagegif($resource, $fileLocation.'.'.$ext);
                } elseif ($imagetype === IMAGETYPE_JPEG) {
                    imagejpeg($resource, $fileLocation.'.'.$ext, $watermarkQuality);
                } else {
                    imagepng($resource, $fileLocation.'.'.$ext);
                }
                $maybeCreateThumb = false;
                $abandonOriginal = true;
            }
        }

        // Late thumbnail (only fires if no watermark was added).
        if ($maybeCreateThumb && $thumb !== null) {
            imagejpeg($thumb, $fileLocation.'.'.$ext, $thumbQuality);
        }

        $location = $savePath.$filename.'.'.$ext;

        return [$ext, $filetype, $width, $height, $abandonOriginal, $hasThumb, $location];
    }

    private function createImage(string $tmpName, int $imagetype): ?\GdImage
    {
        $resource = match ($imagetype) {
            IMAGETYPE_GIF => @imagecreatefromgif($tmpName),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmpName),
            IMAGETYPE_PNG => @imagecreatefrompng($tmpName),
            default => false,
        };

        return $resource === false ? null : $resource;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveWatermarkPosition(
        string $pos,
        int $width,
        int $height,
        int $wmw,
        int $wmh,
    ): array {
        $resolved = $pos === 'random' ? (string) mt_rand(1, 9) : $pos;
        $cx = (int) (($width - $wmw) / 2);
        $cy = (int) (($height - $wmh) / 2);
        $rx = $width - $wmw - 5;
        $ry = $height - $wmh - 5;

        return match ($resolved) {
            '1' => [5, 5],
            '2' => [$cx, 5],
            '3' => [$rx, 5],
            '4' => [5, $cy],
            '5' => [$cx, $cy],
            '6' => [$rx, $cy],
            '7' => [5, $ry],
            '8' => [$cx, $ry],
            '9' => [$rx, $ry],
            default => [5, 5],
        };
    }

    private function buildSavePath(string $type): string
    {
        return match ($type) {
            'monthdir' => date('Ym').'/',
            'daydir' => date('Ymd').'/',
            default => '',
        };
    }

    private function extractExtension(string $filename): string
    {
        $dot = strrpos($filename, '.');
        if ($dot === false) {
            return '';
        }

        return strtolower(substr($filename, $dot + 1));
    }

    /**
     * @param  array<int,string>  $allowedExts
     */
    private function renderForm(
        \ATTACHMENT $attach,
        string $callbackFunc,
        string $altsize,
        string $warning,
    ): string {
        global $lang_attachment;

        $countLeft = (int) $attach->get_count_left();
        $countLimit = (int) $attach->get_count_limit();
        $sizeLimit = (int) $attach->get_size_limit_byte();
        $allowedExts = (array) $attach->get_allowed_ext();

        $smallThumb = htmlspecialchars($this->langOr('text_small_thumbnail', 'Small thumbnail'));
        $submit = htmlspecialchars($this->langOr('submit_upload', 'Upload'));
        $textLeft = htmlspecialchars($this->langOr('text_left', 'Left:'));
        $textOf = htmlspecialchars($this->langOr('text_of', ' of '));
        $textSizeLimit = htmlspecialchars($this->langOr('text_size_limit', 'Size limit:'));
        $textExtensions = htmlspecialchars($this->langOr('text_file_extensions', 'Allowed extensions:'));
        $textMouseOver = htmlspecialchars($this->langOr('text_mouse_over_here', '(mouse over here)'));

        $action = '/attachment.php?callback_func='.urlencode($callbackFunc);
        $disabled = $countLeft > 0 ? '' : ' disabled="disabled"';
        $altChecked = $altsize === 'yes' ? ' checked="checked"' : '';
        $allowedExtsTitle = htmlspecialchars(implode('/', $allowedExts) ?: 'N/A');

        $body = '<table width="100%">';
        $body .= '<form enctype="multipart/form-data" name="attachment" method="post" action="'.$action.'">';
        $body .= '<tr><td class="embedded" colspan="2" align=left>';
        $body .= '<input type="file" name="file"'.$disabled.' />&nbsp;';
        $body .= '<input type="checkbox" name="altsize" value="yes"'.$altChecked.' />'.$smallThumb.'&nbsp;';
        $body .= '<input type="submit" name="submit" value="'.$submit.'"'.$disabled.' /> ';

        if ($warning !== '') {
            $body .= '<span class="striking">'.htmlspecialchars($warning).'</span>';
        } else {
            $body .= '<b>'.$textLeft.'</b><font color="red">'.$countLeft.'</font>'.$textOf.$countLimit;
            $body .= '&nbsp;&nbsp;&nbsp;<b>'.$textSizeLimit.'</b>'.mksize($sizeLimit);
            $body .= '&nbsp;&nbsp;&nbsp;<b>'.$textExtensions.'</b>';
            $body .= '<span title="'.$allowedExtsTitle.'"><i>'.$textMouseOver.'</i></span>';
        }
        $body .= '</td></tr></form></table>';

        return $body;
    }

    private function langOr(string $key, string $fallback): string
    {
        global $lang_attachment;

        return (string) ($lang_attachment[$key] ?? $fallback);
    }

    /**
     * Iframe-style envelope: no global chrome, but the legacy
     * `<link rel="stylesheet" href="…/theme.css">` is preserved
     * so the iframe's visual style matches the parent compose
     * form. The `inframe` body class signals to `theme.css` that
     * it should drop the page-level chrome.
     */
    private function envelope(string $body): Response
    {
        $cssUri = (string) get_css_uri();
        $fontCssUri = (string) get_font_css_uri();
        $fontCssEsc = htmlspecialchars($fontCssUri);
        $themeCssEsc = htmlspecialchars($cssUri.'theme.css');

        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="{$fontCssEsc}" type="text/css">
<link rel="stylesheet" href="{$themeCssEsc}" type="text/css">
</head>
<body class="inframe">
{$body}
</body>
</html>
HTML;

        return new Response($html);
    }
}
