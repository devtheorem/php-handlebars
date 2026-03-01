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
     * @param array<mixed> $scopes
     * @param array<mixed> $data
     * @param array<mixed> $blParam
     */
    public function __construct(
        public array $helpers = [],
        public array $partials = [],
        public array $scopes = [],
        public array $data = [],
        public array $blParam = [],
        public int $partialId = 0,
        public int $partialDepth = 0,
    ) {}
}
