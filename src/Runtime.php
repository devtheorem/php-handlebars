<?php

declare(strict_types=1);

namespace DevTheorem\Handlebars;

/**
 * @internal
 */
final class Runtime
{
    /**
     * Throw exception for missing expression. Only used in strict mode.
     */
    public static function miss(string $v): void
    {
        throw new \Exception('"' . $v . '" not defined');
    }

    /**
     * Strict-mode key lookup: throw if $base is not an array or $key is absent.
     * Unlike the null-coalescing pattern, this allows null values when the key exists.
     */
    public static function strictLookup(mixed $base, string $key, string $original): mixed
    {
        if (!is_array($base) || !array_key_exists($key, $base)) {
            self::miss($original);
        }
        return $base[$key];
    }

    /**
     * Invoke $v if it is callable, passing any extra args; otherwise return $v as-is.
     * Used for data variables that may hold functions (e.g. {{@hello}} or {{@hello "arg"}}).
     */
    public static function dv(mixed $v, mixed ...$args): mixed
    {
        return $v instanceof \Closure ? $v(...$args) : $v;
    }

    /**
     * For {{log}}.
     * @param array<mixed> $v
     */
    public static function lo(array $v): string
    {
        error_log(var_export($v[0], true));
        return '';
    }

    /**
     * For {{#if}} and {{#unless}}.
     *
     * @param array<array<mixed>|string|int>|string|\Stringable|int|float|bool|null $v value to be tested
     * @param bool $zero include zero as true
     *
     * @return bool Return true when the value is not null nor false.
     */
    public static function ifvar(mixed $v, bool $zero = false): bool
    {
        return $v !== null
            && $v !== false
            && ($zero || ($v !== 0 && $v !== 0.0))
            && $v !== ''
            && (!$v instanceof \Stringable || (string) $v !== '')
            && (!is_array($v) || $v);
    }

    /**
     * For {{^var}} .
     *
     * @param array<array<mixed>|string|int>|string|int|bool|null $v value to be tested
     *
     * @return bool Return true when the value is null or false or empty
     */
    public static function isec(mixed $v): bool
    {
        return $v === null || $v === false || (is_array($v) && !$v);
    }

    /**
     * HTML encode {{var}} just like handlebars.js
     *
     * @param array<array<mixed>|string|int>|string|SafeString|int|null $var value to be htmlencoded
     */
    public static function encq($var): string
    {
        if ($var instanceof SafeString) {
            return (string) $var;
        }

        return Handlebars::escapeExpression(static::raw($var));
    }

    /**
     * Get string representation for output
     *
     * @param array<mixed>|string|StringObject|int|bool|null $v value to be output
     */
    public static function raw(array|string|StringObject|int|bool|null $v): string
    {
        if ($v === true) {
            return 'true';
        }

        if ($v === false) {
            return 'false';
        }

        if (is_array($v)) {
            if (array_is_list($v)) {
                $ret = '';
                foreach ($v as $vv) {
                    $ret .= static::raw($vv) . ',';
                }
                return substr($ret, 0, -1);
            } else {
                return '[object Object]';
            }
        }

        return "$v";
    }

