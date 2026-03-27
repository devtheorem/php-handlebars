# PHP Handlebars

A blazing fast, spec-compliant PHP implementation of [Handlebars](https://handlebarsjs.com).

Originally based on [LightnCandy](https://github.com/zordius/lightncandy), but rewritten to enable
full Handlebars.js compatibility without excessive feature flags or performance tradeoffs.

PHP Handlebars compiles and executes complex templates up to 40% faster than LightnCandy:

| Library            | Compile time | Runtime | Total time | Peak memory usage |
|--------------------|--------------|---------|------------|-------------------|
| LightnCandy 1.2.6  | 5.2 ms       | 2.8 ms  | 8.0 ms     | 5.3 MB            |
| PHP Handlebars 1.2 | 3.7 ms       | 1.5 ms  | 5.2 ms     | 3.6 MB            |

_Tested on PHP 8.5 with the JIT enabled. See the `benchmark` branch to run the same test._

## Features

* Supports all Handlebars syntax and language features, including expressions, subexpressions, helpers,
partials, hooks, `@data` variables, whitespace control, and `.length` on arrays.
* Templates are parsed using [PHP Handlebars Parser](https://github.com/devtheorem/php-handlebars-parser),
which implements the same lexical analysis and AST grammar specification as Handlebars.js.
* Tested against the full [Handlebars.js spec](https://github.com/jbboehr/handlebars-spec).

## Installation
```
composer require devtheorem/php-handlebars
```

## Usage
```php
use DevTheorem\Handlebars\Handlebars;

$template = Handlebars::compile('Hello {{name}}!');

echo $template(['name' => 'World']); // Hello World!
```

## Precompilation
Templates can be pre-compiled to native PHP for later execution:

```php
use DevTheorem\Handlebars\Handlebars;

$code = Handlebars::precompile('<p>{{org.name}}</p>');

// save the compiled code into a PHP file
file_put_contents('render.php', "<?php $code");

// later import the template function from the PHP file
$template = require 'render.php';

echo $template(['org' => ['name' => 'DevTheorem']]);
```

## Compile Options

You can alter the template compilation by passing an `Options` instance as the second argument to `compile` or `precompile`.
For example, the `strict` option may be set to `true` to generate a template which will throw an exception for missing data:

```php
use DevTheorem\Handlebars\{Handlebars, Options};

$template = Handlebars::compile('Hi {{first}} {{last}}!', new Options(
    strict: true,
));

echo $template(['first' => 'John']); // Error: "last" not defined
```

### Available Options

* `knownHelpers`: Associative array (`helperName => bool`) of helpers that will be registered at runtime.
  The compiler uses this to emit direct helper calls instead of dynamic dispatch, which is faster and required when `knownHelpersOnly` is set.
  Built-in helpers (`if`, `unless`, `each`, `with`, `lookup`, `log`) are pre-populated as `true` and may be excluded by setting them to `false`.
  Setting `if` or `unless` to `false` also disables the inline ternary optimization and allows those helpers to be overridden at runtime.

* `knownHelpersOnly`: Restricts templates to only the helpers in `knownHelpers`, enabling further compile-time optimizations:
  block sections and bare `{{identifier}}` expressions skip the runtime helper table and use a direct context lookup,
  and any use of an unregistered helper throws a compile-time exception instead of falling back to dynamic dispatch.

* `noEscape`: Set to `true` to disable HTML escaping of output.

* `strict`: Run in strict mode. In this mode, templates will throw rather than silently ignore missing fields.
  This has the side effect of disabling inverse operations such as `{{^foo}}{{/foo}}`
  unless fields are explicitly included in the source object.

* `assumeObjects`: A looser alternative to `strict` mode. A null intermediate in a path
  (e.g. `foo` is null when resolving `foo.bar`) throws an exception, but a missing terminal key returns null silently.

* `preventIndent`: Prevents an indented partial call from indenting the entire partial output by the same amount.

* `ignoreStandalone`: Disables standalone tag removal.
  When set, blocks and partials that are on their own line will not remove the whitespace on that line.

* `explicitPartialContext`: Disables implicit context for partials.
  When enabled, partials that are not passed a context value will execute against an empty object.

* `partials`: An associative array of custom partial template strings (`name => template`).

* `partialResolver`: A closure that will be called at compile time for any partial not found in the `partials` array,
  and should return a template string for it.

## Runtime Options

`Handlebars::compile` returns a closure which can be invoked as `$template($context, $options)`.
The `$options` parameter takes an array of runtime options, accepting the following keys:

* `data`: An associative array of initial `@data` variables (e.g. `['version' => '1.0']` makes `@version` available in the template).

* `helpers`: An `array<string, \Closure>` of helpers to merge with the built-in helpers. Can also be used to override a built-in helper by using the same name.

* `partials`: An `array<string, \Closure>` of partial closures precompiled with `Handlebars::compile`.
  Useful when multiple templates share the same partials, and you want to avoid recompiling them for each template.

## Custom Helpers

Helper functions will be passed any arguments provided to the helper in the template.
If needed, a final `$options` parameter can be included which will be passed a `HelperOptions` instance.

For example, a custom `#equals` helper with JS equality semantics could be implemented as follows:

```php
use DevTheorem\Handlebars\{Handlebars, HelperOptions};

$template = Handlebars::compile('{{#equals my_var false}}Equal to false{{else}}Not equal{{/equals}}');
$helpers = [
    'equals' => function (mixed $a, mixed $b, HelperOptions $options) {
        // In JS, null is not equal to blank string or false or zero,
        // and when both operands are strings no coercion is performed.
        $equal = ($a === null || $b === null || is_string($a) && is_string($b))
            ? $a === $b
            : $a == $b;

        return $equal ? $options->fn() : $options->inverse();
    },
];
$runtimeOptions = ['helpers' => $helpers];

echo $template(['my_var' => 0], $runtimeOptions); // Equal to false
echo $template(['my_var' => 1], $runtimeOptions); // Not equal
echo $template(['my_var' => null], $runtimeOptions); // Not equal
```

### HelperOptions Properties

* `name` (readonly `string`): The helper name as it appeared in the template.
  Useful in `helperMissing`/`blockHelperMissing` hooks to identify which name was called.

* `hash` (readonly `array`): Key/value pairs passed as hash arguments in the template
  (e.g. `{{helper foo=1 bar="x"}}` produces `['foo' => 1, 'bar' => 'x']`).

* `blockParams` (readonly `int`): The number of block parameters declared by the helper call
  (e.g. `{{#helper as |a b|}}` produces `2`).

* `scope` (`mixed`): The current evaluation context (equivalent to `this` in a Handlebars.js helper).

* `data` (`array`): The current `@data` frame. `root` refers to the top-level context.
  `index`, `key`, `first`, and `last` are set by `{{#each}}` blocks. Can be read or modified inside a helper.

### HelperOptions Methods

* `fn(mixed $context = <current scope>, mixed $data = null): string`: Renders the block body.
  Pass a new context as `$context` to change what the block renders against (equivalent to `options.fn(newContext)` in JS).
  Pass a `$data` array with a `'data'` key to inject additional `@`-prefixed variables into the block,
  and/or a `'blockParams'` key containing an array of values to expose as block parameters.

* `inverse(mixed $context = null, mixed $data = null): string`: Renders the `{{else}}` / inverse block.
  Returns an empty string if no inverse block was provided.
  Accepts the same optional `$context` and `$data` arguments as `fn()`.

* `hasPartial(string $name): bool`: Returns `true` if a partial with the given name is registered.
  Useful alongside `registerPartial()` to implement lazy partial loading.

* `registerPartial(string $name, \Closure $partial): void`: Registers a compiled partial closure for the
  remainder of the render. The closure must be produced by `Handlebars::compile`.

> [!NOTE]  
> `isset($options->fn)` and `isset($options->inverse)` return `true` if the helper was called as a block,
> and `false` for inline helper calls.

## Hooks

If a custom helper named `helperMissing` is defined, it will be called when a mustache or a block-statement
is not a registered helper AND is not a property of the current evaluation context.

If a custom helper named `blockHelperMissing` is defined, it will be called when a block-expression calls
a helper that is not registered, even when the name matches a property in the current evaluation context.

For example:

```php
use DevTheorem\Handlebars\{Handlebars, HelperOptions};

$template = Handlebars::compile('{{foo 2 "value"}}
{{#person}}{{firstName}} {{lastName}}{{/person}}');

$helpers = [
    'helperMissing' => function (...$args) {
        $options = array_pop($args);
        return "Missing {$options->name}(" . implode(',', $args) . ')';
    },
    'blockHelperMissing' => function (mixed $context, HelperOptions $options) {
        return "'{$options->name}' not found. Printing block: {$options->fn($context)}";
    },
];

$data = ['person' => ['firstName' => 'John', 'lastName' => 'Doe']];
echo $template($data, ['helpers' => $helpers]);
```
Output:
> Missing foo(2,value)  
> 'person' not found. Printing block: John Doe

## String Escaping

If a custom helper is executed in a `{{ }}` expression, the return value will be HTML escaped.
When a helper is executed in a `{{{ }}}` expression, the original return value will be output directly.

Helpers may return a `DevTheorem\Handlebars\SafeString` instance to prevent escaping the return value.
When constructing the string that will be marked as safe, any external content should be properly escaped
using the `Handlebars::escapeExpression()` method to avoid potential security concerns.

## Data Frames

Block helpers that inject `@`-prefixed variables should create a child data frame using
`Handlebars::createFrame($options->data)`, add their variables to it, and pass it to `fn()` or `inverse()`
via the `data` key (e.g. `$options->fn($context, ['data' => $frame])`). This mirrors `Handlebars.createFrame()`
in Handlebars.js, isolating the helper's variables while still inheriting parent data such as `@root`.

## Missing Features

All syntax and language features from Handlebars.js 4.7.9 should work the same in PHP Handlebars,
with the following exceptions:

* Custom Decorators have not been implemented, as they are [deprecated in Handlebars.js](https://github.com/handlebars-lang/handlebars.js/blob/master/docs/decorators-api.md).
* The `data` and `compat` compilation options have not been implemented.
* The [runtime options to control prototype access](https://handlebarsjs.com/api-reference/runtime-options.html#options-to-control-prototype-access),
along with the `lookupProperty()` helper option method have not been implemented, since they aren't relevant for PHP. 
