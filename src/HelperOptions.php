<?php

namespace DevTheorem\Handlebars;

class HelperOptions
{
    public function __construct(
        public readonly string $name,
        public readonly array $hash,
        public readonly \Closure $fn,
        public readonly \Closure $inverse,
        public readonly int $blockParams,
        public mixed &$scope,
        public array &$data,
    ) {}

    public function fn(...$args): string
    {
        return ($this->fn)(...$args);
    }

    public function inverse(...$args): string
    {
        return ($this->inverse)(...$args);
    }
}
