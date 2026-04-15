<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Handlebars;
use DevTheorem\Handlebars\HelperOptions;
use DevTheorem\Handlebars\Options;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type RenderTest array{
 *     template: string, options?: Options, helpers?: array<string, \Closure>, data?: array<mixed>, expected: string,
 * }
 * @phpstan-type ErrorCase array{template: string, options?: Options, expected: string}
 */
class ErrorTest extends TestCase
{
    /**
     * @param array<string, \Closure> $helpers
     * @param array<mixed> $data
     */
    #[DataProvider("renderErrorProvider")]
    public function testRenderingException(
        string $template,
        string $expected,
        ?Options $options = null,
        array $helpers = [],
        array $data = [],
    ): void {
        $php = Handlebars::precompile($template, $options ?? new Options());
        $renderer = Handlebars::template($php);
        try {
            $result = $renderer($data, ['helpers' => $helpers]);
            $this->fail("Expected exception: {$expected}\nRendered: $result\nPHP code:\n$php");
        } catch (\Exception $e) {
            $this->assertSame($expected, $e->getMessage(), "PHP code:\n$php");
        }
    }

    /**
     * @return array<string, RenderTest>
     */
    public static function renderErrorProvider(): array
    {
        return [
            'missing partial' => [
                'template' => '{{>not_found}}',
                'expected' => "The partial not_found could not be found",
            ],
            'partial-block not found in nested partials' => [
                'template' => "{{#> testPartial}}\n  {{#> innerPartial}}\n   {{> @partial-block}}\n  {{/innerPartial}}\n{{/testPartial}}",
                'options' => new Options(
                    partials: [
                        'testPartial' => 'testPartial => {{> @partial-block}} <=',
                        'innerPartial' => 'innerPartial -> {{> @partial-block}} <-',
                    ],
                ),
                'expected' => "The partial @partial-block could not be found",
            ],
            'partial-block not found at top level' => [
                'template' => '{{> @partial-block}}',
                'expected' => "The partial @partial-block could not be found",
            ],
            'strict mode missing nested property' => [
                'template' => '{{foo.bar}}',
                'options' => new Options(strict: true),
                'data' => ['foo' => []],
                'expected' => '"foo.bar" not defined',
            ],
            'strict mode .length on null' => [
                'template' => '{{foo.bar.length}}',
                'options' => new Options(strict: true),
                'data' => ['foo' => ['bar' => null]],
                'expected' => '"length" not defined in null',
            ],
            'strict mode .length on false' => [
                'template' => '{{foo.length}}',
                'options' => new Options(strict: true),
                'data' => ['foo' => false],
                'expected' => '"length" not defined in false',
            ],
            'strict mode .length on string' => [
                'template' => '{{foo.length}}',
                'options' => new Options(strict: true),
                'data' => ['foo' => 'hello'],
                'expected' => '"length" not defined in "hello"',
            ],
            'strict mode .length on integer' => [
                'template' => '{{foo.length}}',
                'options' => new Options(strict: true),
                'data' => ['foo' => 42],
                'expected' => '"length" not defined in 42',
            ],
            'strict mode null property access in if' => [
                'template' => '{{#if foo.bar}}bad{{else}}OK{{/if}}',
                'options' => new Options(strict: true),
                'expected' => 'Cannot access property "bar" on null',
            ],
            'strict mode missing variable' => [
                'template' => '{{foo}}',
                'options' => new Options(strict: true),
                'expected' => '"foo" not defined',
            ],
            'strict mode should override helperMissing' => [
                'template' => '{{foo}}',
                'options' => new Options(strict: true),
                'helpers' => ['helperMissing' => fn() => 'bad'],
                'expected' => '"foo" not defined',
            ],
            'strict mode should override blockHelperMissing' => [
                'template' => '{{#foo}}OK{{/foo}}',
                'options' => new Options(strict: true),
                'helpers' => ['blockHelperMissing' => fn() => 'bad'],
                'expected' => '"foo" not defined',
            ],
            'strict mode missing block variable' => [
                'template' => '{{#foo}}OK{{/foo}}',
                'options' => new Options(strict: true),
                'expected' => '"foo" not defined',
            ],
            'strict mode missing unescaped variable' => [
                'template' => '{{{foo}}}',
                'options' => new Options(strict: true),
                'expected' => '"foo" not defined',
            ],
            'helperMissing should not be called when the identifier is a non-closure context property' => [
                'template' => '{{foo "Hello"}}',
                'data' => ['foo' => 'test'],
                'helpers' => [
                    'helperMissing' => fn($arg) => "$arg helperMissing",
                ],
                'expected' => 'Expected foo to be a function, got "test"',
            ],
            'helperMissing should not be called for blocks when the identifier is a non-closure context property' => [
                'template' => '{{#foo "Hello"}}content{{/foo}}',
                'data' => ['foo' => 'value'],
                'helpers' => [
                    'helperMissing' => fn($arg) => "$arg helperMissing",
                ],
                'expected' => 'Expected foo to be a function, got "value"',
            ],
            'inline helper fn() unsupported' => [
                'template' => '{{foo}}',
                'helpers' => [
                    'foo' => fn(HelperOptions $options) => $options->fn(),
                ],
                'expected' => 'fn() is not supported for inline helpers',
            ],
            'inline helper inverse() unsupported' => [
                'template' => '{{foo}}',
                'helpers' => [
                    'foo' => fn(HelperOptions $options) => $options->inverse(),
                ],
                'expected' => 'inverse() is not supported for inline helpers',
            ],
            'helper exception propagates' => [
                'template' => '{{foo}}',
                'helpers' => [
                    'foo' => function () {
                        throw new \Exception('Expect the unexpected');
                    },
                ],
                'expected' => 'Expect the unexpected',
            ],
            'callable strings in data should not be treated as functions' => [
                'template' => "{{#foo.bar 'arg'}}{{/foo.bar}}",
                'data' => ['foo' => ['bar' => 'strlen']],
                'expected' => 'Expected foo.bar to be a function, got "strlen"',
            ],
            'missing helper typeof' => [
                'template' => '{{typeof hello}}',
                'expected' => 'Missing helper: "typeof"',
            ],
            'missing multi-part helper' => [
                'template' => '{{foo.bar "arg"}}',
                'expected' => 'Missing helper: "foo.bar"',
            ],
            'missing block helper test' => [
                'template' => '{{#test foo}}{{/test}}',
                'expected' => 'Missing helper: "test"',
            ],
            'missing helper in subexpression' => [
                'template' => '{{test_join (foo bar)}}',
                'helpers' => [
                    'test_join' => fn($input) => join('.', $input),
                ],
                'expected' => 'Missing helper: "foo"',
            ],
            'missing helper in dynamic partial' => [
                'template' => '{{> (foo) bar}}',
                'expected' => 'The partial undefined could not be found',
            ],
            'with requires one argument' => [
                'template' => '{{#with}}OK!{{/with}}',
                'expected' => '#with requires exactly one argument',
            ],
            'if requires one argument' => [
                'template' => '{{#if}}OK!{{/if}}',
                'expected' => '#if requires exactly one argument',
            ],
            'unless requires one argument' => [
                'template' => '{{#unless}}OK!{{/unless}}',
                'expected' => '#unless requires exactly one argument',
            ],
            'each requires iterator argument' => [
                'template' => '{{#each}}OK!{{/each}}',
                'expected' => 'Must pass iterator to #each',
            ],
            'compat+strict: multi-part path, missing property should throw' => [
                'template' => '{{foo.bar}}',
                'options' => new Options(compat: true, strict: true),
                'data' => ['foo' => []],
                'expected' => '"foo.bar" not defined',
            ],
            'compat+assumeObjects: multi-part path, intermediate missing should throw' => [
                'template' => '{{foo.bar}}',
                'options' => new Options(compat: true, assumeObjects: true),
                'expected' => 'Cannot access property "bar" on null',
            ],
            'assumeObjects mode should throw on null block param intermediate' => [
                'template' => '{{#each items as |item|}}{{item.nested.val}}{{/each}}',
                'options' => new Options(assumeObjects: true),
                'data' => ['items' => [['nested' => null]]],
                'expected' => 'Cannot access property "val" on null',
            ],
            'strict mode should throw for missing block param property' => [
                'template' => '{{#each items as |item|}}{{item.missing}}{{/each}}',
                'options' => new Options(strict: true),
                'data' => ['items' => [['val' => 'x']]],
                'expected' => '"item.missing" not defined',
            ],
        ];
    }

