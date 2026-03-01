<?php

namespace DevTheorem\Handlebars;

use Closure;

class HelperOptions
{
    /**
     * @param array<mixed> $hash
     * @param array<mixed> $data
     * @param array<string> $blockParamNames
     */
    public function __construct(
        public readonly string $name,
        public readonly array $hash,
        public readonly int $blockParams,
        public mixed &$scope,
        public array &$data,
        private readonly RuntimeContext $cx,
        private readonly mixed $_this = null,
        private readonly ?Closure $cb = null,
        private readonly ?Closure $inv = null,
        private readonly array $blockParamNames = [],
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

    public function fn(mixed $context = null, mixed $data = null): string
    {
        if ($this->cb === null) {
            return '';
        }
        $cx = $this->cx;
        $cb = $this->cb;
        $_this = $this->_this;

        $savedPartials = $cx->partials;

        if (isset($data['data'])) {
            $savedData = $cx->data;
            $cx->data = array_replace($savedData, $data['data'], ['_parent' => $savedData]);
        }

        $hasBp = isset($data['blockParams']) && $this->blockParamNames;
        if ($hasBp) {
            $ex = [];
            foreach ($this->blockParamNames as $i => $name) {
                $ex[$name] = $data['blockParams'][$i];
            }
            array_unshift($cx->blParam, $ex);
        }

        $isNullCtx = $context === Runtime::nullContext();
        if (!$isNullCtx && ($context === null || $context === $_this)) {
            $ret = $cb($cx, $_this);
        } else {
            $cx->scopes[] = $_this;
            $ret = $cb($cx, $isNullCtx ? null : $context);
            array_pop($cx->scopes);
        }

        if (isset($savedData)) {
            $cx->data = $savedData;
        }
        $cx->partials = $savedPartials;
        if ($hasBp) {
            array_shift($cx->blParam);
        }
        return $ret;
    }

    public function inverse(mixed $context = null): string
    {
        if ($this->inv === null) {
            return '';
        }
        $cx = $this->cx;
        $_this = $this->_this;
        $inv = $this->inv;

        if ($context === null) {
            return $inv($cx, $_this);
        }
        $cx->scopes[] = $_this;
        $ret = $inv($cx, $context);
        array_pop($cx->scopes);
        return $ret;
    }

    public function lookupProperty(mixed $obj, string $key): mixed
    {
        if (is_array($obj)) {
            return $obj[$key] ?? null;
        }
        if (is_object($obj)) {
            return $obj->$key ?? null;
        }
        return null;
    }
}
