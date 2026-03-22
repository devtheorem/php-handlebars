# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

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
  can now be caught in your own code for easier debugging (https://github.com/devtheorem/php-handlebars/issues/13).
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
- Fatal error with deeply nested `else if` using custom helper (https://github.com/devtheorem/php-handlebars/issues/2).
- Incorrect rendering of float values (https://github.com/devtheorem/php-handlebars/issues/11).
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