    /**
     * For {{#var}} or {{#each}} .
     *
     * @param array<array<mixed>|string|int>|string|int|bool|null|\Closure|\Traversable<string, mixed> $v value for the section
     * @param array<string> $bp block parameters
     * @param array<array<mixed>|string|int>|string|int|null $in input data with current scope
     * @param bool $each true when rendering #each
     * @param \Closure $cb callback function to render child context
     * @param \Closure|null $else callback function to render child context when {{else}}
     */
    public static function sec(RuntimeContext $cx, mixed $v, array $bp, mixed $in, bool $each, \Closure $cb, ?\Closure $else = null): string
    {
        if ($else !== null && (is_array($v) || $v instanceof \ArrayObject)) {
            return $else($cx, $in);
        }

        $push = $in !== $v || $each;
        $isAry = is_array($v) || $v instanceof \ArrayObject;
        $isTrav = $v instanceof \Traversable;
        $loop = $each || (is_array($v) && array_is_list($v));

        if (($loop && $isAry || $isTrav) && is_iterable($v)) {
            $last = null;
            $isObj = false;
            $isSparseArray = false;
            if (is_array($v)) {
                $keys = array_keys($v);
                $last = count($keys) - 1;
                $isObj = !array_is_list($v);
                $isSparseArray = $isObj && !array_filter($keys, is_string(...));
            }
            $ret = [];
            $cx = clone $cx;
            if ($push) {
                $cx->scopes[] = $in;
            }
            $i = 0;
            $oldData = $cx->data ?? [];
            $cx->data = array_merge(['root' => $oldData['root'] ?? null], $oldData, ['_parent' => $oldData]);

            foreach ($v as $index => $raw) {
                $cx->data['first'] = ($i === 0);
                $cx->data['last'] = ($i === $last);
                $cx->data['key'] = $index;
                $cx->data['index'] = $isSparseArray ? $index : $i;
                $i++;
                if ($bp) {
                    $bpEntry = [];
                    if (isset($bp[0])) {
                        $bpEntry[$bp[0]] = $raw;
                        $raw = static::merge($raw, [$bp[0] => $raw]);
                    }
                    if (isset($bp[1])) {
                        $bpEntry[$bp[1]] = $index;
                        $raw = static::merge($raw, [$bp[1] => $index]);
                    }
                    array_unshift($cx->blParam, $bpEntry);
                }
                $ret[] = $cb($cx, $raw);
                if ($bp) {
                    array_shift($cx->blParam);
                }
            }

            if ($isObj) {
                unset($cx->data['key']);
            } else {
                unset($cx->data['last']);
            }
            unset($cx->data['index'], $cx->data['first']);

            if ($push) {
                array_pop($cx->scopes);
            }
            return join('', $ret);
        }

        if ($each) {
            return ($else !== null) ? $else($cx, $in) : '';
        }

        if ($isAry) {
            if ($push) {
                $cx->scopes[] = $in;
            }
            $ret = $cb($cx, $v);
            if ($push) {
                array_pop($cx->scopes);
            }
            return $ret;
        }

        if ($v instanceof \Closure) {
            $options = new HelperOptions(
                name: '',
                hash: [],
                fn: function ($context = null) use ($cx, $in, $cb) {
                    if ($context === null || $context === $in) {
                        return $cb($cx, $in);
                    }
                    return static::withScope($cx, $in, $context, $cb);
                },
                inverse: function ($context = null) use ($cx, $in, $else) {
                    if ($else === null) {
                        return '';
                    }
                    if ($context === null || $context === $in) {
                        return $else($cx, $in);
                    }
                    return static::withScope($cx, $in, $context, $else);
                },
                blockParams: 0,
                scope: $in,
                data: $cx->data,
            );
            $result = $v($options);
            return static::applyBlockHelperMissing($cx, $result, $in, $cb, $else);
        }

        if ($v !== null && $v !== false) {
            return $cb($cx, $v === true ? $in : $v);
        }

        return $else !== null ? $else($cx, $in) : '';
    }

    /**
     * For {{#with}} .
     *
     * @param array<array<mixed>|string|int>|string|int|null $v value to be the new context
     * @param array<string> $bp block parameters
     * @param array<array<mixed>|string|int>|\stdClass|null $in input data with current scope
     * @param \Closure $cb callback function to render child context
     * @param \Closure|null $else callback function to render child context when {{else}}
     */
    public static function wi(RuntimeContext $cx, mixed $v, array $bp, array|\stdClass|null $in, \Closure $cb, ?\Closure $else = null): string
    {
        if (isset($bp[0])) {
            $v = static::merge($v, [$bp[0] => $v]);
        }

        if ($v === null || is_array($v) && !$v) {
            return $else ? $else($cx, $in) : '';
        }

        $savedPartials = $cx->partials;

        if ($v === $in) {
            $ret = $cb($cx, $v);
        } else {
            $ret = static::withScope($cx, $in, $v, $cb);
        }

        $cx->partials = $savedPartials;

        return $ret;
    }

    /**
     * Get merged context.
     *
     * @param array<array<mixed>|string|int>|object|string|int|null $a the context to be merged
     * @param array<array<mixed>|string|int|null>|string|int|null $b the new context to overwrite
     *
     * @return array<array<mixed>|string|int|null>|object|string|int|null the merged context object
     */
    public static function merge(mixed $a, mixed $b): mixed
    {
        if (is_array($b)) {
            if ($a === null || is_int($a)) {
                return $b;
            } elseif (is_array($a)) {
                return array_replace($a, $b);
            } else {
                if (!is_object($a)) {
                    $a = new StringObject($a);
                }
                foreach ($b as $i => $v) {
                    $a->$i = $v;
                }
            }
        }
        return $a;
    }