    #[DataProvider("errorProvider")]
    public function testErrors(string $template, string $expected, ?Options $options = null): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($expected);
        Handlebars::precompile($template, $options ?? new Options());
    }

    /**
     * @return array<string, ErrorCase>
     */
    public static function errorProvider(): array
    {
        return [
            'knownHelpersOnly rejects unknown inline helper' => [
                'template' => '{{typeof hello}}',
                'options' => new Options(knownHelpersOnly: true),
                'expected' => 'You specified knownHelpersOnly, but used the unknown helper typeof',
            ],
            'knownHelpersOnly rejects unknown block helper with args' => [
                'template' => '{{#test "arg"}}{{/test}}',
                'options' => new Options(knownHelpersOnly: true),
                'expected' => 'You specified knownHelpersOnly, but used the unknown helper test',
            ],
            'knownHelpersOnly rejects unknown block helper with hash' => [
                'template' => '{{#list id="nav-bar"}}{{/list}}',
                'options' => new Options(knownHelpersOnly: true),
                'expected' => 'You specified knownHelpersOnly, but used the unknown helper list',
            ],
            'knownHelpersOnly rejects if when disabled via knownHelpers' => [
                'template' => '{{#if true}}nope{{/if}}',
                'options' => new Options(knownHelpers: ['if' => false], knownHelpersOnly: true),
                'expected' => 'You specified knownHelpersOnly, but used the unknown helper if',
            ],
            'knownHelpersOnly rejects multi-segment path with hash' => [
                'template' => '{{#obj.fn prop=val}}BODY{{/obj.fn}}',
                'options' => new Options(knownHelpersOnly: true),
                'expected' => 'You specified knownHelpersOnly, but used the unknown helper obj.fn',
            ],
            'knownHelpersOnly rejects depth path with hash' => [
                'template' => '{{#../flag prop=val}}BODY{{/../flag}}',
                'options' => new Options(knownHelpersOnly: true),
                'expected' => 'You specified knownHelpersOnly, but used the unknown helper ../flag',
            ],
            'knownHelpersOnly rejects @data path with params' => [
                'template' => '{{@fn "Hello"}}',
                'options' => new Options(knownHelpersOnly: true),
                'expected' => 'You specified knownHelpersOnly, but used the unknown helper fn',
            ],
            'unknown decorator throws' => [
                'template' => '{{#*help me}}{{/help}}',
                'expected' => 'Unknown decorator: "help"',
            ],
        ];
    }
}
