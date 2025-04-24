<?php

namespace DevTheorem\Handlebars;

/**
 * @internal
 */
class RuntimeContext
{
    /**
     * @param array<string, callable> $helpers
     * @param array<string, string> $partials
     * @param array<mixed> $scopes
     * @param array<mixed> $spVars
     * @param array<mixed> $blParam
     */
    public function __construct(
        public array $helpers = [],
        public array $partials = [],
        public array $scopes = [],
        public array $spVars = [],
        public array $blParam = [],
        public int $partialId = 0,
    ) {}
}
