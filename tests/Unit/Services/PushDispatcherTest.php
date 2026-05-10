<?php

namespace Tests\Unit\Services;

use App\Services\PushDispatcher;
use Tests\TestCase;

class PushDispatcherTest extends TestCase
{
    public function test_is_not_configured_when_vapid_keys_missing(): void
    {
        config([
            'nexus.webpush.vapid_public_key' => '',
            'nexus.webpush.vapid_private_key' => '',
        ]);

        $dispatcher = new PushDispatcher;

        $this->assertFalse($dispatcher->isConfigured());
    }

    public function test_is_configured_when_both_vapid_keys_present(): void
    {
        config([
            'nexus.webpush.vapid_public_key' => 'pub-key',
            'nexus.webpush.vapid_private_key' => 'priv-key',
        ]);

        $dispatcher = new PushDispatcher;

        $this->assertTrue($dispatcher->isConfigured());
    }

    public function test_send_to_user_returns_zero_when_unconfigured(): void
    {
        config([
            'nexus.webpush.vapid_public_key' => '',
            'nexus.webpush.vapid_private_key' => '',
        ]);

        $dispatcher = new PushDispatcher;

        $this->assertSame(0, $dispatcher->sendToUser(1, 'Subj', 'Body'));
        $this->assertSame(0, $dispatcher->sendToUsers([1, 2], 'Subj', 'Body'));
    }
}
