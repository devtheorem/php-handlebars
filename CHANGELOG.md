# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [2.0.0] Compat Mode - 2026-04-27

### Added
- `compat` compile option to enable recursive field lookup. When set to `true`, if a template variable is not
  found in the current scope it will automatically be looked up in parent scopes, matching Mustache's behavior.
- Official [Mustache spec](https://github.com/mustache/spec) tests are now run to verify `compat` functionality.
- `partialResolver` runtime option: a `Closure(string $name): ?Closure` called lazily the first time each
  unresolved partial is called. This replaces the previous compile-time `partialResolver` option.

### Changed
- Improved compiler performance and reduced memory usage by simplifying internal state.
- Optimized rendering of indented partials.

### Removed
- `partials` and `partialResolver` compile-time options. These options baked partials into the generated PHP closure,
  causing each partial to be recompiled and duplicated across every template that referenced it.
  Partials should now be supplied when invoking a template via the `partials` or `partialResolver` runtime options.

  **Upgrade:** if you were passing partials via `Options`, move them to the runtime options instead:
  ```php
  // Before
  $template = Handlebars::compile($source, new Options(
      partials: ['footer' => '<footer>...</footer>'],
      partialResolver: fn($name) => loadTemplate($name),
  ));
  echo $template($data);

  // After
  $template = Handlebars::compile($source);
  echo $template($data, [
      'partials' => ['footer' => Handlebars::compile('<footer>...</footer>')],
      'partialResolver' => fn($name) => Handlebars::compile(loadTemplate($name)),
  ]);
  ```
  This change makes it possible to precompile all partials in a directory, and then lazily import them
  on first use for optimal performance. See the example in the readme.

### Fixed
- Failure to invoke `@data` variables containing a closure when passed to `if` or `unless` helpers.
- Hoisted block closures leaked into the caller's scope when a precompiled template was loaded via `include`/`require`.
- Hash arguments passed to a partial were ignored when the partial was invoked in certain non-array contexts.
- Block helpers returning a nested or non-list array were not stringified correctly.
- Partials with literal names (`{{> true}}`, `{{> false}}`, `{{> null}}`, `{{> undefined}}`) were not
  resolved correctly: boolean names caused a type error, and `null`/`undefined` silently rendered nothing.


## [1.2.3] Hoisted Closures - 2026-04-10
### Changed
- Improved rendering performance by hoisting the closures for block bodies and `{{else}}` clauses,
  avoiding unnecessary re-allocation on repeated invocations.

### Fixed
- Failure to call ambiguous helper (e.g. `{{foo}}`) in `strict` mode.
- `blockParams` count in `HelperOptions` for inverse helper calls.


## [1.2.2] Faithful Dispatch - 2026-04-05

### Changed
- Better aligned compiler and runtime structure with Handlebars.js,
  fixing numerous edge cases related to helpers and `@data` variables.

### Fixed
- `../` expressions inside `{{else}}` blocks of `{{#if}}`, `{{#unless}}`, `{{#with}}`,
  and sections invoking `blockHelperMissing` resolved to the wrong context level.
- A missing helper called via a `@data` variable or multi-segment path in a subexpression failed to invoke `helperMissing`.
- A non-function context property used as a helper (e.g. `{{foo "arg"}}` where `foo` is not a closure)
  incorrectly called `helperMissing` rather than throwing a distinct error.
- No error thrown when calling a missing helper via a multi-segment path with arguments (e.g. `{{foo.bar "arg"}}`).
- Closures in context data could not be used as block helpers (e.g. `{{#fn}}...{{/fn}}` where `fn` is a closure).
- Closures in context data or `@data` variables failed to be passed `HelperOptions` as the last argument in certain cases.
- Templates with hash arguments on complex paths (e.g. `{{foo.bar arg=val}}`) were not compiled correctly.
- Closures in context data were not invoked when accessed via a multi-segment path (e.g. `{{foo.bar}}`),
  or via a literal path (e.g. `{{"foo"}}`) in `knownHelpersOnly` mode.
- `@data` variables incorrectly took priority over helpers with the same name.
- `knownHelpersOnly` was not enforced for `@data` expressions or complex paths used with arguments.


## [1.2.1] Optimal Simplification - 2026-04-02

### Changed
- Updated to PHP Handlebars Parser 2.0, which removed unnecessary options state from the parser and made it
  possible to reuse the same parser instance when compiling multiple templates (e.g. for runtime partials).
  The PHP Handlebars API hasn't changed, but it now performs better and has significantly lower memory
  usage when compiling two or more templates.
- `Options`, `HelperOptions`, and `SafeString` are now `final`, since there's no reason to ever extend them.

### Removed
- Unnecessary internal `StringObject` class.


## [1.2.0] Data Frames - 2026-03-30

### Added
- `Handlebars::createFrame()`: creates a child `@data` frame inheriting fields from a parent frame,
  equivalent to `Handlebars.createFrame()` in Handlebars.js.

### Changed
- To align with Handlebars.js, `@data` variables passed to `fn()` or `inverse()` by block helpers are
  no longer automatically merged with parent data and `@root`. For example, if a helper calls `fn()`
  with `['data' => ['index' => 0]]` as the second parameter, `@index` will now be the *only* `@data`
  variable inside the block. To set `@`-prefixed variables while still inheriting parent `@data`
  variables, call `Handlebars::createFrame($options->data)` to create an isolated child frame.
  Then assign new keys to it before passing it to the `data` option of `fn()` or `inverse()`.
- `Handlebars::escapeExpression()` now uses `strtr()` instead of `str_replace()` for better performance.

### Fixed
- Block param path lookups and literal path lookups (e.g. `{{"foo"}}`, `{{#"foo"}}`) in `strict`
  mode no longer incorrectly throw when the key exists but its value is `null`.
- Inline partials defined inside an `{{else}}` block no longer leak into the surrounding scope.


## [1.1.0] Dynamic Partial Resolution - 2026-03-26

### Added
- `HelperOptions::hasPartial()`: check whether a named partial is registered at runtime.
- `HelperOptions::registerPartial()`: register a compiled partial closure from within a helper,
  enabling the same lazy-loading pattern as `Handlebars.registerPartial()` in Handlebars.js
  ([#5](https://github.com/devtheorem/php-handlebars/issues/5), https://github.com/zordius/lightncandy/issues/296).

### Fixed
- Nested `{{> @partial-block}}` calls from runtime partials.
- Failover rendering for `{{> partial}}fallback{{/partial}}` blocks where the partial is also
  called conditionally earlier in the template.
- `isset($options->fn)` and `isset($options->inverse)` now correctly return `true` for all block
  helper calls, even when the block is inverted or lacks an `{{else}}` clause.
- Closures at complex paths without any arguments (e.g. `{{#obj.fn}}`) are no longer passed a
  `HelperOptions` argument (matching Handlebars.js behavior).
- Inverted sections with literal block paths (e.g. `{{^"foo"}}`) now correctly route through
  `blockHelperMissing`.
- With `knownHelpersOnly` enabled, inverted sections now correctly skip dispatch to unregistered runtime helpers.
- `../` expressions inside an `{{else}}` body now correctly resolve to the block helper's scope
  when there is no enclosing block context.
- `.length` lookup on block param variables and in `strict` mode.


## [1.0.1] Root SubExpression - 2026-03-24
### Fixed
- Support for sub-expressions that are `PathExpression` roots (e.g. `{{(my-helper foo).bar}}`).
- Compilation of multi-segment `if`/`unless` conditions ([#15](https://github.com/devtheorem/php-handlebars/issues/15)).
- Helper argument handling in `strict` mode.
- `assumeObjects` errors now align better with Handlebars.js.


## [1.0.0] AST Compiler - 2026-03-22

Rewrote the parser and compiler to use an abstract syntax tree, based on the same lexical analysis
and grammar specification as Handlebars.js. This eliminates a large class of edge cases and parsing
bugs that the old regex-based approach failed to handle correctly.

This release is 35-40% faster than v0.9.9 and LightnCandy at compiling and executing complex templates,
and uses almost 30% less memory. The code is also significantly simpler and easier to maintain.

### Added
- Support for nested inline partials.
- Support for closures in data and helper arguments.
- `helperMissing` and `blockHelperMissing` hooks: handle calls to unknown helpers with the same API
  as in Handlebars.js, replacing the old `helperResolver` option.
- `knownHelpers` compile option: tell the compiler which helpers will be available at runtime for
  more efficient execution (helper existence checks can be skipped).
- `assumeObjects` compile option: a subset of `strict` mode that generates optimized templates when
  the data inputs are known to be safe.
- Support for deprecated `{{person/firstname}}` path expressions for parity with Handlebars.js
  (avoid using this syntax in new code, though).

### Changed
- Custom helpers must now be passed at runtime when invoking a template (via the `helpers` runtime
  option key), rather than via the `Options` object passed to `compile` or `precompile`. This is a
  significant optimization, since it eliminates the overhead of reading and tokenizing PHP files to
  extract helper functions. It also enables sharing helper closures across multiple templates and
  renders, and removes limitations on what they can access and do
  (e.g. it resolves https://github.com/zordius/lightncandy/issues/342).
- Exceptions thrown by custom helpers are no longer caught and re-thrown, so the original exception
  can now be caught in your own code for easier debugging ([#13](https://github.com/devtheorem/php-handlebars/issues/13)).
- The `partialResolver` closure signature no longer receives an internal `Context` argument.
  Now only the partial name is passed.
- `knownHelpersOnly` now works as in Handlebars.js, and an exception will be thrown if the template
  uses a helper which is not in the `knownHelpers` list.
- Updated various error messages to align with those output by Handlebars.js.

### Removed
- `Options::$helpers`: instead pass custom helpers when invoking a template, using the `helpers` key
  in the runtime options array (the second argument to the template closure).
- `Options::$helperResolver`: use the `helperMissing` / `blockHelperMissing` runtime helpers instead.

### Fixed
- Fatal error with deeply nested `else if` using custom helper ([#2](https://github.com/devtheorem/php-handlebars/issues/2)).
- Incorrect rendering of float values ([#11](https://github.com/devtheorem/php-handlebars/issues/11)).
- Conditional `@partial-block` expressions.
- Support for `@partial-block` in nested partials (https://github.com/zordius/lightncandy/issues/292).
- Ability to precompile partials and pass them at runtime (https://github.com/zordius/lightncandy/issues/341).
- Fatal error when a string parameter to a partial includes curly braces (https://github.com/zordius/lightncandy/issues/316).
- Behavior when modifying root context in a custom helper (https://github.com/zordius/lightncandy/issues/350).
- Escaping of block params and partial names.
- Inline partials defined inside a `{{#with}}` or other block leaking out of that block's scope after it closes.
- Numerous other bugs related to scoping, block params, inverted block helpers, section iteration, and depth-relative paths.


## [0.9.9] Stringable Conditions - 2025-10-15
### Added
- Allow `Stringable` variables in `if` statements ([#8](https://github.com/devtheorem/php-handlebars/pull/8)).

### Fixed
- Raw lookup when key doesn't exist ([#3](https://github.com/devtheorem/php-handlebars/issues/3)).
- Spacing and undefined variable for each block in partial ([#7](https://github.com/devtheorem/php-handlebars/issues/7)).


## [0.9.8] String Escaping - 2025-05-20
### Added
- `Handlebars::escapeExpression()` method (equivalent to the `Handlebars.escapeExpression()` utility function in Handlebars.js).

### Removed
- Unnecessary `$escape` parameter on SafeString constructor.

### Fixed
- Nested else if validation (fixes https://github.com/zordius/lightncandy/issues/313).
- Escaping multiple double quotes (fixes https://github.com/zordius/lightncandy/issues/298).
- Single-quoted string parsing and compiling.


## [0.9.7] Resolvers - 2025-05-04
### Added
- `helperResolver` and `partialResolver` compile options for dynamic handling of partials and helpers.


## [0.9.6] Partial Indentation - 2025-04-20
### Fixed
- Indentation of nested partials (fixes https://github.com/zordius/lightncandy/issues/349).
- Parsing hash options containing line breaks (fixes https://github.com/zordius/lightncandy/issues/310).
- Parameter type error in strict mode.
- Parsing raw block helper params.


## [0.9.5] Block Parameter Parsing - 2025-03-30
### Fixed
- Parsing block parameters with extra surrounding whitespace (fixes https://github.com/zordius/lightncandy/issues/371).


## [0.9.4] String Arguments - 2025-03-23
### Fixed
- Parsing single-quoted string arguments (fixes https://github.com/zordius/lightncandy/issues/281, https://github.com/zordius/lightncandy/issues/357, https://github.com/zordius/lightncandy/issues/367).


## [0.9.3] Raw Block Parsing - 2025-03-20
### Fixed
- Correctly parse handlebars after raw block (fixes https://github.com/zordius/lightncandy/issues/344).


## [0.9.2] Arrow Function Helpers - 2025-03-19
### Added
- Support for arrow function helpers (fixes https://github.com/zordius/lightncandy/issues/366).

### Fixed
- Parse error when using length with `@root` (from https://github.com/zordius/lightncandy/issues/370).


## [0.9.1] Better Return Type - 2025-03-18
### Added
- Detailed return annotation for `compile()` method.


## [0.9.0] Modern Cleanup - 2025-03-18
Initial release after forking from LightnCandy 1.2.6.

### Added
- New `compile` method which takes a template string and options and returns an executable `Closure`.

### Changed
- PHP 8.2+ is now required.
- Replaced compile options array with `Options` object.
- Replaced helper options array with `HelperOptions` object.
- Renamed old `compile` method to `precompile`.
- Replaced `prepare` method with much faster `template` method, and removed dependency on URL include and filesystem write access.

### Fixed
- Rendering data in `{{else}}` of `{{#each}}` (from https://github.com/zordius/lightncandy/pull/369).
- Parsing strings with escaped quotes and parentheses (based on https://github.com/zordius/lightncandy/pull/358).
- Argument count for built-in helpers is now validated.

### Removed
- Custom autoloader.
- Used feature tracking.
- Option to change delimiters.
- `partialresolver` option.
- `compilePartial` method.
- `prepartial` callback option.
- `renderex` option to inject compiled code.
- Option to change runtime class.
- HTML documentation.
- Dozens of unnecessary feature flags.

[2.0.0]: https://github.com/devtheorem/php-handlebars/compare/v1.2.3...v2.0.0
[1.2.3]: https://github.com/devtheorem/php-handlebars/compare/v1.2.2...v1.2.3
[1.2.2]: https://github.com/devtheorem/php-handlebars/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/devtheorem/php-handlebars/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/devtheorem/php-handlebars/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/devtheorem/php-handlebars/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/devtheorem/php-handlebars/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/devtheorem/php-handlebars/compare/v0.9.9...v1.0.0
[0.9.9]: https://github.com/devtheorem/php-handlebars/compare/v0.9.8...v0.9.9
[0.9.8]: https://github.com/devtheorem/php-handlebars/compare/v0.9.7...v0.9.8
[0.9.7]: https://github.com/devtheorem/php-handlebars/compare/v0.9.6...v0.9.7
[0.9.6]: https://github.com/devtheorem/php-handlebars/compare/v0.9.5...v0.9.6
[0.9.5]: https://github.com/devtheorem/php-handlebars/compare/v0.9.4...v0.9.5
[0.9.4]: https://github.com/devtheorem/php-handlebars/compare/v0.9.3...v0.9.4
[0.9.3]: https://github.com/devtheorem/php-handlebars/compare/v0.9.2...v0.9.3
[0.9.2]: https://github.com/devtheorem/php-handlebars/compare/v0.9.1...v0.9.2
[0.9.1]: https://github.com/devtheorem/php-handlebars/compare/v0.9.0...v0.9.1
[0.9.0]: https://github.com/devtheorem/php-handlebars/tree/v0.9.0
