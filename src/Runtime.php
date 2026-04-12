<?php

declare(strict_types=1);

namespace DevTheorem\Handlebars;

use Closure;

/**
 * @internal
 * @phpstan-import-type RenderOptions from Handlebars
 */
final class Runtime
{
    /** @var array<string, Closure>|null */
    private static ?array $defaultHelpers = null;
    /** Parent RuntimeContext during a user-partial invocation, null at top level. */
    private static ?RuntimeContext $partialContext = null;

    /**
     * Default implementations of the built-in Handlebars helpers.
     * These are pre-registered in every runtime context and can be overridden.
     *
     * @return array<string, Closure>
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
                $condition = $args[0] instanceof Closure ? $args[0]($options->scope) : $args[0];
                return static::ifvar($condition, (bool) ($options->hash['includeZero'] ?? false))
                    ? $options->fn($options->scope)
                    : $options->inverse($options->scope);
            },
            'unless' => static function (mixed ...$args): string {
                if (count($args) !== 2) {
                    throw new \Exception('#unless requires exactly one argument');
                }
                /** @var HelperOptions $options */
                $options = $args[1];
                $condition = $args[0] instanceof Closure ? $args[0]($options->scope) : $args[0];
                return static::ifvar($condition, (bool) ($options->hash['includeZero'] ?? false))
                    ? $options->inverse($options->scope)
                    : $options->fn($options->scope);
            },
            'each' => static function (mixed $context, ?HelperOptions $options = null): string {
                if (!$options) {
                    throw new \Exception('Must pass iterator to #each');
                }
                if ($context instanceof Closure) {
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
                $context = $args[0] instanceof Closure ? $args[0]($options->scope) : $args[0];
                if (static::ifvar($context)) {
                    return $options->fn($context, ['blockParams' => [$context]]);
                }
                return $options->inverse($options->scope);
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
                if (count($args) === 1) {
                    // Bare variable lookup with no match — return null (mirrors HBS.js undefined).
                    return null;
                }
                /** @var HelperOptions $options */
                $options = end($args);
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
                    return $options->inverse($options->scope);
                }
                // true renders with the outer scope unchanged; any other truthy value becomes the new scope.
                return $options->fn($context === true ? $options->scope : $context);
            },
        ];
    }

    /**
     * Strict-mode key lookup: throw if $base is not an array or $key is absent.
     * Unlike the null-coalescing pattern, this allows null values when the key exists.
     */
    public static function strictLookup(mixed $base, string $key, string $original): mixed
    {
        if (!is_array($base) || !array_key_exists($key, $base)) {
            throw new \Exception('"' . $original . '" not defined');
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
     * Terminal .length lookup: returns count() for arrays (since PHP arrays have no native .length
     * property), an explicit 'length' key if present, or null for non-array bases.
     * When $strict is true, throws for any non-array base, mirroring HBS.js strict-mode behaviour.
     */
    public static function lookupLength(mixed $base, bool $strict = false): mixed
    {
        if (is_array($base)) {
            return array_key_exists('length', $base) ? $base['length'] : count($base);
        }
        if ($strict) {
            $desc = match (true) {
                $base === null => 'null',
                is_bool($base) => $base ? 'true' : 'false',
                is_int($base) || is_float($base) => (string) $base,
                is_string($base) => "\"$base\"",
                default => get_debug_type($base),
            };
            throw new \Exception("\"length\" not defined in $desc");
        }
        return null;
    }

    /**
     * Build a RuntimeContext from raw render options and compile-time partial closures.
     *
     * @param RenderOptions $options
     * @param array<string, Closure> $compiledPartials
     */
    public static function createContext(mixed $context, array $options, array $compiledPartials): RuntimeContext
    {
        $parentCx = self::$partialContext;

        if ($parentCx !== null) {
            // Partial context: reuse the parent's already-merged helpers and partials directly.
            // PHP copy-on-write ensures inlinePartials is only copied if the partial registers a new {{#* inline}} partial.
            // Inherit the parent's current data so @index, @key, etc. remain accessible inside partials.
            // Unset 'root' first to break the reference established by `$in = &$cx->data['root']` in the
            // calling template; a direct assignment would write through it and corrupt the caller's $in.
            $data = $parentCx->data;
            unset($data['root']);
            $data['root'] = $context;
            return new RuntimeContext(
                helpers: $parentCx->helpers,
                partials: $parentCx->partials,
                inlinePartials: $parentCx->inlinePartials,
                depths: $parentCx->depths,
                data: $data,
                partialBlock: $parentCx->partialBlock,
            );
        }

        $data = $options['data'] ?? [];
        $data['root'] = $data['root'] ?? $context;
        $extraHelpers = $options['helpers'] ?? [];
        return new RuntimeContext(
            helpers: $extraHelpers ? array_replace(Runtime::defaultHelpers(), $extraHelpers) : Runtime::defaultHelpers(),
            partials: array_replace($compiledPartials, $options['partials'] ?? []),
            data: $data,
        );
    }

    /**
     * Invoke $v without arguments if it is a Closure; otherwise return $v as-is.
     */
    public static function dv(mixed $v): mixed
    {
        return $v instanceof Closure ? $v() : $v;
    }

    /**
     * Context variable lookup without helper dispatch.
     * Looks up $name in $_this; if the value is a Closure, invokes it with $_this as a positional arg
     * (PHP equivalent of JS fn.call(context), where context binds as `this` with no positional args).
     * When $strict is true, throws for missing keys.
     *
     * @param mixed $_this current rendering context
     */
    public static function cv(mixed &$_this, string $name, bool $strict = false): mixed
    {
        $v = $strict ? static::strictLookup($_this, $name, $name) : ($_this[$name] ?? null);
        return $v instanceof Closure ? $v($_this) : $v;
    }

    /**
     * Helper-or-variable lookup for bare {{identifier}} expressions.
     * Checks runtime helpers first, then context value.
     * In non-strict mode: falls back to helperMissing when the context value is null.
     * When $assumeObjects is true, uses nullCheck for context lookup (throws on null context).
     * When $strict is true, uses strictLookup after the helper check (throws for missing keys; no helperMissing fallback).
     *
     * @param mixed $_this current rendering context
     */
    public static function hv(RuntimeContext $cx, string $name, mixed &$_this, bool $assumeObjects = false, bool $strict = false): mixed
    {
        $value = $cx->helpers[$name] ?? null;
        if ($value === null) {
            if ($strict) {
                $value = static::strictLookup($_this, $name, $name);
            } else {
                $value = $assumeObjects ? static::nullCheck($_this, $name) : ($_this[$name] ?? null);
                $value ??= $cx->helpers['helperMissing'];
            }
            if (!$value instanceof Closure) {
                return $value;
            }
        }
        return static::hbch($cx, $value, $name, [], [], $_this);
    }

    /**
     * Compat-mode helper-or-variable lookup for bare {{identifier}} expressions.
     * Like hv(), checks runtime helpers first; then uses compatLookup() to walk $cx->depths
     * before falling back to helperMissing. Only called from compat-compiled templates.
     *
     * @param mixed $_this current rendering context
     */
    public static function hvc(RuntimeContext $cx, string $name, mixed &$_this): mixed
    {
        $value = $cx->helpers[$name] ?? null;
        if ($value === null) {
            $value = static::compatLookup($cx, $_this, $name) ?? $cx->helpers['helperMissing'];
            if (!$value instanceof Closure) {
                return $value;
            }
        }
        return static::hbch($cx, $value, $name, [], [], $_this);
    }

    /**
     * Compat-mode depths-walk for a single key, equivalent to HBS.js container.lookup(depths, name).
     * Checks $in first, then walks $cx->depths from closest ancestor outward.
     * Returns null when the key cannot be resolved at any depth level.
     * Only called from compat-compiled templates.
     */
    public static function compatLookup(RuntimeContext $cx, mixed $in, string $name): mixed
    {
        if (is_array($in) && ($value = $in[$name] ?? null) !== null) {
            return $value;
        }
        for ($i = count($cx->depths) - 1; $i >= 0; $i--) {
            $ctx = $cx->depths[$i];
            if (is_array($ctx) && ($v = $ctx[$name] ?? null) !== null) {
                return $v;
            }
        }
        return null;
    }

    /**
     * Returns true or false following the semantics of {{#if}} and {{#unless}} in Handlebars.js.
     */
    public static function ifvar(mixed $v, bool $includeZero = false): bool
    {
        return $v !== null
            && $v !== false
            && ($includeZero || ($v !== 0 && $v !== 0.0))
            && $v !== ''
            && (!is_array($v) || $v)
            && (!$v instanceof \Stringable || (string) $v !== '');
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
     * For {{#var}} and {{^var}} sections.
     * Pass null for $cb when compiling an inverted section ({{^var}}): blockHelperMissing routes
     * truthy contexts through fn() (which returns '' when $cb is null) and falsy contexts through inverse().
     *
     * @param mixed $in input data with current scope
     * @param Closure|null $cb callback function to render child context; null for inverted sections
     * @param Closure|null $else callback function to render child context when {{else}}
     * @param array<mixed> $outerBlockParams outer block param stack, threaded into helper dispatch
     */
    public static function sec(RuntimeContext $cx, mixed $value, mixed $in, ?Closure $cb, ?Closure $else, ?string $helperName, array $outerBlockParams = []): string
    {
        $helper = $helperName !== null ? ($cx->helpers[$helperName] ?? null) : null;
        if ($helper !== null) {
            return static::hbbch($cx, $helper, $helperName, [], [], $in, $cb, $else, $outerBlockParams);
        }

        // Lambda functions in block position: simple-path identifiers ($helperName set) receive
        // HelperOptions so they can render fn/inverse; complex paths ($helperName null) are called
        // with no arguments, mirroring HBS.js which does not treat them as helper calls.
        if ($value instanceof Closure) {
            $result = $helperName !== null
                ? $value(new HelperOptions(scope: $in, data: $cx->data, cx: $cx, cb: $cb, inv: $else))
                : $value();
            return static::resolveBlockResult($cx, $result, $in, $cb, $else);
        }

        return static::hbbch($cx, $cx->helpers['blockHelperMissing'], $helperName ?? '', [$value], [], $in, $cb, $else, $outerBlockParams);
    }

    /**
     * Get merged context.
     */
    public static function merge(mixed $a, mixed $b): mixed
    {
        if (is_array($b)) {
            if ($a === null || is_int($a)) {
                return $b;
            } elseif (is_array($a)) {
                return array_replace($a, $b);
            } elseif (is_object($a)) {
                foreach ($b as $i => $v) {
                    $a->$i = $v;
                }
            }
        }
        return $a;
    }

    /**
     * Call {{> partial}}
     * @param array<string, mixed> $hash named hash overrides merged into the context
     * @param string $indent whitespace to prepend to each line of the partial's output
     * @param mixed $callerIn When compat mode is enabled, the caller's current $in pushed onto depths so
     *                        the partial can walk up to the caller's scope (mirrors HBS.js compat depths).
     */
    public static function p(RuntimeContext $cx, ?string $name, mixed $context, array $hash, string $indent, ?Closure $partialBlock = null, mixed $callerIn = null): string
    {
        $fn = match ($name) {
            '@partial-block' => $cx->partialBlock,
            // name can be null if a dynamic partial doesn't resolve to anything
            null => null,
            // inlinePartials (block-scoped {{#* inline}}) take precedence over partials (persistent),
            // mirroring Handlebars.js which checks options.partials before env.partials.
            default => $cx->inlinePartials[$name] ?? $cx->partials[$name] ?? null,
        };

        if ($fn === null) {
            $name ??= 'undefined'; // match HBS.js error
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
        // In compat mode, push the caller's current context onto depths before creating the partial
        // context so the partial can walk up to the caller's scope. createContext() (called inside
        // the partial closure) copies $parentCx->depths, so the push must happen here, before $fn().
        if ($callerIn !== null) {
            $cx->depths[] = $callerIn;
        }
        $prev = self::$partialContext;
        self::$partialContext = $cx;
        try {
            $result = $fn($context);
        } finally {
            self::$partialContext = $prev;
            if ($callerIn !== null) {
                array_pop($cx->depths);
            }
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
     * @param Closure $partial the compiled partial
     */
    public static function in(RuntimeContext $cx, string $name, Closure $partial): string
    {
        $cx->inlinePartials[$name] = $partial;
        return '';
    }

    /**
     * Resolve a helper by name: optionally check the helper registry, then the pre-resolved
     * callable, then fall back to helperMissing. Throws if a non-null, non-Closure value is found.
     * Pass $checkHelpers = false for scoped (./), depth (../), and data (@) paths, which resolve
     * from context only, matching HBS.js behaviour.
     */
    public static function resolveHelper(RuntimeContext $cx, string $name, mixed $callable, bool $checkHelpers): Closure
    {
        $helper = $checkHelpers ? ($cx->helpers[$name] ?? $callable) : $callable;
        if ($helper instanceof Closure) {
            return $helper;
        }
        if ($helper !== null) {
            throw new \Exception("Expected $name to be a function, got " . json_encode($helper));
        }
        return $cx->helpers['helperMissing'];
    }

    /**
     * Invoke a resolved helper Closure with positional params, hash, and a HelperOptions instance.
     * Used for known helpers and resolved helpers (direct hbch calls from generated code),
     * runtime-registered helpers (called from hv()), and built-in fallbacks like helperMissing/blockHelperMissing.
     *
     * @param array<mixed> $positional
     * @param array<string, mixed> $hash
     * @param mixed $_this current rendering context for the helper
     */
    public static function hbch(RuntimeContext $cx, Closure $helper, string $name, array $positional, array $hash, mixed &$_this): mixed
    {
        /** @var \WeakMap<Closure, int>|null $paramCounts */
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
                data: $cx->data,
                cx: $cx,
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
     * @param Closure|null $cb callback function to render child context (null for inverted blocks)
     * @param Closure|null $else callback function to render child context when {{else}}
     * @param array<mixed> $outerBlockParams outer block param stack for block params declared by the template
     */
    public static function hbbch(RuntimeContext $cx, Closure $helper, string $name, array $positional, array $hash, mixed &$_this, ?Closure $cb, ?Closure $else, array $outerBlockParams = [], int $blockParamCount = 0): string
    {
        $positional[] = new HelperOptions(
            scope: $_this,
            data: $cx->data,
            cx: $cx,
            name: $name,
            hash: $hash,
            blockParams: $blockParamCount,
            cb: $cb,
            inv: $else,
            outerBlockParams: $outerBlockParams,
        );
        return static::resolveBlockResult($cx, $helper(...$positional), $_this, $cb, $else);
    }

    /**
     * Resolve the return value of a block helper call:
     * pass through string/SafeString, stringify arrays, or delegate non-string values to blockHelperMissing.
     */
    private static function resolveBlockResult(RuntimeContext $cx, mixed $result, mixed $_this, ?Closure $cb, ?Closure $else): string
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