    /**
     * For {{> partial}} .
     *
     * @param string $p partial name
     * @param array<array<mixed>> $v value to be the new context
     * @param string $indent whitespace to prepend to each line of the partial's output
     */
    public static function p(RuntimeContext $cx, string $p, array $v, int $pid, string $indent): string
    {
        $pp = ($p === '@partial-block') ? $p . ($pid > 0 ? $pid : $cx->partialId) : $p;

        if (!isset($cx->partials[$pp])) {
            throw new \Exception("Runtime: the partial $p could not be found");
        }

        $savedPartialId = $cx->partialId;
        $cx->partialId = ($p === '@partial-block') ? ($pid > 0 ? $pid : ($cx->partialId > 0 ? $cx->partialId - 1 : 0)) : $pid;
        $cx->partialDepth++;

        $result = $cx->partials[$pp]($cx, static::merge($v[0][0], $v[1]), '');
        $cx->partialId = $savedPartialId;
        $cx->partialDepth--;

        if ($indent !== '') {
            $lines = explode("\n", $result);
            $lastIdx = count($lines) - 1;
            foreach ($lines as $i => &$line) {
                if ($line === '' && $i === $lastIdx) {
                    break;
                }
                $line = $indent . $line;
            }
            unset($line);
            $result = implode("\n", $lines);
        }

        return $result;
    }

    /**
     * For {{#* inlinepartial}} .
     *
     * @param string $p partial name
     * @param \Closure $code the compiled partial code
     */
    public static function in(RuntimeContext $cx, string $p, \Closure $code): void
    {
        if (str_starts_with($p, '@partial-block')) {
            // Capture the outer partialId at registration time so that when this
            // block closure runs, any {{>@partial-block}} inside it resolves to
            // the correct outer partial block (not partialId - 1).
            $outerPartialId = $cx->partialId;
            $cx->partials[$p] = function (RuntimeContext $cx, mixed $in, string $sp) use ($code, $outerPartialId): string {
                $cx->partialId = $outerPartialId;
                return $code($cx, $in, $sp);
            };
        } else {
            $cx->partials[$p] = $code;
        }
    }

    /**
     * For single custom helpers.
     *
     * @param string $ch the name of custom helper to be executed
     * @param array<array<mixed>> $vars variables for the helper
     * @param array<string,array<mixed>|string|int> $_this current rendering context for the helper
     * @param string|null $logicalName when set, use as options.name instead of $ch
     */
    public static function hbch(RuntimeContext $cx, string $ch, array $vars, mixed &$_this, ?string $logicalName = null): mixed
    {
        if (isset($cx->blParam[0][$ch])) {
            return $cx->blParam[0][$ch];
        }

        $options = new HelperOptions(
            name: $logicalName ?? $ch,
            hash: $vars[1],
            fn: fn() => '',
            inverse: fn() => '',
            blockParams: 0,
            scope: $_this,
            data: $cx->data,
        );

        return static::exch($cx, $ch, $vars, $options);
    }

    /**
     * For block custom helpers.
     *
     * @param string $ch the name of custom helper to be executed
     * @param array<array<mixed>> $vars variables for the helper
     * @param array<string,array<mixed>|string|int> $_this current rendering context for the helper
     * @param bool $inverted the logic will be inverted
     * @param \Closure $cb callback function to render child context
     * @param \Closure|null $else callback function to render child context when {{else}}
     * @param string|null $logicalName when set, use as options.name instead of $ch
     */
    public static function hbbch(RuntimeContext $cx, string $ch, array $vars, mixed &$_this, bool $inverted, \Closure $cb, ?\Closure $else = null, ?string $logicalName = null): mixed
    {
        $blockParams = isset($vars[2]) ? count($vars[2]) : 0;
        $data = &$cx->data;

        // invert the logic
        if ($inverted) {
            $tmp = $else;
            $else = $cb;
            $cb = $tmp;
        }

        $options = new HelperOptions(
            name: $logicalName ?? $ch,
            hash: $vars[1],
            fn: static::makeBlockFn($cx, $_this, $cb, $vars),
            inverse: static::makeInverseFn($cx, $_this, $else),
            blockParams: $blockParams,
            scope: $_this,
            data: $data,
        );

        return static::applyBlockHelperMissing($cx, static::exch($cx, $ch, $vars, $options), $_this, $cb, $else);
    }

