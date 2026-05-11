<?php

namespace Tests\Unit\Support;

use App\Support\MentionExtractor;
use PHPUnit\Framework\TestCase;

class MentionExtractorTest extends TestCase
{
    public function test_empty_input_returns_empty_array(): void
    {
        $this->assertSame([], MentionExtractor::usernamesFromText(null));
        $this->assertSame([], MentionExtractor::usernamesFromText(''));
    }

    public function test_extracts_mentions_in_source_order_uniquely(): void
    {
        $names = MentionExtractor::usernamesFromText('hi @alice and @bob, also @ALICE again');
        $this->assertSame(['alice', 'bob'], $names);
    }

    public function test_skips_mentions_inside_code_blocks(): void
    {
        $text = 'outside @alice [code]inside @bob[/code] tail @charlie';
        $names = MentionExtractor::usernamesFromText($text);
        $this->assertContains('alice', $names);
        $this->assertContains('charlie', $names);
        $this->assertNotContains('bob', $names);
    }

    public function test_does_not_match_email_or_url_fragments(): void
    {
        $text = 'mailto: user@example.com see https://example.com/?q=@trick mention @real';
        $names = MentionExtractor::usernamesFromText($text);
        $this->assertSame(['real'], $names);
    }

    public function test_minimum_length_two_characters(): void
    {
        // single-char @ should not match
        $names = MentionExtractor::usernamesFromText('hey @a, also @ab');
        $this->assertSame(['ab'], $names);
    }

    public function test_allowed_username_characters(): void
    {
        $names = MentionExtractor::usernamesFromText('@user.name @user-name @user_name');
        $this->assertSame(['user.name', 'user-name', 'user_name'], $names);
    }
}
