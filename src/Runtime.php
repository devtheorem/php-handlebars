<?php

declare(strict_types=1);

namespace DevTheorem\Handlebars;

/**
 * @internal
 */
final class Runtime
{
    /** Sentinel used by the built-in each helper to pass an explicit null item as context. */
    private static ?object $nullCtxSentinel = null;
    private static ?\Closure $emptyFn = null;
    /** @var array<string, \Closure>|null */
    private static ?array $defaultHelpers = null;

    public static function nullContext(): object
    {
        return self::$nullCtxSentinel ??= new \stdClass();
    }

    private static function emptyFn(): \Closure
    {
        return self::$emptyFn ??= static fn() => '';
    }

    /**
     * Default implementations of the built-in Handlebars helpers.
     * These are pre-registered in every runtime context and can be overridden.
     *
     * @return array<string, \Closure>
     */
    public static function defaultHelpers(): array
    {
        return self::$defaultHelpers ??= [
            'if' => static function (mixed ...$args): string {
                /** @var HelperOptions $options */
                $options = array_pop($args);
                if (count($args) !== 1) {
                    throw new \Exception('#if requires exactly one argument');
                }
                $condition = $args[0] instanceof \Closure ? $args[0]($options->scope) : $args[0];
                return static::ifvar($condition, (bool) ($options->hash['includeZero'] ?? false))
                    ? $options->fn()
                    : $options->inverse();
            },
            'unless' => static function (mixed ...$args): string {
                /** @var HelperOptions $options */
                $options = array_pop($args);
                if (count($args) !== 1) {
                    throw new \Exception('#unless requires exactly one argument');
                }
                $condition = $args[0] instanceof \Closure ? $args[0]($options->scope) : $args[0];
                return static::ifvar($condition, (bool) ($options->hash['includeZero'] ?? false))
                    ? $options->inverse()
                    : $options->fn();
            },
            'each' => static function (mixed ...$args): string {
                /** @var HelperOptions $options */
                $options = array_pop($args);
                if (count($args) === 0) {
                    throw new \Exception('Must pass iterator to #each');
                }
                $items = self::getEachCollection($args[0], $options->scope);
                if (!$items) {
                    return $options->inverse();
                }
                $last = count($items) - 1;
                $ret = '';
                $i = 0;
                foreach ($items as $index => $value) {
                    $ret .= $options->fn($value === null ? static::nullContext() : $value, [
                        'data' => [
                            'first' => $i === 0,
                            'last' => $i === $last,
                            'key' => $index,
                            'index' => $i,
                        ],
                        'blockParams' => [$value, $index],
                    ]);
                    $i++;
                }
                return $ret;
            },
            'with' => static function (mixed ...$args): string {
                /** @var HelperOptions $options */
                $options = array_pop($args);
                if (count($args) !== 1) {
                    throw new \Exception('#with requires exactly one argument');
                }
                $context = $args[0] instanceof \Closure ? $args[0]($options->scope) : $args[0];
                if (static::ifvar($context)) {
                    return $options->fn($context, ['blockParams' => [$context]]);
                }
                return $options->inverse();
            },
            'lookup' => static function (mixed ...$args): mixed {
                array_pop($args); // remove HelperOptions
                if (count($args) !== 2) {
                    throw new \Exception('{{lookup}} requires 2 arguments');
                }
                [$obj, $key] = $args;
                if (is_array($obj)) {
                    return $obj[$key] ?? null;
                }
                if (is_object($obj)) {
                    return $obj->$key ?? null;
                }
                return null;
            },
            'log' => static function (mixed ...$args): string {
                array_pop($args); // remove HelperOptions
                error_log(var_export($args, true));
                return '';
            },
            'blockHelperMissing' => static function (mixed ...$args): string {
                /** @var HelperOptions $options */
                $options = array_pop($args);
                $context = $args[0] ?? null;
                $isList = is_array($context) && array_is_list($context);
                if ($isList || $context instanceof \Traversable) {
                    // Sequential arrays and Traversables: delegate to each.
                    return (Runtime::defaultHelpers()['each'])($context, $options);
                }
                if (is_array($context)) {
                    // Non-list (associative) arrays: render block once with context as scope.
                    return $context ? $options->fn($context) : $options->inverse();
                }
                if ($context === false || $context === null) {
                    return $options->inverse();
                }
                // true renders with the outer scope unchanged; any other truthy value becomes the new scope.
                return $context === true ? $options->fn() : $options->fn($context);
            },
        ];
    }

