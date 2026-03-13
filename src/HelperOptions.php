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
        $bpStack = null;

        if ($data !== null) {
            if (isset($data['data'])) {
                $savedFrame = $cx->frame;
                if (count($savedFrame) === 1) {
                    // Fast path: only root in frame, no user @-data to inherit
                    $newFrame = $data['data'];
                } else {
                    $newFrame = array_replace($savedFrame, $data['data']);
                }
                $newFrame['root'] = &$cx->data['root'];
                $newFrame['_parent'] = $savedFrame;
                $cx->frame = $newFrame;
            }

            if (isset($data['blockParams'])) {
                // Build block params stack: current level prepended to outer stack.
                $bpStack = [$data['blockParams'], ...$this->outerBlockParams];
            }
        }

        $cb = $this->cb;
        $scope = $this->scope;
        $savedPartials = $cx->partials;

        if ($context === $scope) {
            // explicit same-context pass, equivalent to HBS.js options.fn(this)
            $ret = $cb($cx, $scope, $bpStack);
        } else {
            // new context - push onto depths, equivalent to HBS.js options.fn()
            $cx->depths[] = $scope;
            $newCtx = $context === Scope::Use ? $scope : $context;
            $ret = $cb($cx, $newCtx, $bpStack);
            array_pop($cx->depths);
        }

        if (isset($savedFrame)) {
            $cx->frame = $savedFrame;
        }
        $cx->partials = $savedPartials;
        return $ret;
    }

    public function inverse(mixed $context = null): string
    {
        if ($this->cx === null) {
            throw new \Exception('inverse() is not supported for inline helpers');
        } elseif ($this->inv === null) {
            return '';
        }
        $cx = $this->cx;
        $inv = $this->inv;

        if ($context === null) {
            return $inv($cx, $this->scope);
        }
        $cx->depths[] = $this->scope;
        $ret = $inv($cx, $context);
        array_pop($cx->depths);
        return $ret;
    }
}
