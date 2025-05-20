<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function testStripExtendedComments(): void
    {
        $this->assertSame('abc', Validator::stripExtendedComments('abc'));
        $this->assertSame('abc{{!}}cde', Validator::stripExtendedComments('abc{{!}}cde'));
        $this->assertSame('abc{{! }}cde', Validator::stripExtendedComments('abc{{!----}}cde'));
    }
}
