<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Handlebars;
use DevTheorem\Handlebars\Options;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type RenderTest array{template: string, options?: Options, data?: array<mixed>, expected: string}
 * @phpstan-type ErrorCase array{template: string, options?: Options, expected: string}
 */
class ErrorTest extends TestCase
{
    /**
     * @param array<mixed> $data
     */
    #[DataProvider("renderErrorProvider")]
    public function testRenderingException(string $template, string $expected, ?Options $options = null, array $data = []): void
    {
        $php = Handlebars::precompile($template, $options ?? new Options());
        $renderer = Handlebars::template($php);
        try {
            $renderer($data);
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
                'template' => "{{#> testPartial}}\n  {{#> innerPartial}}\n   {{> @partial-block}}\n  {{/innerPartial}}\n{{/testPartial}}",
                'options' => new Options(
                    partials: [
                        'testPartial' => 'testPartial => {{> @partial-block}} <=',
                        'innerPartial' => 'innerPartial -> {{> @partial-block}} <-',
                    ],
                ),
                'expected' => "Runtime: the partial @partial-block could not be found",
            ],
            [
                'template' => '{{> @partial-block}}',
                'expected' => "Runtime: the partial @partial-block could not be found",
            ],
            [
                'template' => '{{foo}}',
                'options' => new Options(strict: true),
                'expected' => 'Runtime: foo does not exist',
            ],
            [
                'template' => '{{#foo}}OK{{/foo}}',
                'options' => new Options(strict: true),
                'expected' => 'Runtime: foo does not exist',
            ],
            [
                'template' => '{{{foo}}}',
                'options' => new Options(strict: true),
                'expected' => 'Runtime: foo does not exist',
            ],
            [
                'template' => '{{foo}}',
                'options' => new Options(
                    helpers: [
                        'foo' => function () {
                            throw new \Exception('Expect the unexpected');
                        },
                    ],
                ),
                'expected' => 'Runtime: call custom helper \'foo\' error: Expect the unexpected',
            ],
            // ensure that callable strings in data aren't treated as functions
            [
                'template' => "{{#foo.bar 'arg'}}{{/foo.bar}}",
                'data' => ['foo' => ['bar' => 'strlen']],
                'expected' => '"foo.bar" is not a block helper function',
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
                'expected' => 'Missing helper: "typeof"',
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
            [
                'template' => '{{lookup}}',
                'expected' => '{{lookup}} requires 2 arguments',
            ],
            [
                'template' => '{{lookup foo}}',
                'expected' => '{{lookup}} requires 2 arguments',
            ],
            [
                'template' => '{{#test foo}}{{/test}}',
                'expected' => 'Missing helper: "test"',
            ],
            [
                'template' => '{{>not_found}}',
                'expected' => "The partial not_found could not be found",
            ],
            [
                'template' => '{{test_join (foo bar)}}',
                'options' => new Options(
                    helpers: [
                        'test_join' => function ($input) {
                            return join('.', $input);
                        },
                    ],
                ),
                'expected' => 'Missing helper: "foo"',
            ],
            [
                'template' => '{{> (foo) bar}}',
                'expected' => 'Missing helper: "foo"',
            ],
            [
                'template' => '{{#*help me}}{{/help}}',
                'expected' => 'Unknown decorator: "help"',
            ],
        ];
    }
}
