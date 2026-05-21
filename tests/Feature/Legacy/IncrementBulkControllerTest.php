<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/increment-bulk.php` form-render contract.
 *
 * SYSOP+ tool. Auth + class gate from {@see TakeStaffMessControllerTest},
 * envelope shape from {@see StaffMessControllerTest}. Happy-path
 * fixtures use `User::CLASS_STAFF_LEADER` so `user_can()`
 * short-circuits to `true`.
 */
class IncrementBulkControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/increment-bulk.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/increment-bulk.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_sysop_is_forbidden(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $this->get('/increment-bulk.php')->assertForbidden();
    }

    public function test_sysop_renders_form(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->get('/increment-bulk.php');
        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringContainsString(
            '<title>Batch add bonus/attendance card/invite/uploaded/temporary invite</title>',
            $body,
        );
        $this->assertStringContainsString(
            '<form method="post" action="take-increment-bulk.php">',
            $body,
        );
        $this->assertStringContainsString('name="type" value="seedbonus"', $body);
        $this->assertStringContainsString('name="type" value="attendance_card"', $body);
        $this->assertStringContainsString('name="type" value="invites"', $body);
        $this->assertStringContainsString('name="type" value="uploaded"', $body);
        $this->assertStringContainsString('name="type" value="tmp_invites"', $body);
        $this->assertStringContainsString('(GB)', $body);
        $this->assertStringContainsString('name="amount"', $body);
        $this->assertStringContainsString('name="duration"', $body);
        $this->assertStringContainsString('name="classes[]"', $body);
        $this->assertStringContainsString('name="subject"', $body);
        $this->assertStringContainsString('name="msg"', $body);
        $this->assertStringContainsString('name="sender" type="radio" value="self"', $body);
        $this->assertStringContainsString('name="sender" type="radio" value="system"', $body);
    }

    public function test_sent_banner_renders_with_known_type(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $body = (string) $this->get('/increment-bulk.php?sent=1&type=seedbonus')->getContent();

        $this->assertStringContainsString(
            'bonus has been added and inform message has been sent',
            $body,
        );
    }

    public function test_sent_banner_skipped_for_unknown_type(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $body = (string) $this->get('/increment-bulk.php?sent=1&type=bogus')->getContent();

        $this->assertStringNotContainsString(
            'has been added and inform message has been sent',
            $body,
        );
    }

    public function test_returnto_query_param_is_propagated_into_hidden_input(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $body = (string) $this->get('/increment-bulk.php?returnto=/staffpanel.php')->getContent();

        $this->assertStringContainsString(
            '<input type="hidden" name="returnto" value="/staffpanel.php">',
            $body,
        );
    }

    public function test_returnto_falls_back_to_referer_header(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $body = (string) $this
            ->withHeaders(['referer' => 'https://example.test/staffpanel.php'])
            ->get('/increment-bulk.php')
            ->getContent();

        $this->assertStringContainsString(
            '<input type="hidden" name="returnto" value="https://example.test/staffpanel.php">',
            $body,
        );
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }
}
