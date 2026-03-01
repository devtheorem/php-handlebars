<?php

namespace DevTheorem\Handlebars;

use Closure;

/** @internal */
enum Scope
{
    /** Sentinel default for fn()/inverse() meaning "use the current scope unchanged". */
    case Use;
}

class HelperOptions
{
    /**
     * @param array<mixed> $data
     * @param array<mixed> $hash
     * @param array<mixed> $outerBlockParams outer block param stack, passed as trailing elements of the stack
     */
    public function __construct(
        public mixed &$scope,
        public array &$data,
        public readonly string $name = '',
        public readonly array $hash = [],
        public readonly int $blockParams = 0,
        private readonly ?RuntimeContext $cx = null,
        private readonly ?Closure $cb = null,
        private readonly ?Closure $inv = null,
        private readonly array $outerBlockParams = [],
    ) {}

    /**
     * Allows isset($options->fn) and isset($options->inverse) to check whether the block exists.
     */
    public function __isset(string $name): bool
    {
        if ($name === 'fn') {
            return $this->cb !== null;
        } elseif ($name === 'inverse') {
            return $this->inv !== null;
        }
        return false;
    }

    public function fn(mixed $context = Scope::Use, mixed $data = null): string
    {
        if ($this->cx === null) {
            throw new \Exception('fn() is not supported for inline helpers');
        } elseif ($this->cb === null) {
            return '';
        }
        $cx = $this->cx;
        $scope = $this->scope;

        // Save partials so that any {{#* inline}} partials registered inside the block body
        // don't leak out after fn() returns. The spec requires inline partials to be
        // block-scoped. PHP copy-on-write makes this assignment cheap when no inline partials are registered.
        $savedPartials = $cx->partials;

        // Skip depths push for explicit same-context pass (equivalent to HBS.js options.fn(this))
        $skipDepths = $context === $scope;
        $resolvedContext = $skipDepths ? $scope : ($context === Scope::Use ? $scope : $context);
        $ret = $this->callBlock($this->cb, $resolvedContext, !$skipDepths, $data);

        $cx->partials = $savedPartials;
        return $ret;
    }

    public function inverse(mixed $context = null, mixed $data = null): string
    {
        if ($this->cx === null) {
            throw new \Exception('inverse() is not supported for inline helpers');
        } elseif ($this->inv === null) {
            return '';
        }
        return $this->callBlock($this->inv, $context ?? $this->scope, $context !== null, $data);
    }

    /** @param array<mixed>|null $data */
    private function callBlock(\Closure $closure, mixed $context, bool $pushDepths, ?array $data): string
    {
        $cx = $this->cx;
        assert($cx !== null);
        $savedFrame = null;
        $bpStack = null;

        if (isset($data['data'])) {
            $savedFrame = $cx->frame;
            // Fast path: only root in frame, no user @-data to inherit
            $newFrame = count($savedFrame) === 1 ? $data['data'] : array_replace($savedFrame, $data['data']);
            $newFrame['root'] = &$cx->data['root'];
            $newFrame['_parent'] = $savedFrame;
            $cx->frame = $newFrame;
        }

        if (isset($data['blockParams'])) {
            // Build block params stack: current level prepended to outer stack.
            $bpStack = [$data['blockParams'], ...$this->outerBlockParams];
        }

        if ($pushDepths) {
            // Push the current scope onto depths so that ../ path expressions inside the block
            // body can traverse back up to the caller's context.
            $cx->depths[] = $this->scope;
        }
        $ret = $closure($cx, $context, $bpStack);
        if ($pushDepths) {
            array_pop($cx->depths);
        }
        if ($savedFrame !== null) {
            $cx->frame = $savedFrame;
        }
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
        assert($cx !== null);
        $cb = $this->cb;

        // Push depths and save partials once for the entire loop.
        $cx->depths[] = $this->scope;
        $savedPartials = $cx->partials;

        $last = count($items) - 1;
        $ret = '';
        $i = 0;
        $outerFrame = $cx->frame;
        // Fast path: when only root is in the frame, skip array_replace.
        $simpleFrame = count($outerFrame) === 1;
        // Pre-allocate bpStack once; mutate [0][0] and [0][1] per iteration.
        // PHP COW ensures the inner array's refcount returns to 1 after $cb() returns,
        // so the next iteration's assignment is an in-place mutation, not a copy.
        $bpStack = [[null, null], ...$this->outerBlockParams];

        foreach ($items as $index => $value) {
            $iterData = ['key' => $index, 'index' => $i, 'first' => $i === 0, 'last' => $i === $last];
            $newFrame = $simpleFrame ? $iterData : array_replace($outerFrame, $iterData);
            $newFrame['root'] = &$cx->data['root'];
            $newFrame['_parent'] = $outerFrame;
            $cx->frame = $newFrame;

            $bpStack[0][0] = $value;
            $bpStack[0][1] = $index;
            $ret .= $cb($cx, $value, $bpStack);
            $i++;
        }

        $cx->frame = $outerFrame;
        array_pop($cx->depths);
        $cx->partials = $savedPartials;
        return $ret;
    }
}
