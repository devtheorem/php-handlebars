<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Handlebars;
use PHPUnit\Framework\TestCase;

class HandlebarsTest extends TestCase
{
    public function testEscapeExpression(): void
    {
        $this->assertSame('a&amp;&#x27;b', Handlebars::escapeExpression("a&'b"));
        $this->assertSame('&lt;&gt;&quot;', Handlebars::escapeExpression('<>"'));
        $this->assertSame('&#x60;a&#x3D;b', Handlebars::escapeExpression('`a=b'));
    }
}
