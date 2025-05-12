<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\SafeString;
use PHPUnit\Framework\TestCase;

class SafeStringTest extends TestCase
{
    public function testStripExtendedComments(): void
    {
        $this->assertSame('abc', SafeString::stripExtendedComments('abc'));
        $this->assertSame('abc{{!}}cde', SafeString::stripExtendedComments('abc{{!}}cde'));
        $this->assertSame('abc{{! }}cde', SafeString::stripExtendedComments('abc{{!----}}cde'));
    }
}
