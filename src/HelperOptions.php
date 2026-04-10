<?php

namespace DevTheorem\Handlebars;

use Closure;

/** @internal */
enum Scope
{
    /** Sentinel default for fn()/inverse() meaning "use the current scope unchanged". */
    case Use;
}

/**
 * @phpstan-import-type Template from Handlebars
 */
final class HelperOptions
{
    /**
     * @param array<mixed> $data
     * @param array<mixed> $hash
     * @param array<mixed> $outerBlockParams outer block param stack, passed as trailing elements of the stack
     */
    public function __construct(
        public mixed &$scope,
        public array &$data,
        private readonly RuntimeContext $cx,
        public readonly string $name = '',
        public readonly array $hash = [],
        public readonly int $blockParams = 0,
        private readonly ?Closure $cb = null,
        private readonly ?Closure $inv = null,
        private readonly array $outerBlockParams = [],
    ) {}

    /**
     * Returns true if a partial with the given name is registered.
     */
    public function hasPartial(string $name): bool
    {
        return isset($this->cx->inlinePartials[$name]) || isset($this->cx->partials[$name]);
    }

    /**
     * Registers a compiled partial closure under the given name for the remainder of the render.
     * Typically used alongside hasPartial() to implement lazy partial loading.
     * @param Template $partial
     */
    public function registerPartial(string $name, Closure $partial): void
    {
        $this->cx->partials[$name] = $partial;
    }

    /**
     * Supports isset($options->fn) and isset($options->inverse), both of which return true for
     * any block helper call and false for inline helper calls (matching Handlebars.js behavior).
     */
    public function __isset(string $name): bool
    {
        if ($name === 'fn' || $name === 'inverse') {
            return $this->cb !== null || $this->inv !== null;
        }
        return false;
    }

    public function fn(mixed $context = Scope::Use, mixed $data = null): string
    {
        if ($this->cb === null) {
            if ($this->inv === null) {
                throw new \Exception('fn() is not supported for inline helpers');
            }
            // Occurs when blockHelperMissing routes a truthy context through fn() for an inverted block.
            return '';
        }
        return $this->invokeBlock($this->cb, $context, $data);
    }

    public function inverse(mixed $context = Scope::Use, mixed $data = null): string
    {
        if ($this->inv === null) {
            if ($this->cb === null) {
                throw new \Exception('inverse() is not supported for inline helpers');
            }
            return '';
        }
        return $this->invokeBlock($this->inv, $context, $data);
    }

    private function invokeBlock(Closure $closure, mixed $context, mixed $data): string
    {
        $cx = $this->cx;
        // Save inlinePartials so that any {{#* inline}} partials registered inside the block body
        // don't leak out after it returns. The spec requires inline partials to be
        // block-scoped. PHP copy-on-write makes this assignment cheap when no inline partials are registered.
        $savedInlinePartials = $cx->inlinePartials;
        $scope = $this->scope;
        // Skip depths push when the caller explicitly passes the current scope (e.g. fn($options->scope)),
        // equivalent to HBS.js options.fn(this) where the scope level isn't changing.
        $pushDepths = $context !== $scope;
        $resolvedContext = ($context === Scope::Use) ? $scope : $context;
        $outerFrame = null;
        $bpStack = null;

        if (isset($data['data'])) {
            $outerFrame = $cx->data;
            $cx->data = $data['data'];
        }

        if (isset($data['blockParams'])) {
            // Build block params stack: current level prepended to outer stack.
            $bpStack = [$data['blockParams'], ...$this->outerBlockParams];
        }

        if ($pushDepths) {
            // Push the current scope onto depths so that ../ path expressions inside the block
            // body can traverse back up to the caller's context.
            $cx->depths[] = $scope;
        }
        $ret = $closure($cx, $resolvedContext, $bpStack ?? $this->outerBlockParams);
        if ($pushDepths) {
            array_pop($cx->depths);
        }
        if ($outerFrame !== null) {
            $cx->data = $outerFrame;
        }
        $cx->inlinePartials = $savedInlinePartials;
        return $ret;
    }

    /**
     * Optimized iteration for each-like helpers: performs depths push and partials save/restore
     * once around the entire loop rather than once per fn() call.
     *
     * HBS.js achieves the same effect by capturing the depths array at sub-program creation time
     * (before the loop), so all iterations share the same static depths reference.
     *
     * @param array<mixed> $items
     * @internal
     */
    public function iterate(array $items): string
    {
        if (!$items) {
            return $this->inverse();
        }
        if ($this->cb === null) {
            return '';
        }
        $cx = $this->cx;
        $cb = $this->cb;

        // Push depths and save inlinePartials once for the entire loop.
        $cx->depths[] = $this->scope;
        $savedInlinePartials = $cx->inlinePartials;

        $last = count($items) - 1;
        $ret = '';
        $i = 0;
        $outerFrame = $cx->data;
        $hasBp = $this->blockParams > 0;
        // When block params are declared, pre-allocate a slot at depth 0 and mutate it each
        // iteration. PHP COW ensures the inner array's refcount returns to 1 after $cb() returns,
        // so the next iteration's assignment is an in-place mutation, not a copy.
        // When no block params are declared, pass outerBlockParams directly — prepending a slot
        // would shift all compiled depth indices by 1.
        $bpStack = $hasBp ? [[null, null], ...$this->outerBlockParams] : $this->outerBlockParams;
        $data = Handlebars::createFrame($outerFrame);
        $data['first'] = true;

        foreach ($items as $index => $value) {
            $data['key'] = $index;
            $data['index'] = $i;
            $data['last'] = $i === $last;
            $cx->data = $data;

            if ($hasBp) {
                $bpStack[0][0] = $value;
                $bpStack[0][1] = $index;
            }
            $ret .= $cb($cx, $value, $bpStack);
            $data['first'] = false;
            $i++;
        }

        $cx->data = $outerFrame;
        array_pop($cx->depths);
        $cx->inlinePartials = $savedInlinePartials;
        return $ret;
    }
}
