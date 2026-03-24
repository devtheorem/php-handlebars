<?php

declare(strict_types=1);

namespace DevTheorem\Handlebars;

/**
 * @internal
 * @phpstan-import-type RenderOptions from Handlebars
 */
final class Runtime
{
    /** @var array<string, \Closure>|null */
    private static ?array $defaultHelpers = null;
    /** Parent RuntimeContext during a user-partial invocation, null at top level. */
    private static ?RuntimeContext $partialContext = null;

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
                if (count($args) !== 2) {
                    throw new \Exception('#if requires exactly one argument');
                }
                /** @var HelperOptions $options */
                $options = $args[1];
                $condition = $args[0] instanceof \Closure ? $args[0]($options->scope) : $args[0];
                return static::ifvar($condition, (bool) ($options->hash['includeZero'] ?? false))
                    ? $options->fn($options->scope)
                    : $options->inverse();
            },
            'unless' => static function (mixed ...$args): string {
                if (count($args) !== 2) {
                    throw new \Exception('#unless requires exactly one argument');
                }
                /** @var HelperOptions $options */
                $options = $args[1];
                $condition = $args[0] instanceof \Closure ? $args[0]($options->scope) : $args[0];
                return static::ifvar($condition, (bool) ($options->hash['includeZero'] ?? false))
                    ? $options->inverse()
                    : $options->fn($options->scope);
            },
            'each' => static function (mixed $context, ?HelperOptions $options = null): string {
                if (!$options) {
                    throw new \Exception('Must pass iterator to #each');
                }
                if ($context instanceof \Closure) {
                    $context = $context($options->scope);
                }
                if ($context instanceof \Traversable) {
                    $context = iterator_to_array($context);
                } elseif (!is_array($context)) {
                    $context = [];
                }
                return $options->iterate($context);
            },
            'with' => static function (mixed ...$args): string {
                if (count($args) !== 2) {
                    throw new \Exception('#with requires exactly one argument');
                }
                /** @var HelperOptions $options */
                $options = $args[1];
                $context = $args[0] instanceof \Closure ? $args[0]($options->scope) : $args[0];
                if (static::ifvar($context)) {
                    return $options->fn($context, ['blockParams' => [$context]]);
                }
                return $options->inverse();
            },
            'lookup' => static function (mixed $obj, string|int $key): mixed {
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
            'helperMissing' => static function (mixed ...$args): mixed {
                /** @var HelperOptions $options */
                $options = end($args);
                if (count($args) === 1 && !isset($options->fn)) {
                    // Bare variable lookup with no match — return null (mirrors HBS.js undefined).
                    return null;
                }
                throw new \Exception('Missing helper: "' . $options->name . '"');
            },
            'blockHelperMissing' => static function (mixed $context, HelperOptions $options): string {
                if ($context instanceof \Traversable) {
                    $context = iterator_to_array($context);
                }
                if (is_array($context)) {
                    return array_is_list($context) ? $options->iterate($context) : $options->fn($context);
                }
                if ($context === false || $context === null) {
                    return $options->inverse();
                }
                // true renders with the outer scope unchanged; any other truthy value becomes the new scope.
                return $options->fn($context === true ? $options->scope : $context);
            },
        ];
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
     * assumeObjects / strict-helper-arg key lookup: throw if $base is null (mirroring JS
     * TypeError for null/undefined property access); return null silently for a missing key on a
     * valid array (mirroring JS returning undefined for a missing object property); return null for
     * non-array non-null bases (mirroring JS returning undefined for property access on primitives).
     */
    public static function nullCheck(mixed $base, string $key): mixed
    {
        if ($base === null) {
            throw new \ErrorException("Cannot access property \"$key\" on null");
        }
        return is_array($base) ? ($base[$key] ?? null) : null;
    }

    /**
     * Build a RuntimeContext from raw render options and compile-time partial closures.
     *
     * @param RenderOptions $options
     * @param array<string, \Closure> $compiledPartials
     */
    public static function createContext(mixed $context, array $options, array $compiledPartials): RuntimeContext
    {
        $parentCx = self::$partialContext;
        $root = ['root' => $context];

        if ($parentCx !== null) {
            // Partial context: reuse the parent's already-merged helpers and partials directly.
            // PHP copy-on-write ensures partials is only copied if in() registers a new inline partial.
            // Inherit the parent's current frame so @index, @key, etc. remain accessible inside partials.
            // templateClosure will update frame['root'] to reference this partial's own data['root'].
            return new RuntimeContext(
                helpers: $parentCx->helpers,
                partials: $parentCx->partials,
                depths: $parentCx->depths,
                data: $root,
                frame: $parentCx->frame,
                partialBlock: $parentCx->partialBlock,
            );
        }

        $data = $options['data'] ?? [];
        return new RuntimeContext(
            helpers: array_replace(Runtime::defaultHelpers(), $options['helpers'] ?? []),
            partials: array_replace($compiledPartials, $options['partials'] ?? []),
            data: ['root' => $data['root'] ?? $context],
            frame: $data,
        );
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
     * Context variable lookup without helper dispatch.
     * Looks up $name in $_this; if the value is a Closure, invokes it with $_this as context.
     * Used when helper dispatch is unnecessary: knownHelpersOnly mode (the compiler has already
     * ruled out known helpers), and inlined if/unless conditions on single-segment paths.
     *
     * @param mixed $_this current rendering context
     */
    public static function cv(mixed &$_this, string $name): mixed
    {
        $v = is_array($_this) ? ($_this[$name] ?? null) : null;
        return $v instanceof \Closure ? $v($_this) : $v;
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
            return static::hbch($cx, $helper, $name, [], [], $_this);
        }
        if (is_array($_this) && array_key_exists($name, $_this)) {
            return static::dv($_this[$name]);
        }
        return static::hbch($cx, $cx->helpers['helperMissing'], $name, [], [], $_this);
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
     * Returns true if an inverse block {{^var}} should be rendered.
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
     * Inverted section with runtime helper check.
     */
    public static function isech(RuntimeContext $cx, mixed $v, mixed $in, \Closure $else, string $helperName): string
    {
        if (isset($cx->helpers[$helperName])) {
            return static::hbbch($cx, $cx->helpers[$helperName], $helperName, [], [], $in, null, $else);
        }
        return static::hbbch($cx, $cx->helpers['blockHelperMissing'], $helperName, [$v], [], $in, null, $else);
    }

    /**
     * HTML encode {{var}} just like Handlebars.js
     */
    public static function encq(mixed $var): string
    {
        if ($var instanceof SafeString) {
            return (string) $var;
        }

        return Handlebars::escapeExpression(static::raw($var));
    }

    /**
     * Get string representation for output
     */
    public static function raw(mixed $value): string
    {
        if ($value === true) {
            return 'true';
        }

        if ($value === false) {
            return 'false';
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $ret = '';
                foreach ($value as $vv) {
                    $ret .= static::raw($vv) . ',';
                }
                return substr($ret, 0, -1);
            } else {
                return '[object Object]';
            }
        }

        return "$value";
    }

    /**
     * For {{#var}} sections.
     *
     * @param mixed $in input data with current scope
     * @param \Closure $cb callback function to render child context
     * @param \Closure|null $else callback function to render child context when {{else}}
     */
    public static function sec(RuntimeContext $cx, mixed $value, mixed $in, \Closure $cb, ?\Closure $else = null, ?string $helperName = null): string
    {
        if ($helperName !== null && isset($cx->helpers[$helperName])) {
            return static::hbbch($cx, $cx->helpers[$helperName], $helperName, [], [], $in, $cb, $else);
        }

        // Lambda functions in block position receive HelperOptions directly.
        // This must be checked before blockHelperMissing routing.
        if ($value instanceof \Closure) {
            $result = $value(new HelperOptions(
                scope: $in,
                data: $cx->frame,
                cx: $cx,
                cb: $cb,
                inv: $else,
            ));
            return static::resolveBlockResult($cx, $result, $in, $cb, $else);
        }

        return static::hbbch($cx, $cx->helpers['blockHelperMissing'], $helperName ?? '', [$value], [], $in, $cb, $else);
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
     * @param string $name partial name
     * @param mixed $context the partial's context value
     * @param array<string, mixed> $hash named hash overrides merged into the context
     * @param string $indent whitespace to prepend to each line of the partial's output
     */
    public static function p(RuntimeContext $cx, string $name, mixed $context, array $hash, string $indent, ?\Closure $partialBlock = null): string
    {
        $fn = $name === '@partial-block' ? $cx->partialBlock : ($cx->partials[$name] ?? null);
        if ($fn === null) {
            throw new \Exception("The partial $name could not be found");
        }

        // Install a wrapper as the active @partial-block so the partial can invoke it via {{> @partial-block}}.
        // The wrapper temporarily restores the previously active block before calling $partialBlock,
        // allowing nested partial blocks to correctly resolve their own @partial-block.
        if ($partialBlock !== null) {
            $currentBlock = $cx->partialBlock;
            $cx->partialBlock = static function (mixed $blockContext) use ($partialBlock, $currentBlock): string {
                $callingCx = self::$partialContext;
                assert($callingCx !== null);
                $saved = $callingCx->partialBlock;
                $callingCx->partialBlock = $currentBlock;
                $result = $partialBlock($blockContext);
                $callingCx->partialBlock = $saved;
                return $result;
            };
        }

        $context = $hash ? static::merge($context, $hash) : $context;
        $prev = self::$partialContext;
        self::$partialContext = $cx;
        try {
            $result = $fn($context);
        } finally {
            self::$partialContext = $prev;
            if ($partialBlock !== null) {
                $cx->partialBlock = $currentBlock;
            }
        }

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
     * For {{#* inline "name"}} and {{#> partial}}fallback{{/partial}} blocks.
     *
     * @param string $name partial name
     * @param \Closure $partial the compiled partial
     */
    public static function in(RuntimeContext $cx, string $name, \Closure $partial): string
    {
        $cx->partials[$name] = $partial;
        return '';
    }

    /**
     * @param array<mixed> $positional
     * @param array<string, mixed> $hash
     */
    public static function dynhbch(RuntimeContext $cx, string $name, array $positional, array $hash, mixed &$_this): mixed
    {
        $helper = $cx->helpers[$name] ?? null;
        if ($helper !== null) {
            return static::hbch($cx, $helper, $name, $positional, $hash, $_this);
        }

        $fn = $_this[$name] ?? null;
        if ($fn instanceof \Closure) {
            return static::hbch($cx, $fn, $name, $positional, $hash, $_this);
        }

        if (!$positional && !$hash) {
            // No arguments: must be a helper call (e.g. sub-expression), not a property lookup.
            throw new \Exception('Missing helper: "' . $name . '"');
        }

        return static::hbch($cx, $cx->helpers['helperMissing'], $name, $positional, $hash, $_this);
    }

    /**
     * For single known helpers.
     *
     * @param array<mixed> $positional
     * @param array<string, mixed> $hash
     * @param mixed $_this current rendering context for the helper
     */
    public static function hbch(RuntimeContext $cx, \Closure $helper, string $name, array $positional, array $hash, mixed &$_this): mixed
    {
        /** @var \WeakMap<\Closure, int>|null $paramCounts */
        static $paramCounts = null;
        $paramCounts ??= new \WeakMap();

        $numParams = $paramCounts[$helper] ?? null;
        if ($numParams === null) {
            // Cache the number of parameters for the closure so HelperOptions doesn't have to be instantiated
            // when it isn't used. This can boost runtime performance by 20% for complex templates.
            $rf = new \ReflectionFunction($helper);
            $params = $rf->getParameters();
            $numParams = $params && end($params)->isVariadic() ? 0 : $rf->getNumberOfParameters();
            $paramCounts[$helper] = $numParams;
        }
        if ($numParams === 0 || $numParams > count($positional)) {
            $positional[] = new HelperOptions(
                scope: $_this,
                data: $cx->frame,
                name: $name,
                hash: $hash,
            );
        }

        return $helper(...$positional);
    }

    /**
     * For block custom helpers.
     *
     * @param array<mixed> $positional
     * @param array<string, mixed> $hash
     * @param mixed $_this current rendering context for the helper
     * @param \Closure|null $cb callback function to render child context (null for inverted blocks)
     * @param \Closure|null $else callback function to render child context when {{else}}
     * @param array<mixed> $outerBlockParams outer block param stack for block params declared by the template
     */
    public static function hbbch(RuntimeContext $cx, \Closure $helper, string $name, array $positional, array $hash, mixed &$_this, ?\Closure $cb, ?\Closure $else, int $blockParamCount = 0, array $outerBlockParams = []): string
    {
        $positional[] = new HelperOptions(
            scope: $_this,
            data: $cx->frame,
            name: $name,
            hash: $hash,
            blockParams: $blockParamCount,
            cx: $cx,
            cb: $cb,
            inv: $else,
            outerBlockParams: $outerBlockParams,
        );
        return static::resolveBlockResult($cx, $helper(...$positional), $_this, $cb, $else);
    }

    /**
     * Like hbbch but for non-registered paths (pathed/depthed/scoped block calls with params).
     * @param array<mixed> $positional
     * @param array<string, mixed> $hash
     * @param array<string,array<mixed>|string|int> $_this current rendering context for the helper
     * @param \Closure $cb callback function to render child context
     * @param \Closure|null $else callback function to render child context when {{else}}
     * @param array<mixed> $outerBlockParams outer block param stack for block params declared by the template
     */
    public static function dynhbbch(RuntimeContext $cx, string $name, mixed $callable, array $positional, array $hash, mixed &$_this, \Closure $cb, ?\Closure $else, int $blockParamCount, array $outerBlockParams): mixed
    {
        $helper = $cx->helpers[$name] ?? null;
        if ($helper !== null) {
            return static::hbbch($cx, $helper, $name, $positional, $hash, $_this, $cb, $else, $blockParamCount, $outerBlockParams);
        }

        if (!$callable instanceof \Closure) {
            return static::hbbch($cx, $cx->helpers['helperMissing'], $name, $positional, $hash, $_this, $cb, $else, $blockParamCount, $outerBlockParams);
        }

        return static::hbbch($cx, $callable, '', $positional, $hash, $_this, $cb, $else, $blockParamCount, $outerBlockParams);
    }

    /**
     * Resolve the return value of a block helper call:
     * pass through string/SafeString, stringify arrays, or delegate non-string values to blockHelperMissing.
     */
    private static function resolveBlockResult(RuntimeContext $cx, mixed $result, mixed $_this, ?\Closure $cb, ?\Closure $else): string
    {
        if (is_string($result) || $result instanceof SafeString) {
            return (string) $result;
        }

        // Arrays stringify like JS Array.prototype.toString(), regardless of fn block.
        if (is_array($result)) {
            return implode(',', $result);
        }

        if ($cb === null) {
            return ''; // can occur when compiled from an inverted block helper (e.g. {{^helper}}...{{/helper}})
        }
        return static::hbbch($cx, $cx->helpers['blockHelperMissing'], '', [$result], [], $_this, $cb, $else);
    }
}
