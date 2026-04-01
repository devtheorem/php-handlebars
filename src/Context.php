<?php

namespace DevTheorem\Handlebars;

/**
 * @internal
 */
final class Context
{
    /**
     * @param array<string, string> $usedPartial
     * @param array<string, string> $partialCode
     */
    public function __construct(
        public readonly Options $options,
        public array $usedPartial = [],
        public array $partialCode = [],
        public int $usedDynPartial = 0,
    ) {}
}
