<?php

namespace DevTheorem\Handlebars\Test;

/**
 * @implements \IteratorAggregate<string, int>
 */
class TwoDimensionIterator implements \IteratorAggregate
{
    public function __construct(
        private readonly int $w,
        private readonly int $h,
    ) {}

    /** @return \Generator<string, int> */
    public function getIterator(): \Generator
    {
        for ($pos = 0; $pos < $this->w * $this->h; $pos++) {
            $x = $pos % $this->w;
            $y = (int) floor($pos / $this->w);
            yield $x . 'x' . $y => $x * $y;
        }
    }
}
