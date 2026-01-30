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

echo $template(['first' => 'John']); // Error: Runtime: [last] does not exist
```

**Available Options:**
* `knownHelpersOnly`: Enable to allow further optimizations based on the known helpers list.
* `noEscape`: Enable to not HTML escape any content.
* `strict`: Run in strict mode. In this mode, templates will throw rather than silently ignore missing fields.
* `preventIndent`: Prevent indented partial-call from indenting the entire partial output by the same amount.
* `ignoreStandalone`: Disables standalone tag removal. When set, blocks and partials that are on their own line will not remove the whitespace on that line.
* `explicitPartialContext`: Disables implicit context for partials. When enabled, partials that are not passed a context value will execute against an empty object.
* `helpers`: Provide a key => value array of custom helper functions.
* `helperResolver`: A closure which will be called for any helper not in the `helpers` array to return a function for it.
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

## String Escaping

If a custom helper is executed in a `{{ }}` expression, the return value will be HTML escaped.
When a helper is executed in a `{{{ }}}` expression, the original return value will be output directly.

Helpers may return a `DevTheorem\Handlebars\SafeString` instance to prevent escaping the return value.
When constructing the string that will be marked as safe, any external content should be properly escaped
using the `Handlebars::escapeExpression()` method to avoid potential security concerns.

## Detailed Feature list

Go https://handlebarsjs.com/ to see more details about each feature.

* Exact same CR/LF behavior as Handlebars.js
* Exact same 'true' or 'false' output as Handlebars.js
* Exact same '[object Object]' output or join(',' array) output as Handlebars.js
* `{{{value}}}` or `{{&value}}` : raw variable
* `{{value}}` : HTML escaped variable
* `{{path.to.value}}` : dot notation
* `{{.}}` or `{{this}}` : current context
* `{{#value}}` : section
   * false, undefined and null will skip the section
   * true will run the section with original scope
   * All others will run the section with new scope (includes 0, 1, -1, '', '1', '0', '-1', 'false', Array, ...)
* `{{/value}}` : end section
* `{{^value}}` : inverted section
   * false, undefined and null will run the section with original scope
   * All others will skip the section (includes 0, 1, -1, '', '1', '0', '-1', 'false', Array, ...)
* `{{! comment}}` : comment
* `{{!-- comment {{ or }} --}}` : extended comment that can contain }} or {{ .
* `{{#each var}}` : each loop
* `{{#each}}` : each loop on `{{.}}`
* `{{/each}}` : end loop
* `{{#each bar as |foo|}}` : echo loop on bar and set the value as foo.
* `{{#each bar as |foo moo|}}` : echo loop on bar, set the value as foo, set the index as moo.
* `{{#if var}}` : run if logic with original scope (null, false, empty Array and '' will skip this block)
* `{{#if foo includeZero=true}}` : result as true when foo === 0
* `{{/if}}` : end if
* `{{else}}` or `{{^}}` : run else logic, should between `{{#if var}}` and `{{/if}}` ; or between `{{#unless var}}` and `{{/unless}}`; or between `{{#foo}}` and `{{/foo}}`; or between `{{#each var}}` and `{{/each}}`; or between `{{#with var}}` and `{{/with}}`.
* `{{#if foo}} ... {{else if bar}} ... {{/if}}` : chained if else blocks
* `{{#unless var}}` : run unless logic with original scope (null, false, empty Array and '' will render this block)
* `{{#unless foo}} ... {{else if bar}} ... {{/unless}}` : chained unless else blocks
* `{{#unless foo}} ... {{else unless bar}} ... {{/unless}}` : chained unless else blocks
* `{{#foo}} ... {{else bar}} ... {{/foo}}` : custom helper chained else blocks
* `{{#with var}}` : change context scope. If the var is false or an empty array, skip included section.
* `{{#with bar as |foo|}}` : change context to bar and set the value as foo.
* `{{lookup foo bar}}` : lookup foo by value of bar as key.
* `{{../var}}` : parent template scope.
* `{{>file}}` : partial; include another template inside a template.
* `{{>file foo}}` : partial with new context
* `{{>file foo bar=another}}` : partial with new context which mixed with followed key value
* `{{>(helper) foo}}` : include dynamic partial by name provided from a helper
* `{{@index}}` : references to current index in a `{{#each}}` loop on an array.
* `{{@key}}` : references to current key in a `{{#each}}` loop on an object.
* `{{@root}}` : references to root context.
* `{{@first}}` : true when looping at first item.
* `{{@last}}` : true when looping at last item.
* `{{@root.path.to.value}}` : references to root context then follow the path.
* `{{@../index}}` : access to parent loop index.
* `{{@../key}}` : access to parent loop key.
* `{{~any_valid_tag}}` : Space control, remove all previous spacing (includes CR/LF, tab, space; stop on any none spacing character)
* `{{any_valid_tag~}}` : Space control, remove all next spacing (includes CR/LF, tab, space; stop on any none spacing character)
* `{{{helper var}}}` : Execute custom helper then render the result
* `{{helper var}}` : Execute custom helper then render the HTML escaped result
* `{{helper "str"}}` or `{{helper 'str'}}` : Execute custom helper with string arguments
* `{{helper 123 null true false undefined}}` : Pass number, true, false, null or undefined into helper
* `{{helper name1=var name2=var2}}` : Execute custom helper with named arguments
* `{{#helper ...}}...{{/helper}}` : Execute block custom helper
* `{{helper (helper2 foo) bar}}` : Execute custom helpers as subexpression
* `{{{{raw_block}}}} {{will_not_parsed}} {{{{/raw_block}}}}` : Raw block
* `{{#> foo}}block{{/foo}}` : Partial block, provide `foo` partial default content
* `{{#> @partial-block}}` : access partial block content inside a partial
* `{{#*inline "partial_name"}}...{{/inline}}` : Inline partial, provide a partial and overwrite the original one.
* `{{log foo}}` : output value to stderr for debug.
