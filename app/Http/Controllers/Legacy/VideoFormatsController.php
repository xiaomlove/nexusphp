<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Replacement for `public/videoformats.php` (deleted in the same
 * PR). Static glossary of video-rip release tags (CAM / TS / TC
 * / SCR / DVDRip / TVRip / WP / NUKED / DUPE / ...). Same shape
 * as `FormatsController`: the body has zero dynamic content, so
 * we render `resources/views/legacy/videoformats.blade.php`
 * verbatim inside a chrome-less HTML envelope.
 */
class VideoFormatsController extends Controller
{
    public function __invoke(): Response
    {
        $body = view('legacy.videoformats')->render();

        $html = <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>NexusPHP :: Video Formats</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }
}
