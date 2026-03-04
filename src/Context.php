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
     * @param array<string, true> $usedHelpers
     * @param array<string, string> $partials
     * @param array<mixed> $partialBlock
     * @param array<mixed> $inlinePartial
     * @param array<string, callable> $helpers
     */
    public function __construct(
        public readonly Options $options,
        public array $usedPartial = [],
        public array $partialCode = [],
        public int $usedDynPartial = 0,
        public int $usedPBlock = 0,
        public int $partialBlockId = 0,
        public array $usedHelpers = [],
        public array $partials = [],
        public array $partialBlock = [],
        public array $inlinePartial = [],
        public array $helpers = [],
    ) {
        $this->partials = $options->partials;
        $this->helpers = $options->helpers;
    }

    /**
     * Update from another context.
     */
    public function merge(self $context): void
    {
        $this->helpers = $context->helpers;
        $this->partials = $context->partials;
        $this->partialCode = $context->partialCode;
        $this->usedHelpers = $context->usedHelpers;
        $this->usedDynPartial = $context->usedDynPartial;
        $this->usedPBlock = $context->usedPBlock;
        $this->partialBlockId = $context->partialBlockId;
        $this->usedPartial = $context->usedPartial;
    }
}
