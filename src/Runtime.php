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
                return self::isEmpty($condition, (bool) ($options->hash['includeZero'] ?? false))
                    ? $options->inverse($options->scope)
                    : $options->fn($options->scope);
            },
            'unless' => static function (mixed ...$args): string {
                if (count($args) !== 2) {
                    throw new \Exception('#unless requires exactly one argument');
                }
                /** @var HelperOptions $options */
                $options = $args[1];
                $condition = $args[0] instanceof Closure ? $args[0]($options->scope) : $args[0];
                return self::isEmpty($condition, (bool) ($options->hash['includeZero'] ?? false))
                    ? $options->fn($options->scope)
                    : $options->inverse($options->scope);
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
                return self::isEmpty($context)
                    ? $options->inverse($options->scope)
                    : $options->fn($context, ['blockParams' => [$context]]);
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
     * When $strict is true, throws for any non-array base, mirroring HBS.js strict-mode behavior.
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
     * Build a RuntimeContext from raw render options.
     *
     * @param RenderOptions|array{_cx?: RuntimeContext} $options
     */
    public static function createContext(mixed $context, array $options): RuntimeContext
    {
        $parentCx = $options['_cx'] ?? null;
        $data = $options['data'] ?? [];

        if ($parentCx !== null) {
            // Partial context: reuse the parent's already-merged helpers, partials, and resolver directly.
            // PHP copy-on-write ensures inlinePartials is only copied if the partial registers a new {{#* inline}} partial.
            // invokePartial() always passes the caller's current data frame via options, so partials inherit @index, @key, etc.
            // Unset 'root' first to break the reference established by `$in = &$cx->data['root']` in the
            // calling template; a direct assignment would write through it and corrupt the caller's $in.
            unset($data['root']);
            $data['root'] = $context;
            return new RuntimeContext(
                helpers: $parentCx->helpers,
                partials: $parentCx->partials,
                partialResolver: $parentCx->partialResolver,
                inlinePartials: $parentCx->inlinePartials,
                depths: $parentCx->depths,
                data: $data,
            );
        }

        $data['root'] ??= $context;
        return new RuntimeContext(
            helpers: array_replace(Runtime::defaultHelpers(), $options['helpers'] ?? []),
            partials: $options['partials'] ?? [],
            partialResolver: $options['partialResolver'] ?? null,
            data: $data,
        );
    }

    /**
     * Invoke $v without arguments if it is a Closure; otherwise return $v as-is.
     * Equivalent to container.lambda() in Handlebars.js.
     */
    public static function lambda(mixed $v): mixed
    {
        return $v instanceof Closure ? $v() : $v;
    }

    /**
     * Context-only property lookup with lambda invocation.
     * Looks up $name in $_this; if the value is a Closure, invokes it with $_this as a positional arg
     * (PHP equivalent of JS fn.call(context), where context binds as `this` with no positional args).
     * When $strict is true, throws for missing keys.
     */
    public static function lookupValue(mixed &$_this, string $name, bool $strict = false): mixed
    {
        $v = $strict ? self::strictLookup($_this, $name, $name) : ($_this[$name] ?? null);
        return $v instanceof Closure ? $v($_this) : $v;
    }

    /**
     * Ambiguous bare {{identifier}}: checks runtime helpers first, then context value.
     * Equivalent to the invokeAmbiguous opcode in the Handlebars.js compiler.
     * In non-strict mode: falls back to helperMissing when the context value is null.
     * When $assumeObjects is true, uses nullCheck for context lookup (throws on null context).
     * When $strict is true, uses strictLookup after the helper check (throws for missing keys; no helperMissing fallback).
     * When $compat is true, uses compatLookup() to walk $cx->depths instead of looking up directly in $_this.
     */
    public static function invokeAmbiguous(RuntimeContext $cx, string $name, mixed &$_this, bool $assumeObjects, bool $strict, bool $compat): mixed
    {
        $value = $cx->helpers[$name] ?? null;
        if ($value === null) {
            if ($compat) {
                $value = self::compatLookup($cx, $_this, $name, $strict);
            } elseif ($strict) {
                $value = self::strictLookup($_this, $name, $name);
            } else {
                $value = $assumeObjects ? self::nullCheck($_this, $name) : ($_this[$name] ?? null);
            }
            if (!$strict) {
                $value ??= $cx->helpers['helperMissing'];
            }
            if (!$value instanceof Closure) {
                return $value;
            }
        }
        return self::invokeHelper($cx, $value, $name, [], [], $_this);
    }

    /**
     * Compat depths-walk for a single key, equivalent to HBS.js container.lookup / container.strictLookup.
     * Checks $in first, then walks $cx->depths from the closest ancestor outward.
     * Uses array_key_exists so explicit null values shadow parent contexts (Mustache semantics).
     * When $strict is true, throws for a key absent from all frames; otherwise returns null.
     */
    public static function compatLookup(RuntimeContext $cx, mixed $in, string $name, bool $strict = false): mixed
    {
        if (is_array($in) && array_key_exists($name, $in)) {
            return $in[$name];
        }
        for ($i = count($cx->depths) - 1; $i >= 0; $i--) {
            $ctx = $cx->depths[$i];
            if (is_array($ctx) && array_key_exists($name, $ctx)) {
                return $ctx[$name];
            }
        }
        return $strict ? self::strictLookup(null, $name, $name) : null;
    }

    /**
     * Returns true if $v is empty/falsy under Handlebars.js semantics (null, false, 0 unless
     * includeZero, empty string, empty array, or Stringable whose string form is '').
     * Equivalent to Utils.isEmpty() in Handlebars.js.
     */
    public static function isEmpty(mixed $v, bool $includeZero = false): bool
    {
        return $v === null
            || $v === false
            || (!$includeZero && ($v === 0 || $v === 0.0))
            || $v === ''
            || (is_array($v) && !$v)
            || ($v instanceof \Stringable && (string) $v === '');
    }

    /**
     * HTML encode {{var}} just like Handlebars.js.
     * Equivalent to container.escapeExpression() in Handlebars.js.
     */
    public static function escapeExpression(mixed $var): string
    {
        if ($var instanceof SafeString) {
            return (string) $var;
        }

        return Handlebars::escapeExpression(self::raw($var));
    }

    /**
     * Get string representation for output
     *
     * This function has been optimized for JIT via a microbenchmark with common inputs.
     */
    public static function raw(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

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
                    $ret .= self::raw($vv) . ',';
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
    public static function section(RuntimeContext $cx, mixed $value, mixed $in, ?Closure $cb, ?Closure $else, ?string $helperName, array $outerBlockParams = []): string
    {
        $helper = $helperName !== null ? ($cx->helpers[$helperName] ?? null) : null;
        if ($helper !== null) {
            return self::invokeBlockHelper($cx, $helper, $helperName, [], [], $in, $cb, $else, $outerBlockParams);
        }

        // Lambda functions in block position: simple-path identifiers ($helperName set) receive
        // HelperOptions so they can render fn/inverse; complex paths ($helperName null) are called
        // with no arguments, mirroring HBS.js which does not treat them as helper calls.
        if ($value instanceof Closure) {
            $result = $helperName !== null
                ? $value(new HelperOptions(scope: $in, data: $cx->data, cx: $cx, cb: $cb, inv: $else))
                : $value();
            return self::resolveBlockResult($cx, $result, $in, $cb, $else);
        }

        return self::invokeBlockHelper($cx, $cx->helpers['blockHelperMissing'], $helperName ?? '', [$value], [], $in, $cb, $else, $outerBlockParams);
    }

    /**
     * Merge context objects, equivalent to Utils.extend() in Handlebars.js.
     */
    private static function extend(mixed $a, mixed $b): mixed
    {
        if (is_array($b)) {
            return is_array($a) ? array_replace($a, $b) : $b;
        }
        return $a;
    }

    private static function resolveAndCachePartial(RuntimeContext $cx, string $name): ?Closure
    {
        if ($cx->partialResolver === null) {
            return null;
        }
        $fn = ($cx->partialResolver)($name);
        if ($fn !== null) {
            $cx->partials[$name] = $fn;
        }
        return $fn;
    }

    /**
     * @param array<mixed> $hash named hash overrides merged into the context
     * @param string $indent whitespace to prepend to each line of the partial's output
     * @param mixed $callerIn When compat mode is enabled, the caller's current $in pushed onto depths so
     *                        the partial can walk up to the caller's scope (mirrors HBS.js compat depths).
     */
    public static function invokePartial(RuntimeContext $cx, ?string $name, mixed $context, array $hash, string $indent, ?Closure $partialBlock = null, mixed $callerIn = null): string
    {
        $fn = match ($name) {
            // @partial-block is resolved from data, mirroring HBS.js resolvePartial
            '@partial-block' => $cx->data['partial-block'] ?? null,
            // name can be null if a dynamic partial doesn't resolve to anything
            null => null,
            // inlinePartials (block-scoped {{#* inline}}) take precedence over partials (persistent),
            // mirroring Handlebars.js which checks options.partials before env.partials.
            // Falls back to partialResolver for lazy loading; result is cached in $cx->partials.
            default => $cx->inlinePartials[$name] ?? $cx->partials[$name] ?? self::resolveAndCachePartial($cx, $name),
        };

        $context = $hash ? self::extend($context, $hash) : $context;

        if ($fn === null) {
            if ($partialBlock !== null && $name !== null) {
                // Partial not found; render the block body as failover (mirrors HBS.js behavior).
                return $partialBlock($context, ['data' => $cx->data, '_cx' => $cx]);
            }
            $name ??= 'undefined'; // match HBS.js error
            throw new \Exception("The partial $name could not be found");
        }

        // Install a wrapper as the active @partial-block so the partial can invoke it via {{> @partial-block}}.
        // Mirrors HBS.js invokePartial: the wrapper receives the current data frame, restores partial-block to
        // currentPartialBlock (captured from the closure), then calls the block content with that frame.
        if ($partialBlock !== null) {
            $currentBlock = $cx->data['partial-block'] ?? null;
            $cx->data['partial-block'] = static function (mixed $context = null, array $options = []) use ($partialBlock, $currentBlock): string {
                $options['data'] ??= [];
                $options['data']['partial-block'] = $currentBlock;
                return $partialBlock($context, $options);
            };
        }

        // In compat mode, push the caller's current context onto depths before creating the partial
        // context so the partial can walk up to the caller's scope. createContext() (called inside
        // the partial closure) copies $parentCx->depths, so the push must happen here, before $fn().
        if ($callerIn !== null) {
            $cx->depths[] = $callerIn;
        }
        try {
            $result = $fn($context, ['data' => $cx->data, '_cx' => $cx]);
        } finally {
            if ($callerIn !== null) {
                array_pop($cx->depths);
            }
            if ($partialBlock !== null) {
                $cx->data['partial-block'] = $currentBlock;
            }
        }

        if ($indent !== '') {
            if (str_ends_with($result, "\n")) {
                $result = $indent . str_replace("\n", "\n" . $indent, substr($result, 0, -1)) . "\n";
            } elseif ($result !== '') {
                $result = $indent . str_replace("\n", "\n" . $indent, $result);
            }
        }

        return $result;
    }

    /**
     * For {{#* inline "name"}} and {{#> partial}}fallback{{/partial}} blocks.
     */
    public static function setInlinePartial(RuntimeContext $cx, string $name, Closure $partial): string
    {
        $cx->inlinePartials[$name] = $partial;
        return '';
    }

    /**
     * Resolve a helper by name: optionally check the helper registry, then the pre-resolved
     * callable, then fall back to helperMissing. Throws if a non-null, non-Closure value is found.
     * Pass $checkHelpers = false for scoped (./), depth (../), and data (@) paths, which resolve
     * from context only, matching HBS.js behavior.
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
     * Equivalent to the invokeHelper/invokeKnownHelper opcodes in the Handlebars.js compiler.
     *
     * @param array<mixed> $positional
     * @param array<mixed> $hash
     */
    public static function invokeHelper(RuntimeContext $cx, Closure $helper, string $name, array $positional, array $hash, mixed &$_this): mixed
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
     * Invoke a resolved block helper Closure with fn/inverse callbacks and a HelperOptions instance.
     *
     * @param array<mixed> $positional
     * @param array<mixed> $hash
     * @param mixed $_this current rendering context for the helper
     * @param Closure|null $cb callback function to render child context (null for inverted blocks)
     * @param Closure|null $else callback function to render child context when {{else}}
     * @param array<mixed> $outerBlockParams outer block param stack for block params declared by the template
     */
    public static function invokeBlockHelper(RuntimeContext $cx, Closure $helper, string $name, array $positional, array $hash, mixed &$_this, ?Closure $cb, ?Closure $else, array $outerBlockParams = [], int $blockParamCount = 0): string
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
        return self::resolveBlockResult($cx, $helper(...$positional), $_this, $cb, $else);
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
            return self::raw($result);
        }

        if ($cb === null) {
            return ''; // can occur when compiled from an inverted block helper (e.g. {{^helper}}...{{/helper}})
        }
        return self::invokeBlockHelper($cx, $cx->helpers['blockHelperMissing'], '', [$result], [], $_this, $cb, $else);
    }
}
