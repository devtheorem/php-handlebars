# PHP Handlebars

A blazing fast, spec-compliant PHP implementation of [Handlebars](https://handlebarsjs.com).

Originally based on [LightnCandy](https://github.com/zordius/lightncandy), but rewritten to enable
full Handlebars.js compatibility without excessive feature flags or performance tradeoffs.

PHP Handlebars compiles and executes complex templates up to 40% faster than LightnCandy:

| Library            | Compile time | Runtime | Total time | Peak memory usage |
|--------------------|--------------|---------|------------|-------------------|
| LightnCandy 1.2.6  | 5.2 ms       | 2.8 ms  | 8.0 ms     | 5.3 MB            |
| PHP Handlebars 1.0 | 3.5 ms       | 1.6 ms  | 5.1 ms     | 3.6 MB            |

_Tested on PHP 8.5 with the JIT enabled. See the `benchmark` branch to run the same test._

## Features

* Supports all Handlebars syntax and language features, including expressions, subexpressions, helpers,
partials, hooks, and `@data` variables.
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

* `knownHelpers`: Associative array (`helperName => bool`) of helpers known to exist at template execution time.
  Passing this allows the compiler to optimize a number of cases.
  Builtin helpers are automatically included in this list and may be omitted by setting that value to `false`.
* `knownHelpersOnly`: Enable to allow further optimizations based on the known helpers list.
* `noEscape`: Enable to not HTML escape any content.
* `strict`: Run in strict mode. In this mode, templates will throw rather than silently ignore missing fields.
  This has the side effect of disabling inverse operations such as `{{^foo}}{{/foo}}`
  unless fields are explicitly included in the source object.
* `assumeObjects`: Removes object existence checks when traversing paths.
  This is a subset of strict mode that generates optimized templates when the data inputs are known to be safe.
* `preventIndent`: Prevent indented partial-call from indenting the entire partial output by the same amount.
* `ignoreStandalone`: Disables standalone tag removal.
  When set, blocks and partials that are on their own line will not remove the whitespace on that line.
* `explicitPartialContext`: Disables implicit context for partials.
  When enabled, partials that are not passed a context value will execute against an empty object.
* `partials`: Provide a `name => value` array of custom partial template strings.
* `partialResolver`: A closure which will be called for any partial not in the `partials` array to return a template for it.

## Runtime Options

`Handlebars::compile` returns a closure which can be invoked as `$template($context, $options)`.
The `$options` parameter takes an array of runtime options, accepting the following keys:

* `data`: An array to define custom `@variable` private variables.
* `helpers`: An `array<string, \Closure>` containing custom helpers to add to the built-in helpers.
* `partials`: An `array<string, \Closure>` containing partial functions precompiled with `Handlebars::compile`.
This is useful if multiple templates sharing the same partials need to be compiled and rendered, and you don't want
to recompile the same partials over and over for each template.

## Custom Helpers

Helper functions will be passed any arguments provided to the helper in the template.
If needed, a final `$options` parameter can be included which will be passed a `HelperOptions` instance.
This object contains properties for accessing `hash` arguments, `data`, and the current `scope`, `name`,
as well as `fn()` and `inverse()` methods to render the block and else contents, respectively.

For example, a custom `#equals` helper with JS equality semantics could be implemented as follows:

```php
use DevTheorem\Handlebars\{Handlebars, HelperOptions};

$template = Handlebars::compile('{{#equals my_var false}}Equal to false{{else}}Not equal{{/equals}}');
$options = [
    'helpers' => [
        'equals' => function (mixed $a, mixed $b, HelperOptions $options) {
            // In JS, null is not equal to blank string or false or zero,
            // and when both operands are strings no coercion is performed.
            $equal = ($a === null || $b === null || is_string($a) && is_string($b))
                ? $a === $b
                : $a == $b;

            return $equal ? $options->fn() : $options->inverse();
        },
    ],
];

echo $template(['my_var' => 0], $options); // Equal to false
echo $template(['my_var' => 1], $options); // Not equal
echo $template(['my_var' => null], $options); // Not equal
```

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

$options = [
    'helpers' => [
        'helperMissing' => function (...$args) {
            $options = array_pop($args);
            return "Missing {$options->name}(" . implode(',', $args) . ')';
        },
        'blockHelperMissing' => function (mixed $context, HelperOptions $options) {
            return "'{$options->name}' not found. Printing block: {$options->fn($context)}";
        },
    ],
];

echo $template(['person' => ['firstName' => 'John', 'lastName' => 'Doe']], $options);
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

## Missing Features

All syntax and language features from Handlebars.js 4.7.8 should work the same in PHP Handlebars,
with the following exceptions:

* Custom Decorators have not been implemented, as they are [deprecated in Handlebars.js](https://github.com/handlebars-lang/handlebars.js/blob/master/docs/decorators-api.md).
* The `data` and `compat` compilation options have not been implemented.
* The [runtime options to control prototype access](https://handlebarsjs.com/api-reference/runtime-options.html#options-to-control-prototype-access),
along with the `lookupProperty()` helper option method have not been implemented, since they aren't relevant for PHP. 
