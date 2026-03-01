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
     * @param array<string, string> $partials
     * @param array<mixed> $partialBlock
     * @param array<mixed> $inlinePartial
     */
    public function __construct(
        public readonly Options $options,
        public array $usedPartial = [],
        public array $partialCode = [],
        public int $usedDynPartial = 0,
        public int $usedPBlock = 0,
        public int $partialBlockId = 0,
        public array $partials = [],
        public array $partialBlock = [],
        public array $inlinePartial = [],
    ) {
        $this->partials = $options->partials;
    }

    /**
     * Update from another context.
     */
    public function merge(self $context): void
    {
        $this->partials = $context->partials;
        $this->partialCode = $context->partialCode;
        $this->usedDynPartial = $context->usedDynPartial;
        $this->usedPBlock = $context->usedPBlock;
        $this->partialBlockId = $context->partialBlockId;
        $this->usedPartial = $context->usedPartial;
    }
}
