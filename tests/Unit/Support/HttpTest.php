<?php

namespace Tests\Unit\Support;

use App\Support\Http;
use PHPUnit\Framework\TestCase;

class HttpTest extends TestCase
{
    public function test_content_disposition_default_is_attachment_with_ascii_filename(): void
    {
        $value = Http::contentDisposition('Ubuntu-24.04.iso.torrent');
        $this->assertStringStartsWith('attachment;', $value);
        $this->assertStringContainsString('filename=Ubuntu-24.04.iso.torrent', $value);
    }

    public function test_content_disposition_honours_inline_disposition(): void
    {
        $value = Http::contentDisposition('preview.png', 'inline');
        $this->assertStringStartsWith('inline;', $value);
    }

    public function test_content_disposition_emits_utf8_extended_filename(): void
    {
        // Symfony's HeaderUtils encodes non-ASCII filenames into
        // RFC 5987 `filename*=UTF-8''<percent-encoded>` form, and
        // strips `%` from the ASCII fallback. Pinned because the
        // legacy contract depends on that exact double-emission so
        // both modern and old user-agents resolve the filename.
        $value = Http::contentDisposition('релиз.torrent');
        $this->assertStringContainsString("filename*=utf-8''", strtolower($value));
        $this->assertStringContainsString('.torrent', $value);
    }

    public function test_content_disposition_strips_percent_from_ascii_fallback(): void
    {
        // The fallback path runs `str_replace('%', '', Str::ascii(...))`
        // — a leftover `%` would otherwise collide with RFC 5987
        // percent-encoding in the ext-name field.
        $value = Http::contentDisposition('100%off.torrent');
        $this->assertStringNotContainsString('100%off', $value);
        $this->assertStringContainsString('100off', $value);
    }
}
