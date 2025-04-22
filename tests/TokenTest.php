<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Token;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function testToString(): void
    {
        $this->assertSame('c', Token::toString(['0', 'a', 'b', 'c', 'd', 'e']));
        $this->assertSame('cd', Token::toString(['0', 'a', 'b', 'c', 'd', 'e', 'f']));
        $this->assertSame('qd', Token::toString(['0', 'a', 'b', 'c', 'd', 'e', 'f'], [3 => 'q']));
    }
}
