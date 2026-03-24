<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Handlebars;
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
            $renderer($data, [
                'helpers' => $helpers,
            ]);
            $this->fail("Expected to throw exception: {$expected}. CODE: $php");
        } catch (\Exception $e) {
            $this->assertEquals($expected, $e->getMessage(), $php);
        }
    }

    /**
     * @return RenderTest[]
     */
    public static function renderErrorProvider(): array
    {
        return [
            [
                'template' => '{{>not_found}}',
                'expected' => "The partial not_found could not be found",
            ],
            [
                'template' => "{{#> testPartial}}\n  {{#> innerPartial}}\n   {{> @partial-block}}\n  {{/innerPartial}}\n{{/testPartial}}",
                'options' => new Options(
                    partials: [
                        'testPartial' => 'testPartial => {{> @partial-block}} <=',
                        'innerPartial' => 'innerPartial -> {{> @partial-block}} <-',
                    ],
                ),
                'expected' => "The partial @partial-block could not be found",
            ],
            [
                'template' => '{{> @partial-block}}',
                'expected' => "The partial @partial-block could not be found",
            ],
            [
                'template' => '{{foo.bar}}',
                'options' => new Options(strict: true),
                'data' => ['foo' => []],
                'expected' => '"foo.bar" not defined',
            ],
            [
                'template' => '{{#if foo.bar}}bad{{else}}OK{{/if}}',
                'options' => new Options(strict: true),
                'expected' => 'Cannot access property "bar" on null',
            ],
            [
                'template' => '{{foo}}',
                'options' => new Options(strict: true),
                'expected' => '"foo" not defined',
            ],
            [
                // strict mode should override helperMissing
                'template' => '{{foo}}',
                'options' => new Options(strict: true),
                'helpers' => ['helperMissing' => fn() => 'bad'],
                'expected' => '"foo" not defined',
            ],
            [
                // strict mode should override blockHelperMissing
                'template' => '{{#foo}}OK{{/foo}}',
                'options' => new Options(strict: true),
                'helpers' => ['blockHelperMissing' => fn() => 'bad'],
                'expected' => '"foo" not defined',
            ],
            [
                'template' => '{{#foo}}OK{{/foo}}',
                'options' => new Options(strict: true),
                'expected' => '"foo" not defined',
            ],
            [
                'template' => '{{{foo}}}',
                'options' => new Options(strict: true),
                'expected' => '"foo" not defined',
            ],
            [
                'template' => '{{foo}}',
                'helpers' => [
                    'foo' => function () {
                        throw new \Exception('Expect the unexpected');
                    },
                ],
                'expected' => 'Expect the unexpected',
            ],
            // ensure that callable strings in data aren't treated as functions
            [
                'template' => "{{#foo.bar 'arg'}}{{/foo.bar}}",
                'data' => ['foo' => ['bar' => 'strlen']],
                'expected' => 'Missing helper: "foo.bar"',
            ],
            [
                'template' => '{{typeof hello}}',
                'expected' => 'Missing helper: "typeof"',
            ],
            [
                'template' => '{{#test foo}}{{/test}}',
                'expected' => 'Missing helper: "test"',
            ],
            [
                'template' => '{{test_join (foo bar)}}',
                'helpers' => [
                    'test_join' => function ($input) {
                        return join('.', $input);
                    },
                ],
                'expected' => 'Missing helper: "foo"',
            ],
            [
                'template' => '{{> (foo) bar}}',
                'expected' => 'Missing helper: "foo"',
            ],
            [
                'template' => '{{#with}}OK!{{/with}}',
                'expected' => '#with requires exactly one argument',
            ],
            [
                'template' => '{{#if}}OK!{{/if}}',
                'expected' => '#if requires exactly one argument',
            ],
            [
                'template' => '{{#unless}}OK!{{/unless}}',
                'expected' => '#unless requires exactly one argument',
            ],
            [
                'template' => '{{#each}}OK!{{/each}}',
                'expected' => 'Must pass iterator to #each',
            ],
        ];
    }

    #[DataProvider("errorProvider")]
    public function testErrors(string $template, string $expected, ?Options $options = null): void
    {
        try {
            Handlebars::precompile($template, $options ?? new Options());
            $this->fail("Expected to throw exception: {$expected}");
        } catch (\Exception $e) {
            $this->assertSame($expected, $e->getMessage());
        }
    }

    /**
     * @return ErrorCase[]
     */
    public static function errorProvider(): array
    {
        return [
            [
                'template' => '{{typeof hello}}',
                'options' => new Options(knownHelpersOnly: true),
                'expected' => 'You specified knownHelpersOnly, but used the unknown helper typeof',
            ],
            [
                'template' => '{{#test "arg"}}{{/test}}',
                'options' => new Options(knownHelpersOnly: true),
                'expected' => 'You specified knownHelpersOnly, but used the unknown helper test',
            ],
            [
                'template' => '{{#list id="nav-bar"}}{{/list}}',
                'options' => new Options(knownHelpersOnly: true),
                'expected' => 'You specified knownHelpersOnly, but used the unknown helper list',
            ],
            [
                'template' => '{{#if true}}nope{{/if}}',
                'options' => new Options(knownHelpers: ['if' => false], knownHelpersOnly: true),
                'expected' => 'You specified knownHelpersOnly, but used the unknown helper if',
            ],
            [
                'template' => '{{#*help me}}{{/help}}',
                'expected' => 'Unknown decorator: "help"',
            ],
        ];
    }
}
