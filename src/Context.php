<?php

namespace DevTheorem\Handlebars;

use Closure;

/**
 * @internal
 */
final class Context
{
    /**
     * @param array<mixed> $stack
     * @param array<mixed>|null $currentToken
     * @param string[] $error
     * @param array<mixed> $elseLvl
     * @param array<string, string> $usedPartial
     * @param list<string> $partialStack
     * @param array<string, string> $partialCode
     * @param array<string, true> $usedHelpers
     * @param array<mixed> $parsed
     * @param array<string, string> $partials
     * @param null|Closure(Context, string):(string|null) $partialResolver
     * @param array<mixed> $partialBlock
     * @param array<mixed> $inlinePartial
     * @param array<string, callable> $helpers
     * @param null|Closure(Context, string):(Closure|null) $helperResolver
     */
    public function __construct(
        public readonly Options $options,
        public int $level = 0,
        public array $stack = [],
        public ?array $currentToken = null,
        public array $error = [],
        public array $elseLvl = [],
        public bool $elseChain = false,
        public string $tokenSearch = '',
        public string $partialIndent = '',
        public int|false $tokenAhead = false,
        public array $usedPartial = [],
        public array $partialStack = [],
        public array $partialCode = [],
        public int $usedDynPartial = 0,
        public int $usedPBlock = 0,
        public array $usedHelpers = [],
        public bool $compile = false,
        public array $parsed = [],
        public array $partials = [],
        public ?Closure $partialResolver = null,
        public array $partialBlock = [],
        public array $inlinePartial = [],
        public array $helpers = [],
        public ?Closure $helperResolver = null,
        public string|false $rawBlock = false,
        public readonly string $startChar = '{',
        public readonly string $separator = '.',
        public readonly string $cndStart = '.(',
        public readonly string $cndEnd = ').',
        public readonly string $cndThen = ' ? ',
        public readonly string $cndElse = ' : ',
        public readonly string $fStart = 'return ',
        public readonly string $fEnd = ';',
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
        $this->error = $context->error;
        $this->helpers = $context->helpers;
        $this->partials = $context->partials;
        $this->partialCode = $context->partialCode;
        $this->partialStack = $context->partialStack;
        $this->usedHelpers = $context->usedHelpers;
        $this->usedDynPartial = $context->usedDynPartial;
        $this->usedPBlock = $context->usedPBlock;
        $this->usedPartial = $context->usedPartial;
    }
}
