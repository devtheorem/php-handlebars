<?php

namespace DevTheorem\Handlebars;

use Closure;

/**
 * @internal
 */
final class Context
{
    /**
     * @param array<string, string> $usedPartial
     * @param list<string> $partialStack
     * @param array<string, string> $partialCode
     * @param array<string, true> $usedHelpers
     * @param array<string, string> $partials
     * @param null|Closure(Context, string):(string|null) $partialResolver
     * @param array<mixed> $partialBlock
     * @param array<mixed> $inlinePartial
     * @param array<string, callable> $helpers
     * @param null|Closure(Context, string):(Closure|null) $helperResolver
     */
    public function __construct(
        public readonly Options $options,
        public array $usedPartial = [],
        public array $partialStack = [],
        public array $partialCode = [],
        public int $usedDynPartial = 0,
        public int $usedPBlock = 0,
        public int $partialBlockId = 0,
        public array $usedHelpers = [],
        public array $partials = [],
        public ?Closure $partialResolver = null,
        public array $partialBlock = [],
        public array $inlinePartial = [],
        public array $helpers = [],
        public ?Closure $helperResolver = null,
    ) {
        $this->partials = $options->partials;
        $this->partialResolver = $options->partialResolver;
        $this->helpers = $options->helpers;
        $this->helperResolver = $options->helperResolver;
    }

    /**
     * Update from another context.
     */
    public function merge(Context $context): void
    {
        $this->helpers = $context->helpers;
        $this->partials = $context->partials;
        $this->partialCode = $context->partialCode;
        $this->partialStack = $context->partialStack;
        $this->usedHelpers = $context->usedHelpers;
        $this->usedDynPartial = $context->usedDynPartial;
        $this->usedPBlock = $context->usedPBlock;
        $this->partialBlockId = $context->partialBlockId;
        $this->usedPartial = $context->usedPartial;
    }
}
