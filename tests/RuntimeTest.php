<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Runtime;
use PHPUnit\Framework\TestCase;

class RuntimeTest extends TestCase
{
    public function testIfVar(): void
    {
        $this->assertFalse(Runtime::ifvar(null));
        $this->assertFalse(Runtime::ifvar(0));
        $this->assertTrue(Runtime::ifvar(0, true));
        $this->assertFalse(Runtime::ifvar(false));
        $this->assertTrue(Runtime::ifvar(true));
        $this->assertTrue(Runtime::ifvar(1));
        $this->assertFalse(Runtime::ifvar(''));
        $this->assertTrue(Runtime::ifvar('0'));
        $this->assertFalse(Runtime::ifvar([]));
        $this->assertTrue(Runtime::ifvar(['']));
        $this->assertTrue(Runtime::ifvar([0]));
        $this->assertFalse(Runtime::ifvar(self::createStringable('')));
        $this->assertTrue(Runtime::ifvar(self::createStringable('0')));
    }

    public function testIsec(): void
    {
        $this->assertTrue(Runtime::isec(null));
        $this->assertFalse(Runtime::isec(0));
        $this->assertTrue(Runtime::isec(false));
        $this->assertFalse(Runtime::isec('false'));
        $this->assertTrue(Runtime::isec([]));
        $this->assertFalse(Runtime::isec(['1']));
    }

    private static function createStringable(string $value): \Stringable
    {
        return new class ($value) implements \Stringable {
            public function __construct(private string $value) {}

            public function __toString(): string
            {
                return $this->value;
            }
        };
    }
}
