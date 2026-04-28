<?php

namespace DevTheorem\Handlebars;

use Closure;

/**
 * @internal
 */
final class RuntimeContext
{
    /**
     * @param array<Closure> $helpers
     * @param array<Closure> $partials runtime-registered partials (persistent)
     * @param array<Closure> $inlinePartials block-scoped {{#* inline}} partials (reset on fn() return)
     * @param array<mixed> $depths
     * @param array<mixed> $data
     */
    public function __construct(
        public array $helpers = [],
        public array $partials = [],
        public ?Closure $partialResolver = null,
        public array $inlinePartials = [],
        public array $depths = [],
        public array $data = [],
    ) {}
}
