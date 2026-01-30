<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Exporter;
use PHPUnit\Framework\TestCase;

class ExporterTest extends TestCase
{
    public function testClosure(): void
    {
        $expected = 'function ($a) {
                return 1 + $a;
            }';
        $this->assertSame($expected, Exporter::closure(
            function ($a) {
                return 1 + $a;
            },
        ));

        $this->assertSame('fn() => 1 + 1', Exporter::closure(fn() => 1 + 1));
        $this->assertSame('fn(int $a) => $a * 2', Exporter::closure(
            fn(int $a) => $a * 2,
        ));
    }
}
