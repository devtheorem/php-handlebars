<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Handlebars;
use DevTheorem\Handlebars\Options;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type MustacheSpec array{
 *     file: string, name: string, desc: string, expected: string, template: string,
 *     data: mixed, partials: array<string>,
 * }
 */
class MustacheSpecTest extends TestCase
{
    /**
     * @param MustacheSpec $spec
     */
    #[DataProvider("mustacheSpecProvider")]
    public function testSpecs(array $spec): void
    {
        // skip same tests as Handlebars.js: https://github.com/handlebars-lang/handlebars.js/blob/master/spec/spec.js
        if (
            // Optional Mustache specs (lambdas, dynamic names, inheritance) are not supported
            str_starts_with($spec['file'], '~')
            // We also choose to throw if partials are not found
            || ($spec['file'] === 'partials.json' && $spec['name'] === 'Failed Lookup')
            // We nest the entire response from partials, not just the literals
            || ($spec['file'] === 'partials.json' && $spec['name'] === 'Standalone Indentation')
            // We do not support alternative delimiters
            || str_contains($spec['template'], '{{=')
            || count(array_filter($spec['partials'], fn($v) => str_contains($v, '{{='))) > 0
        ) {
            $this->markTestIncomplete('Not supported');
        }

        $options = new Options(compat: true);
        $template = Handlebars::compile($spec['template'], $options);
        $result = $template($spec['data'], [
            'partials' => array_map(fn($p) => Handlebars::compile($p, $options), $spec['partials']),
        ]);

        $this->assertSame($spec['expected'], $result);
    }

    /**
     * @return array<string, array{MustacheSpec}>
     */
    public static function mustacheSpecProvider(): array
    {
        $ret = [];

        $files = glob('tests/mustache/specs/*.json');
        if ($files === false) {
            throw new \Exception("Failed to read Mustache spec files");
        }

        foreach ($files as $file) {
            $fileName = basename($file);
            $contents = file_get_contents($file);
            if ($contents === false) {
                throw new \Exception("Failed to read Mustache spec file {$file}");
            }
            $json = json_decode($contents, true);

            foreach ($json['tests'] as $test) {
                $key = "{$fileName}: {$test['name']}";
                $ret[$key] = [[
                    'file' => $fileName,
                    'name' => $test['name'],
                    'desc' => $test['desc'],
                    'expected' => $test['expected'],
                    'template' => $test['template'],
                    'data' => $test['data'],
                    'partials' => $test['partials'] ?? [],
                ]];
            }
        }

        return $ret;
    }
}
