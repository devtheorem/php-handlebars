<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Handlebars;
use DevTheorem\Handlebars\Options;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type RenderTest array{template: string, options?: Options, expected: string}
 * @phpstan-type ErrorCase array{template: string, options: Options, expected: string|null}
 */
class ErrorTest extends TestCase
{
    /**
     * @param RenderTest $test
     */
    #[DataProvider("renderErrorProvider")]
    public function testRenderingException(array $test): void
    {
        $php = Handlebars::precompile($test['template'], $test['options'] ?? new Options());
        $renderer = Handlebars::template($php);
        try {
            $renderer();
            $this->fail("Expected to throw exception: {$test['expected']}. CODE: $php");
        } catch (\Exception $e) {
            $this->assertEquals($test['expected'], $e->getMessage(), $php);
        }
    }

    /**
     * @return list<array{RenderTest}>
     */
    public static function renderErrorProvider(): array
    {
        $errorCases = [
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
        ];

        return array_map(fn($i) => [$i], $errorCases);
    }

    #[DataProvider("errorProvider")]
    public function testErrors(string $template, ?string $expected, Options $options): void
    {
        if ($expected === null) {
            // should compile without error
            $code = Handlebars::precompile($template, $options);
            $this->assertNotEmpty($code);
            return;
        }

        try {
            Handlebars::precompile($template, $options);
            $this->fail("Expected to throw exception: {$expected}");
        } catch (\Exception $e) {
            $this->assertSame($expected, $e->getMessage());
        }
    }

    /**
     * @return list<ErrorCase>
     */
    public static function errorProvider(): array
    {
        $errorCases = [
            [
                'template' => '{{typeof hello}}',
                'expected' => 'Missing helper: "typeof"',
            ],
            [
                'template' => '{{#with items}}OK!{{/with}}',
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
                'template' => '{{log}}',
            ],
            [
                'template' => '{{#*help me}}{{/help}}',
                'expected' => 'Unknown decorator: "help"',
            ],
            [
                'template' => '{{#*inline}}{{/inline}}',
            ],
        ];

        return array_map(function ($i) {
            if (!isset($i['options'])) {
                $i['options'] = new Options();
            }
            if (!isset($i['expected'])) {
                $i['expected'] = null;
            }
            return $i;
        }, $errorCases);
    }
}
