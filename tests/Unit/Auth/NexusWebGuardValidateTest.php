<?php

namespace Tests\Unit\Auth;

use App\Auth\NexusWebGuard;
use BadMethodCallException;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class NexusWebGuardValidateTest extends TestCase
{
    private function makeGuard(): NexusWebGuard
    {
        $provider = $this->createMock(UserProvider::class);

        return new NexusWebGuard(Request::create('/'), $provider);
    }

    public function test_validate_returns_false_when_credentials_are_empty(): void
    {
        $this->assertFalse($this->makeGuard()->validate([]));
    }

    public function test_validate_returns_false_when_secure_pass_cookie_is_empty(): void
    {
        $this->assertFalse($this->makeGuard()->validate(['c_secure_pass' => '']));
    }

    public function test_validate_returns_false_when_required_cookie_is_missing(): void
    {
        $this->assertFalse($this->makeGuard()->validate(['some_other_cookie' => 'foo']));
    }

    public function test_validate_returns_true_when_secure_pass_cookie_is_present(): void
    {
        $this->assertTrue($this->makeGuard()->validate(['c_secure_pass' => 'arbitrary-value']));
    }

    public function test_unimplemented_attempt_throws_bad_method_call(): void
    {
        $this->expectException(BadMethodCallException::class);

        $this->makeGuard()->attempt(['username' => 'foo']);
    }

    public function test_unimplemented_login_using_id_throws_bad_method_call(): void
    {
        $this->expectException(BadMethodCallException::class);

        $this->makeGuard()->loginUsingId(1);
    }

    public function test_unimplemented_via_remember_throws_bad_method_call(): void
    {
        $this->expectException(BadMethodCallException::class);

        $this->makeGuard()->viaRemember();
    }
}
