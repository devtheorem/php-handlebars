<?php

namespace DevTheorem\Handlebars;

/**
 * @internal
 */
final class RuntimeContext
{
    /**
     * @param array<string, \Closure> $helpers
     * @param array<string, \Closure> $partials
     * @param array<mixed> $depths
     * @param array<mixed> $data
     * @param array<mixed> $frame
     */
    public function __construct(
        public array $helpers = [],
        public array $partials = [],
        public array $depths = [],
        public array $data = [],
        public array $frame = [],
        public ?\Closure $partialBlock = null,
    ) {}
}
