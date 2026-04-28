# PHP Handlebars

A blazing fast, spec-compliant PHP implementation of [Handlebars](https://handlebarsjs.com).

The syntax of Handlebars is generally a superset of Mustache, so in most cases it is
possible to swap out Mustache for Handlebars and continue using the same templates.

## Features

* Supports all Handlebars syntax and language features, including expressions, subexpressions, helpers,
partials, hooks, `@data` variables, whitespace control, and `.length` on arrays.
* Templates are parsed using [PHP Handlebars Parser](https://github.com/devtheorem/php-handlebars-parser),
which implements the same lexical analysis and AST grammar specification as Handlebars.js.
* Tested against the [Handlebars.js spec](https://github.com/jbboehr/handlebars-spec)
  and the [Mustache spec](https://github.com/mustache/spec).

## Performance

PHP Handlebars started as a fork of [LightnCandy](https://github.com/zordius/lightncandy),
but has been rewritten with an AST-based parser and optimized runtime to enable full
Handlebars.js compatibility with better performance.

PHP Handlebars compiles and executes complex templates over 40% faster than LightnCandy, with 60% lower memory usage:

| Library            | Compile time | Runtime | Total time | Peak memory usage |
|--------------------|--------------|---------|------------|-------------------|
| LightnCandy 1.2.6  | 5.2 ms       | 2.8 ms  | 8.0 ms     | 5.3 MB            |
| PHP Handlebars 2.0 | 3.0 ms       | 1.4 ms  | 4.4 ms     | 1.8 MB            |

_Tested on PHP 8.5 with the JIT enabled. See the `benchmark` branch to run the same test._

## Installation
```
composer require devtheorem/php-handlebars
```

## Usage
```php
use DevTheorem\Handlebars\Handlebars;

$source = <<<'HBS'
    <p>Hi {{user.name}}, you have {{notifications.length}} new notification(s):</p>
    <ul>
    {{#notifications}}
        <li>{{count}} {{message}} ({{time}})</li>
    {{/notifications}}
    </ul>
    HBS;

$data = [
    'user' => ['name' => 'Jane'],
    'notifications' => [
        ['count' => 4, 'message' => 'new comments', 'time' => '5 min ago'],
        ['count' => 3, 'message' => 'new followers', 'time' => '1 hr ago'],
    ],
];

$template = Handlebars::compile($source);
echo $template($data);
```

Output:
```html
<p>Hi Jane, you have 2 new notification(s):</p>
<ul>
    <li>4 new comments (5 min ago)</li>
    <li>3 new followers (1 hr ago)</li>
</ul>
```

## Precompilation

Templates and partials can be precompiled to native PHP for later execution,
avoiding the overhead of parsing and compilation on each request.

**Build step** - compile all templates in a directory and cache the generated PHP:

```php
use DevTheorem\Handlebars\Handlebars;

$templateDir = 'templates';
$cacheDir = 'templateCache';

foreach (glob("$templateDir/*.hbs") ?: [] as $file) {
    $name = basename($file, '.hbs');
    $code = Handlebars::precompile(file_get_contents($file));
    file_put_contents("$cacheDir/$name.php", "<?php $code");
}
```

**Runtime** - load only needed templates, with precompiled partials resolved on demand:

```php
$template = require 'templateCache/page.php';

$data = ['title' => 'My Page', 'user' => ['name' => 'Jane']];
echo $template($data, [
    'partialResolver' => fn(string $name) => require "templateCache/$name.php",
]);
```

Each `{{> partial}}` call triggers the resolver on first use, and the result is cached for
the rest of that render. Only the partials that the page actually references are ever loaded.

> [!IMPORTANT]  
> Precompiled templates must be regenerated whenever PHP Handlebars is updated, as the generated
> PHP code depends on the current version of the runtime. The build step above should be part of
> a deployment process so that precompiled output does not need to be committed to source control.

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

* `compat`: Set to `true` to enable recursive field lookup. If a template variable is not found in the current scope,
  it will automatically be looked up in parent scopes, matching Mustache's default behavior.

> [!NOTE]  
> Recursive lookup has a runtime cost, so it is recommended that performance-sensitive
> operations should avoid `compat` mode and instead opt for explicit path references.

* `knownHelpers`: Associative array (`helperName => bool`) of helpers that will be registered at runtime.
  The compiler uses this to emit direct helper calls instead of dynamic dispatch,
  which is faster and required when `knownHelpersOnly` is set.
  Built-in helpers (`if`, `unless`, `each`, `with`, `lookup`, `log`) are pre-populated as `true` and may be excluded
  by setting them to `false`. Setting `if` or `unless` to `false` also disables the inline ternary optimization and
  allows those helpers to be overridden at runtime.

* `knownHelpersOnly`: Restricts templates to only the helpers in `knownHelpers`, enabling further compile-time optimizations:
  block sections and bare `{{identifier}}` expressions skip the runtime helper table and use a direct context lookup,
  and any use of an unknown helper throws a compile-time exception instead of falling back to dynamic dispatch.

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

## Runtime Options

`Handlebars::compile` returns a closure which can be invoked as `$template($context, $options)`.
The `$options` parameter takes an array of runtime options, accepting the following keys:

* `data`: An associative array of custom `@data` variables (e.g. `['version' => '1.0']` makes `@version` available in the template).

* `helpers`: An `array<string, Closure>` of helpers to merge with the built-in helpers.
  Can also be used to override a built-in helper by using the same name.

* `partials`: An `array<string, Closure>` of partials compiled with `Handlebars::compile`.
  Useful for eagerly providing a known set of partials.

* `partialResolver`: A `Closure(string $name): ?Closure` called lazily when a partial is referenced
  but not found in the `partials` map. Should return a compiled partial closure, or `null` if the partial
  does not exist. The resolved closure is cached for the remainder of the render, so each partial is loaded
  at most once per template invocation.

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

* `data` (`array`): The current `@data` frame. The `root` key refers to the top-level context.
  `index`, `key`, `first`, and `last` are set by `{{#each}}` blocks. Can be read or modified inside a helper.

### HelperOptions Methods

* `fn(mixed $context = <current scope>, mixed $data = null): string`: Renders the block body.
  Pass a new context as `$context` to change what the block renders against (equivalent to `options.fn(newContext)` in JS).
  Pass a `$data` array with a `'data'` key to inject `@`-prefixed variables into the block,
  and/or a `'blockParams'` key containing an array of values to expose as block parameters.

* `inverse(mixed $context = <current scope>, mixed $data = null): string`: Renders the `{{else}}` / inverse block.
  Returns an empty string if no inverse block was provided.
  Accepts the same optional `$context` and `$data` arguments as `fn()`.

* `hasPartial(string $name): bool`: Returns `true` if a partial with the given name is registered.
  Useful alongside `registerPartial()` to implement dynamic partial loading.

* `registerPartial(string $name, Closure $partial): void`: Registers a compiled partial closure for the
  remainder of the render. The closure can be produced via `Handlebars::compile`, or by importing a
  cached closure created with `Handlebars::precompile`.

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
Because `SafeString` bypasses the automatic HTML escaping that `{{ }}` applies, any user-supplied content
embedded in it must first be escaped with `Handlebars::escapeExpression()` to prevent XSS vulnerabilities.

## Data Frames

Block helpers that inject `@`-prefixed variables should create a child data frame using
`Handlebars::createFrame($options->data)`, add their variables to it, and pass it to `fn()` or `inverse()`
via the `data` key (e.g. `$options->fn($context, ['data' => $frame])`). This mirrors `Handlebars.createFrame()`
in Handlebars.js, isolating the helper's variables while still inheriting parent data such as `@root`.

## Missing Features

All syntax and language features from Handlebars.js 4.7.9 should work the same in PHP Handlebars,
with the following exceptions:

* Custom Decorators have not been implemented, as they are [deprecated in Handlebars.js](https://github.com/handlebars-lang/handlebars.js/blob/master/docs/decorators-api.md).
* The `data` compilation option has not been implemented.
* The [runtime options to control prototype access](https://handlebarsjs.com/api-reference/runtime-options.html#options-to-control-prototype-access),
along with the `lookupProperty()` helper option method have not been implemented, since they aren't relevant for PHP. 

## Mustache Compatibility

Handlebars is largely compatible with Mustache syntax, with a few notable differences:

- Handlebars does not perform recursive field lookup by default.
  The `compat` compile option must be set to enable this behavior.
- Alternative Mustache delimiters (e.g. `{{=<% %>=}}`) are not supported.
- Spaces are not allowed between the opening `{{` and a command character such as `#`, `/`, or `>`.
  For example, `{{> partial}}` works but `{{ > partial}}` does not.
