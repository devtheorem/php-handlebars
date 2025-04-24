<?php

namespace DevTheorem\Handlebars\Test;

/**
 * @implements \Iterator<string, int>
 */
class TwoDimensionIterator implements \Iterator
{
    private int $position = 0;
    private int $x = 0;
    private int $y = 0;

    public function __construct(
        private readonly int $w,
        private readonly int $h,
    ) {}

    public function rewind(): void
    {
        $this->position = 0;
        $this->x = 0;
        $this->y = 0;
    }

    public function current(): int
    {
        return $this->x * $this->y;
    }

    public function key(): string
    {
        return $this->x . 'x' . $this->y;
    }

    public function next(): void
    {
        ++$this->position;
        $this->x = $this->position % $this->w;
        $this->y = (int) floor($this->position / $this->w);
    }

    public function valid(): bool
    {
        return $this->position < $this->w * $this->h;
    }
}
