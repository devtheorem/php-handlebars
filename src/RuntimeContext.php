<?php

namespace DevTheorem\Handlebars;

/**
 * @internal
 */
final class RuntimeContext
{
    /**
     * @param array<string, \Closure> $helpers
     * @param array<string, \Closure> $partials compile-time and helper-registered partials (persistent)
     * @param array<string, \Closure> $inlinePartials block-scoped {{#* inline}} partials (reset on fn() return)
     * @param array<mixed> $depths
     * @param array<mixed> $data
     * @param array<mixed> $frame
     */
    public function __construct(
        public array $helpers = [],
        public array $partials = [],
        public array $inlinePartials = [],
        public array $depths = [],
        public array $data = [],
        public array $frame = [],
        public ?\Closure $partialBlock = null,
    ) {}
}
