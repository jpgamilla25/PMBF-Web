<?php

namespace Tests\Unit;

use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

class PinRulesTest extends TestCase
{
    private AuthService $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = new AuthService();
    }

    public function test_it_rejects_repeated_digits(): void
    {
        foreach (['0000', '1111', '7777', '9999'] as $pin) {
            $this->assertTrue($this->auth->isWeakPin($pin), "{$pin} should be rejected");
        }
    }

    public function test_it_rejects_sequential_digits(): void
    {
        foreach (['1234', '2345', '6789', '4321', '9876'] as $pin) {
            $this->assertTrue($this->auth->isWeakPin($pin), "{$pin} should be rejected");
        }
    }

    public function test_it_allows_ordinary_pins(): void
    {
        foreach (['1357', '8024', '4915', '1123', '2468'] as $pin) {
            $this->assertFalse($this->auth->isWeakPin($pin), "{$pin} should be allowed");
        }
    }

    public function test_wrapping_sequences_are_not_treated_as_sequential(): void
    {
        // 9012 wraps past 9 back to 0, so the step is not a constant +1.
        $this->assertFalse($this->auth->isWeakPin('9012'));
    }
}
