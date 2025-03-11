<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public static function getPartialNameProvider(): array
    {
        return [
            [null, [], 0],
            [['foo'], ['foo'], 0],
            [['foo'], ['"foo"'], 0],
            [['foo'], ['[foo]'], 0],
            [['foo'], ["\\'foo\\'"], 0],
            [['foo'], [0, 'foo'], 1],
        ];
    }

    #[DataProvider('getPartialNameProvider')]
    public function testGetPartialName(?array $expected, array $vars, int $pos): void
    {
        $this->assertSame($expected, Parser::getPartialName($vars, $pos));
    }
}