    /**
     * Like hbbch but for non-registered paths (pathed/depthed/scoped block calls with params).
     * @param array<array<mixed>> $vars variables for the helper
     * @param array<string,array<mixed>|string|int> $_this current rendering context for the helper
     * @param \Closure $cb callback function to render child context
     * @param \Closure|null $else callback function to render child context when {{else}}
     */
    public static function dynhbbch(RuntimeContext $cx, string $name, mixed $callable, array $vars, mixed &$_this, \Closure $cb, ?\Closure $else = null): mixed
    {
        if (!$callable instanceof \Closure) {
            throw new \Exception('"' . $name . '" is not a block helper function');
        }

        $blockParams = isset($vars[2]) ? count($vars[2]) : 0;
        $data = &$cx->data;

        $options = new HelperOptions(
            name: '',
            hash: $vars[1],
            fn: static::makeBlockFn($cx, $_this, $cb, $vars),
            inverse: static::makeInverseFn($cx, $_this, $else),
            blockParams: $blockParams,
            scope: $_this,
            data: $data,
        );

        $args = $vars[0];
        $args[] = $options;
        try {
            $result = $callable(...$args);
        } catch (\Throwable $e) {
            throw new \Exception('Runtime: dynamic block helper error: ' . $e->getMessage());
        }

        return static::applyBlockHelperMissing($cx, $result, $_this, $cb, $else);
    }

    /**
     * Build the $fn closure passed to HelperOptions for block helpers.
     * Handles private variable updates, block-param injection, and context scope pushing.
     *
     * @param array<array<mixed>> $vars
     */
    private static function makeBlockFn(RuntimeContext $cx, mixed $_this, ?\Closure $cb, array $vars): \Closure
    {
        if (!$cb) {
            return fn() => '';
        }

        return function ($context = null, $data = null) use ($cx, $_this, $cb, $vars) {
            $cx = clone $cx;
            $oldData = $cx->data;
            if (isset($data['data'])) {
                $cx->data = array_merge(['root' => $oldData['root']], $data['data'], ['_parent' => $oldData]);
            }

            if (isset($data['blockParams'], $vars[2])) {
                $ex = array_combine($vars[2], array_slice($data['blockParams'], 0, count($vars[2])));
                array_unshift($cx->blParam, $ex);
            }

            if ($context === null || $context === $_this) {
                $ret = $cb($cx, $_this);
            } else {
                $ret = static::withScope($cx, $_this, $context, $cb);
            }

            if (isset($data['data'])) {
                $cx->data = $oldData;
            }
            return $ret;
        };
    }

    /**
     * Build the $inverse closure passed to HelperOptions for block helpers.
     */
    private static function makeInverseFn(RuntimeContext $cx, mixed $_this, ?\Closure $else): \Closure
    {
        return $else
            ? function ($context = null) use ($cx, $_this, $else) {
                if ($context === null) {
                    return $else($cx, $_this);
                }
                return static::withScope($cx, $_this, $context, $else);
            }
        : fn() => '';
    }

    /**
     * Apply blockHelperMissing semantics: if the helper returned a string/SafeString,
     * pass it through; otherwise treat the return value as a section context.
     */
    private static function applyBlockHelperMissing(RuntimeContext $cx, mixed $result, mixed $_this, ?\Closure $cb, ?\Closure $else): string
    {
        if (is_string($result) || $result instanceof SafeString) {
            return (string) $result;
        }

        // $cb may be null (inverted block with no else clause) after the inversion swap in hbbch
        return static::sec($cx, $result, [], $_this, false, $cb ?? static fn() => '', $else);
    }

    private static function withScope(RuntimeContext $cx, mixed $scope, mixed $context, \Closure $cb): string
    {
        $cx->scopes[] = $scope;
        $ret = $cb($cx, $context);
        array_pop($cx->scopes);
        return $ret;
    }

    /**
     * Execute custom helper with prepared options
     *
     * @param string $ch the name of custom helper to be executed
     * @param array<array<mixed>> $vars variables for the helper
     */
    public static function exch(RuntimeContext $cx, string $ch, array $vars, HelperOptions $options): mixed
    {
        $args = $vars[0];
        $args[] = $options;

        try {
            return ($cx->helpers[$ch])(...$args);
        } catch (\Throwable $e) {
            throw new \Exception("Custom helper '$ch' error: " . $e->getMessage());
        }
    }
}
