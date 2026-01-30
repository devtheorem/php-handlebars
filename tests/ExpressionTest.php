<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Expression;
use PHPUnit\Framework\TestCase;

class ExpressionTest extends TestCase
{
    public function testListString(): void
    {
        $this->assertSame('[]', Expression::listString([]));
        $this->assertSame("['a']", Expression::listString(['a']));
        $this->assertSame("['a','b','c']", Expression::listString(['a', 'b', 'c']));
    }

    public function testArrayString(): void
    {
        $this->assertSame('', Expression::arrayString([]));
        $this->assertSame("['a']", Expression::arrayString(['a']));
        $this->assertSame("['a']['b']['c']", Expression::arrayString(['a', 'b', 'c']));
    }
}