    /**
     * @return array<mixed>
     */
    private static function getEachCollection(mixed $value, mixed $scope): array
    {
        if ($value instanceof \Closure) {
            $value = $value($scope);
        }
        if (is_array($value)) {
            return $value;
        } elseif ($value instanceof \Traversable) {
            return iterator_to_array($value);
        }
        return [];
    }

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
     * Helper-or-variable lookup for bare {{identifier}} expressions.
     * Checks runtime helpers first, then context value, then helperMissing fallback.
     *
     * @param mixed $_this current rendering context
     */
    public static function hv(RuntimeContext $cx, string $name, mixed &$_this): mixed
    {
        $helper = $cx->helpers[$name] ?? null;
        if ($helper !== null) {
            if (isset($cx->blParam[0][$name])) {
                return $cx->blParam[0][$name];
            }
            return static::invokeInlineHelper($cx, $helper, $name, [], [], $_this);
        }
        if (is_array($_this) && array_key_exists($name, $_this)) {
            return static::dv($_this[$name]);
        }
        $helperMissing = $cx->helpers['helperMissing'] ?? null;
        if ($helperMissing !== null) {
            return static::invokeInlineHelper($cx, $helperMissing, $name, [], [], $_this);
        }
        return static::dv($_this[$name] ?? null);
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
     * For {{#var}} sections.
     *
     * @param array<array<mixed>|string|int>|string|int|bool|null|\Closure $v value for the section
     * @param array<array<mixed>|string|int>|string|int|null $in input data with current scope
     * @param \Closure $cb callback function to render child context
     * @param \Closure|null $else callback function to render child context when {{else}}
     */
    public static function sec(RuntimeContext $cx, mixed $v, mixed $in, \Closure $cb, ?\Closure $else = null, ?string $helperName = null): string
    {
        if ($helperName !== null && isset($cx->helpers[$helperName])) {
            return static::hbbch($cx, $helperName, [], [], [], $in, $cb, $else);
        }

        // Lambda functions in block position receive HelperOptions directly.
        // This must be checked before blockHelperMissing routing.
        if ($v instanceof \Closure) {
            $options = new HelperOptions(
                name: '',
                hash: [],
                blockParams: 0,
                scope: $in,
                data: $cx->data,
                cx: $cx,
                _this: $in,
                cb: $cb,
                inv: $else,
            );
            $result = $v($options);
            return static::applyBlockHelperMissing($cx, $result, $in, $cb, $else);
        }

        if ($helperName !== null && isset($cx->helpers['blockHelperMissing'])) {
            return static::hbbch($cx, 'blockHelperMissing', [$v], [], [], $in, $cb, $else, $helperName);
        }

        // Fallback for knownHelpersOnly mode (helperName is null).
        if ($else !== null && (is_array($v)) && !count($v)) {
            return $else($cx, $in);
        }

        if (is_array($v)) {
            $push = $in !== $v;
            if ($push) {
                $cx->scopes[] = $in;
            }
            $ret = $cb($cx, $v);
            if ($push) {
                array_pop($cx->scopes);
            }
            return $ret;
        }

        if ($v !== null && $v !== false) {
            return $cb($cx, $v === true ? $in : $v);
        }

        return $else !== null ? $else($cx, $in) : '';
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
            throw new \Exception("The partial $p could not be found");
        }

        $savedPartialId = $cx->partialId;
        $cx->partialId = ($p === '@partial-block') ? ($pid > 0 ? $pid : ($cx->partialId > 0 ? $cx->partialId - 1 : 0)) : $pid;
        $cx->partialDepth++;

        $result = $cx->partials[$pp]($cx, static::merge($v[0][0], $v[1]));
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
            $cx->partials[$p] = function (RuntimeContext $cx, mixed $in) use ($code, $outerPartialId): string {
                $cx->partialId = $outerPartialId;
                return $code($cx, $in);
            };
        } else {
            $cx->partials[$p] = $code;
        }
    }

    /**
     * For helper calls not registered at compile time: checks runtime helpers, then context closures.
     *
     * @param array<mixed> $positional
     * @param array<string, mixed> $hash
     * @param mixed $_this current rendering context for the helper
     */
    private static function invokeInlineHelper(RuntimeContext $cx, \Closure $helper, string $name, array $positional, array $hash, mixed &$_this): mixed
    {
        $options = new HelperOptions(
            name: $name,
            hash: $hash,
            blockParams: 0,
            scope: $_this,
            data: $cx->data,
            cx: $cx,
        );
        $args = $positional;
        $args[] = $options;
        return $helper(...$args);
    }

    /**
     * @param array<mixed> $positional
     * @param array<string, mixed> $hash
     * @param array<string> $blockParamNames
     */
    private static function invokeBlockHelper(RuntimeContext $cx, \Closure $helper, string $name, array $positional, array $hash, array $blockParamNames, mixed &$_this, ?\Closure $cb, ?\Closure $else): string
    {
        $data = &$cx->data;
        $options = new HelperOptions(
            name: $name,
            hash: $hash,
            blockParams: count($blockParamNames),
            scope: $_this,
            data: $data,
            cx: $cx,
            _this: $_this,
            cb: $cb,
            inv: $else,
            blockParamNames: $blockParamNames,
        );
        $args = $positional;
        $args[] = $options;
        return static::applyBlockHelperMissing($cx, $helper(...$args), $_this, $cb, $else);
    }

    /**
     * @param array<mixed> $positional
     * @param array<string, mixed> $hash
     */
    public static function dynhbch(RuntimeContext $cx, string $name, array $positional, array $hash, mixed &$_this): mixed
    {
        $helper = $cx->helpers[$name] ?? null;
        if ($helper !== null) {
            if (isset($cx->blParam[0][$name])) {
                return $cx->blParam[0][$name];
            }
            return static::invokeInlineHelper($cx, $helper, $name, $positional, $hash, $_this);
        }

        $fn = $_this[$name] ?? null;
        if ($fn instanceof \Closure) {
            return static::invokeInlineHelper($cx, $fn, $name, $positional, $hash, $_this);
        }

        $helperMissing = $cx->helpers['helperMissing'] ?? null;
        if ($helperMissing !== null) {
            return static::invokeInlineHelper($cx, $helperMissing, $name, $positional, $hash, $_this);
        }

        throw new \Exception('Missing helper: "' . $name . '"');
    }

    /**
     * For single custom helpers.
     *
     * @param string $ch the name of custom helper to be executed
     * @param array<mixed> $positional
     * @param array<string, mixed> $hash
     * @param mixed $_this current rendering context for the helper
     * @param string|null $logicalName when set, use as options.name instead of $ch
     */
    public static function hbch(RuntimeContext $cx, string $ch, array $positional, array $hash, mixed &$_this, ?string $logicalName = null): mixed
    {
        if (isset($cx->blParam[0][$ch])) {
            return $cx->blParam[0][$ch];
        }

        return static::invokeInlineHelper($cx, $cx->helpers[$ch], $logicalName ?? $ch, $positional, $hash, $_this);
    }

    /**
     * For block custom helpers.
     *
     * @param string $ch the name of custom helper to be executed
     * @param array<mixed> $positional
     * @param array<string, mixed> $hash
     * @param array<string> $blockParamNames
     * @param mixed $_this current rendering context for the helper
     * @param \Closure|null $cb callback function to render child context (null for inverted blocks)
     * @param \Closure|null $else callback function to render child context when {{else}}
     * @param string|null $logicalName when set, use as options.name instead of $ch
     */
    public static function hbbch(RuntimeContext $cx, string $ch, array $positional, array $hash, array $blockParamNames, mixed &$_this, ?\Closure $cb, ?\Closure $else = null, ?string $logicalName = null): mixed
    {
        return static::invokeBlockHelper($cx, $cx->helpers[$ch], $logicalName ?? $ch, $positional, $hash, $blockParamNames, $_this, $cb, $else);
    }

    /**
     * Like hbbch but for non-registered paths (pathed/depthed/scoped block calls with params).
     * @param array<mixed> $positional
     * @param array<string, mixed> $hash
     * @param array<string> $blockParamNames
     * @param array<string,array<mixed>|string|int> $_this current rendering context for the helper
     * @param \Closure $cb callback function to render child context
     * @param \Closure|null $else callback function to render child context when {{else}}
     */
    public static function dynhbbch(RuntimeContext $cx, string $name, mixed $callable, array $positional, array $hash, array $blockParamNames, mixed &$_this, \Closure $cb, ?\Closure $else = null): mixed
    {
        $helper = $cx->helpers[$name] ?? null;
        if ($helper !== null) {
            return static::invokeBlockHelper($cx, $helper, $name, $positional, $hash, $blockParamNames, $_this, $cb, $else);
        }

        if (!$callable instanceof \Closure) {
            $helperMissing = $cx->helpers['helperMissing'] ?? null;
            if ($helperMissing !== null) {
                return static::invokeBlockHelper($cx, $helperMissing, $name, $positional, $hash, $blockParamNames, $_this, $cb, $else);
            }
            throw new \Exception(
                $callable === null
                ? 'Missing helper: "' . $name . '"'
                : '"' . $name . '" is not a block helper function',
            );
        }

        $data = &$cx->data;

        $options = new HelperOptions(
            name: '',
            hash: $hash,
            blockParams: count($blockParamNames),
            scope: $_this,
            data: $data,
            cx: $cx,
            _this: $_this,
            cb: $cb,
            inv: $else,
            blockParamNames: $blockParamNames,
        );

        $args = $positional;
        $args[] = $options;
        $result = $callable(...$args);

        return static::applyBlockHelperMissing($cx, $result, $_this, $cb, $else);
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

        // $cb may be null when compiled from an inverted block helper (e.g. {{^helper}}...{{/helper}})
        return static::sec($cx, $result, $_this, $cb ?? self::emptyFn(), $else);
    }
}
