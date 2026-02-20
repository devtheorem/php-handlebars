<?php

namespace DevTheorem\Handlebars;

/**
 * @internal
 */
final class Runtime
{
    /**
     * Output debug info.
     */
    public static function debug(string $expression, string $runtimeFn, mixed ...$rest): mixed
    {
        $runtime = self::class;
        return call_user_func_array("$runtime::$runtimeFn", $rest);
    }

    /**
     * Throw exception for missing expression. Only used in strict mode.
     */
    public static function miss(string $v): void
    {
        throw new \Exception("Runtime: $v does not exist");
    }

    /**
     * Invoke $v if it is callable, passing any extra args; otherwise return $v as-is.
     * Used for data variables that may hold functions (e.g. {{@hello}} or {{@hello "arg"}}).
     */
    public static function dv(mixed $v, mixed ...$args): mixed
    {
        return is_callable($v) ? $v(...$args) : $v;
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
            && (!is_array($v) || count($v) > 0);
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
        return $v === null || $v === false || (is_array($v) && count($v) === 0);
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
     * Get string value
     *
     * @param array<array<mixed>|string|int>|string|int|bool|null $v value to be output
     * @param int $ex 1 to return untouched value, default is 0
     *
     * @return array<array<mixed>|string|int>|string|null The raw value of the specified variable
     */
    public static function raw(array|string|int|bool|null $v, int $ex = 0): string|array|null
    {
        if ($ex) {
            return $v;
        }

        if ($v === true) {
            return 'true';
        }

        if ($v === false) {
            return 'false';
        }

        if (is_array($v)) {
            if (static::isObjectArray($v)) {
                return '[object Object]';
            } else {
                $ret = '';
                foreach ($v as $vv) {
                    $ret .= static::raw($vv) . ',';
                }
                return substr($ret, 0, -1);
            }
        }

        return "$v";
    }

    /**
     * For {{#var}} or {{#each}} .
     *
     * @param array<array<mixed>|string|int>|string|int|bool|null|\Traversable<string, mixed> $v value for the section
     * @param array<string>|null $bp block parameters
     * @param array<array<mixed>|string|int>|string|int|null $in input data with current scope
     * @param bool $each true when rendering #each
     * @param \Closure $cb callback function to render child context
     * @param \Closure|null $else callback function to render child context when {{else}}
     */
    public static function sec(RuntimeContext $cx, mixed $v, ?array $bp, mixed $in, bool $each, \Closure $cb, ?\Closure $else = null): string
    {
        $push = $in !== $v || $each;

        $isAry = is_array($v) || ($v instanceof \ArrayObject);
        $isTrav = $v instanceof \Traversable;
        $loop = $each;
        $keys = null;
        $last = null;
        $isObj = false;

        if ($isAry && $else !== null && count($v) === 0) {
            return $else($cx, $in);
        }

        // #var, detect input type is object or not
        if (!$loop && $isAry) {
            $keys = array_keys($v);
            $isObj = static::isObjectArray($v);
            $loop = !$isObj;
        }

        if (($loop && $isAry) || $isTrav) {
            if ($each && !$isTrav) {
                // Detect input type is object or not when never done once
                if ($keys == null) {
                    $keys = array_keys($v);
                    $isObj = static::isObjectArray($v);
                }
            }
            $ret = [];
            $cx = clone $cx;
            if ($push) {
                $cx->scopes[] = $in;
            }
            $i = 0;
            $oldSpvar = $cx->spVars ?? [];
            $cx->spVars = array_merge(['root' => $oldSpvar['root'] ?? null], $oldSpvar, ['_parent' => $oldSpvar]);
            if (!$isTrav) {
                $last = count($keys) - 1;
            }

            $isSparceArray = $isObj && (count(array_filter(array_keys($v), 'is_string')) == 0);

            foreach ($v as $index => $raw) {
                $cx->spVars['first'] = ($i === 0);
                $cx->spVars['last'] = ($i == $last);
                $cx->spVars['key'] = $index;
                $cx->spVars['index'] = $isSparceArray ? $index : $i;
                $i++;
                $originalRaw = $raw;
                if (isset($bp[0])) {
                    $raw = static::merge($raw, [$bp[0] => $raw]);
                }
                if (isset($bp[1])) {
                    $raw = static::merge($raw, [$bp[1] => $index]);
                }
                if ($bp) {
                    $bpEntry = [];
                    if (isset($bp[0])) {
                        $bpEntry[$bp[0]] = $originalRaw;
                    }
                    if (isset($bp[1])) {
                        $bpEntry[$bp[1]] = $index;
                    }
                    array_unshift($cx->blParam, $bpEntry);
                    $ret[] = $cb($cx, $raw);
                    array_shift($cx->blParam);
                } else {
                    $ret[] = $cb($cx, $raw);
                }
            }

            if ($isObj) {
                unset($cx->spVars['key']);
            } else {
                unset($cx->spVars['last']);
            }
            unset($cx->spVars['index'], $cx->spVars['first']);

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

        if ($v === true) {
            return $cb($cx, $in);
        }

        if ($v !== null && $v !== false) {
            return $cb($cx, $v);
        }

        if ($else !== null) {
            return $else($cx, $in);
        }

        return '';
    }

    /**
     * For {{#with}} .
     *
     * @param array<array<mixed>|string|int>|string|int|bool|null $v value to be the new context
     * @param array<string>|null $bp block parameters
     * @param array<array<mixed>|string|int>|\stdClass|null $in input data with current scope
     * @param \Closure $cb callback function to render child context
     * @param \Closure|null $else callback function to render child context when {{else}}
     */
    public static function wi(RuntimeContext $cx, mixed $v, ?array $bp, array|\stdClass|null $in, \Closure $cb, ?\Closure $else = null): string
    {
        if (isset($bp[0])) {
            $v = static::merge($v, [$bp[0] => $v]);
        }

        if ($v === false || $v === null || (is_array($v) && count($v) === 0)) {
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
     * @param array<array<mixed>|string|int>|string|int|null $b the new context to overwrite
     *
     * @return array<array<mixed>|string|int>|string|int the merged context object
     */
    public static function merge(mixed $a, mixed $b): mixed
    {
        if (is_array($b)) {
            if ($a === null) {
                return $b;
            } elseif (is_array($a)) {
                return array_merge($a, $b);
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
     * @param array<array<mixed>|string|int>|string|int|null $v value to be the new context
     * @param string $indent whitespace to prepend to each line of the partial's output
     */
    public static function p(RuntimeContext $cx, string $p, $v, int $pid, string $indent): string
    {
        $pp = ($p === '@partial-block') ? $p . ($pid > 0 ? $pid : $cx->partialId) : $p;

        if (!isset($cx->partials[$pp])) {
            throw new \Exception("Runtime: the partial $p could not be found");
        }

        $cx = clone $cx;
        $cx->partialId = ($p === '@partial-block') ? ($pid > 0 ? $pid : ($cx->partialId > 0 ? $cx->partialId - 1 : 0)) : $pid;
        $cx->partialDepth++;

        if ($cx->partialDepth > 100) {
            throw new \Exception("Runtime: the partial $p could not be found");
        }

        $result = $cx->partials[$pp]($cx, static::merge($v[0][0], $v[1]), '');

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
     * @param array<array<mixed>|string|int> $vars variables for the helper
     * @param array<string,array<mixed>|string|int> $_this current rendering context for the helper
     */
    public static function hbch(RuntimeContext $cx, string $ch, array $vars, mixed &$_this): mixed
    {
        if (isset($cx->blParam[0][$ch])) {
            return $cx->blParam[0][$ch];
        }

        $options = new HelperOptions(
            name: $ch,
            hash: $vars[1],
            fn: fn() => '',
            inverse: fn() => '',
            blockParams: 0,
            scope: $_this,
            data: $cx->spVars,
        );

        return static::exch($cx, $ch, $vars, $options);
    }

    /**
     * For block custom helpers.
     *
     * @param string $ch the name of custom helper to be executed
     * @param array<array<mixed>|string|int> $vars variables for the helper
     * @param array<string,array<mixed>|string|int> $_this current rendering context for the helper
     * @param bool $inverted the logic will be inverted
     * @param \Closure|null $cb callback function to render child context
     * @param \Closure|null $else callback function to render child context when {{else}}
     */
    public static function hbbch(RuntimeContext $cx, string $ch, array $vars, mixed &$_this, bool $inverted, ?\Closure $cb, ?\Closure $else = null): mixed
    {
        $blockParams = isset($vars[2]) ? count($vars[2]) : 0;
        $data = &$cx->spVars;

        // invert the logic
        if ($inverted) {
            $tmp = $else;
            $else = $cb;
            $cb = $tmp;
        }

        $options = new HelperOptions(
            name: $ch,
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
     * If $callable is not callable, falls back to sec() using it as a section value.
     * @param array<array<mixed>|string|int> $vars variables for the helper
     * @param array<string,array<mixed>|string|int> $_this current rendering context for the helper
     * @param \Closure|null $cb callback function to render child context
     * @param \Closure|null $else callback function to render child context when {{else}}
     */
    public static function dynhbbch(RuntimeContext $cx, mixed $callable, array $vars, mixed &$_this, ?\Closure $cb, ?\Closure $else = null): mixed
    {
        if (!is_callable($callable)) {
            return static::sec($cx, $callable, null, $_this, false, $cb, $else);
        }

        $blockParams = isset($vars[2]) ? count($vars[2]) : 0;
        $data = &$cx->spVars;

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
     * Handles spVars updates, block-param injection, and context scope pushing.
     *
     * @param array<array<mixed>|string|int> $vars
     */
    private static function makeBlockFn(RuntimeContext $cx, mixed $_this, ?\Closure $cb, array $vars): \Closure
    {
        return function ($context = null, $data = null) use ($cx, $_this, $cb, $vars) {
            $cx = clone $cx;
            $old_spvar = $cx->spVars;
            if (isset($data['data'])) {
                $cx->spVars = array_merge(['root' => $old_spvar['root']], $data['data'], ['_parent' => $old_spvar]);
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
                $cx->spVars = $old_spvar;
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
            return $result;
        }

        // $cb may be null (inverted block with no else clause) after the inversion swap in hbbch
        return static::sec($cx, $result, null, $_this, false, $cb ?? static fn() => '', $else);
    }

    private static function withScope(RuntimeContext $cx, mixed $scope, mixed $context, \Closure $cb): string
    {
        $cx->scopes[] = $scope;
        $ret = $cb($cx, $context);
        array_pop($cx->scopes);
        return $ret;
    }

    /**
     * @param array<mixed> $v
     */
    private static function isObjectArray(array $v): bool
    {
        return count(array_diff_key($v, array_keys(array_keys($v)))) !== 0;
    }

    /**
     * Execute custom helper with prepared options
     *
     * @param string $ch the name of custom helper to be executed
     * @param array<array<mixed>|string|int> $vars variables for the helper
     */
    public static function exch(RuntimeContext $cx, string $ch, array $vars, HelperOptions $options): mixed
    {
        $args = $vars[0];
        $args[] = $options;

        try {
            return ($cx->helpers[$ch])(...$args);
        } catch (\Throwable $e) {
            throw new \Exception("Runtime: call custom helper '$ch' error: " . $e->getMessage());
        }
    }
}
