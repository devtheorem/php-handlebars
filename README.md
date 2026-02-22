# PHP Handlebars

A blazing fast, spec-compliant PHP implementation of [Handlebars](https://handlebarsjs.com/).

Originally based on [LightnCandy](https://github.com/zordius/lightncandy), but rewritten to focus on
more robust Handlebars.js compatibility without the need for excessive feature flags.

## Features

* Compile templates to pure PHP code.
* Templates are parsed using [PHP Handlebars Parser](https://github.com/devtheorem/php-handlebars-parser),
which implements the same lexical analysis and grammar specification as Handlebars.js.
* Tested against the [Handlebars.js spec](https://github.com/jbboehr/handlebars-spec).

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

echo $template(['first' => 'John']); // Error: Runtime: last does not exist
```

**Available Options:**
* `knownHelpersOnly`: Enable to allow further optimizations based on the known helpers list.
* `noEscape`: Enable to not HTML escape any content.
* `strict`: Run in strict mode. In this mode, templates will throw rather than silently ignore missing fields.
* `assumeObjects`: Removes object existence checks when traversing paths. This is a subset of strict mode that generates optimized templates when the data inputs are known to be safe.
* `preventIndent`: Prevent indented partial-call from indenting the entire partial output by the same amount.
* `ignoreStandalone`: Disables standalone tag removal. When set, blocks and partials that are on their own line will not remove the whitespace on that line.
* `explicitPartialContext`: Disables implicit context for partials. When enabled, partials that are not passed a context value will execute against an empty object.
* `helpers`: Provide a key => value array of custom helper functions.
* `partials`: Provide a key => value array of custom partial templates.
* `partialResolver`: A closure which will be called for any partial not in the `partials` array to return a template for it.

## Custom Helpers

Helper functions will be passed any arguments provided to the helper in the template.
If needed, a final `$options` parameter can be included which will be passed a `HelperOptions` instance.
This object contains properties for accessing `hash` arguments, `data`, and the current `scope`, as well as
`fn()` and `inverse()` methods to render the block and else contents, respectively.

For example, a custom `#equals` helper with JS equality semantics could be implemented as follows:

```php
use DevTheorem\Handlebars\{Handlebars, HelperOptions, Options};

$template = Handlebars::compile('{{#equals my_var false}}Equal to false{{else}}Not equal{{/equals}}', new Options(
    helpers: [
        'equals' => function (mixed $a, mixed $b, HelperOptions $options) {
            $jsEquals = function (mixed $a, mixed $b): bool {
                if ($a === null || $b === null) {
                    // in JS, null is not equal to blank string or false or zero
                    return $a === $b;
                }

                return $a == $b;
            };

            return $jsEquals($a, $b) ? $options->fn() : $options->inverse();
        },
    ],
));

echo $template(['my_var' => 0]); // Equal to false
echo $template(['my_var' => 1]); // Not equal
echo $template(['my_var' => null]); // Not equal
```

## Hooks

If a custom helper named `helperMissing` is defined, it will be called when a mustache or a block-statement
is not a registered helper AND is not a property of the current evaluation context.

If a custom helper named `blockHelperMissing` is defined, it will be called when a block-expression calls
a helper that is not registered, even when the name matches a property in the current evaluation context.

For example:

```php
use DevTheorem\Handlebars\{Handlebars, HelperOptions, Options};

$templateStr = '{{foo 2 "value"}}
{{#person}}{{firstName}} {{lastName}}{{/person}}';

$template = Handlebars::compile($templateStr, new Options(
    helpers: [
        'helperMissing' => function (...$args) {
            $options = array_pop($args);
            return "Missing {$options->name}(" . implode(',', $args) . ')';
        },
        'blockHelperMissing' => function (mixed $context, HelperOptions $options) {
            return "'{$options->name}' not found. Printing block: {$options->fn($context)}";
        },
    ],
));

echo $template(['person' => ['firstName' => 'John', 'lastName' => 'Doe']]);
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

## Missing features

All syntax from Handlebars.js 4.7.8 should work the same in this implementation, with the following exception:
* Decorators ([deprecated in Handlebars.js](https://github.com/handlebars-lang/handlebars.js/blob/master/docs/decorators-api.md)) have not been implemented.
