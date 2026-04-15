<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Handlebars;
use DevTheorem\Handlebars\Options;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type JsonSpec array{
 *     data: mixed, it: string, description: string, expected: string|null,
 *     helpers: array<mixed>, partials: array<mixed>, compileOptions: array<mixed>,
 *     template: string, exception: string|true, runtimeOptions: array<mixed>,
 * }
 */
class HandlebarsSpecTest extends TestCase
{
    /**
     * @param JsonSpec $spec
     */
    #[DataProvider("jsonSpecProvider")]
    public function testSpecs(array $spec): void
    {
        // Fix {} for these test cases
        if (
            $spec['it'] === 'should override template partials'
            || $spec['it'] === 'should override partials down the entire stack'
            || $spec['it'] === 'should define inline partials for block'
        ) {
            $spec['data'] = new \stdClass();
        }

        // 5. Not supported case: misc
        if (
            // stringParams mode was removed from Handlebars in 2015
            isset($spec['compileOptions']['stringParams'])

            // Decorators are deprecated: https://github.com/handlebars-lang/handlebars.js/blob/master/docs/decorators-api.md
            || $spec['description'] === 'blocks - decorators'

            // this method may be useful in JS, but not in PHP
            || $spec['description'] === 'helpers - the lookupProperty-option'

            // PHP doesn't have the same concept of sparse arrays as JS, so there's no need to skip over holes.
            || $spec['it'] === 'GH-1065: Sparse arrays'
        ) {
            $this->markTestIncomplete('Not supported case: just skip it');
        }

        // FIX SPEC
        if ($spec['it'] === 'should take presednece over parent block params') {
            $spec['helpers']['goodbyes']['php'] = str_replace('static $value = 0;', 'static $value = 1;', $spec['helpers']['goodbyes']['php']);
        }
        self::addDataHelpers($spec);

        // setup helpers
        $helpers = [];
        $helpersList = '[';
        foreach ($spec['helpers'] as $name => $func) {
            if (!isset($func['php'])) {
                continue;
            }
            $helper = self::patchHelperCode($func['php']);
            $helpersList .= "\n  '$name' => $helper,\n";
            eval('$helpers[\'' . $name . '\'] = ' . $helper . ';');
        }
        $helpersList .= ']';

        // Convert "!code" partials (callable PHP strings) into actual callables.
        $partials = [];
        $stringPartials = [];
        foreach ($spec['partials'] as $name => $partial) {
            if (is_array($partial) && isset($partial['!code'], $partial['php'])) {
                $partials[$name] = eval('return ' . $partial['php'] . ';');
            } else {
                $stringPartials[$name] = $partial;
            }
        }

        try {
            $knownHelpersOnly = $spec['compileOptions']['knownHelpersOnly'] ?? false;
            $strict = $spec['compileOptions']['strict'] ?? false;
            $assumeObjects = $spec['compileOptions']['assumeObjects'] ?? false;
            $preventIndent = $spec['compileOptions']['preventIndent'] ?? false;
            $ignoreStandalone = $spec['compileOptions']['ignoreStandalone'] ?? false;
            $explicitPartialContext = $spec['compileOptions']['explicitPartialContext'] ?? false;

            $php = Handlebars::precompile($spec['template'], new Options(
                compat: $spec['compileOptions']['compat'] ?? false,
                knownHelpers: $spec['compileOptions']['knownHelpers'] ?? [],
                knownHelpersOnly: $knownHelpersOnly,
                strict: $strict,
                assumeObjects: $assumeObjects,
                preventIndent: $preventIndent,
                ignoreStandalone: $ignoreStandalone,
                explicitPartialContext: $explicitPartialContext,
                partials: $stringPartials,
            ));
        } catch (\Exception $e) {
            if ($spec['exception']) {
                $this->expectNotToPerformAssertions();
                return;
            }
            $this->fail("Compile error: {$e->getMessage()}");
        }
        $renderer = Handlebars::template($php);

        try {
            $ropt = [
                'helpers' => $helpers,
                'partials' => $partials,
            ];
            if (is_array($spec['runtimeOptions']['data'] ?? null)) {
                $ropt['data'] = [];
                foreach ($spec['runtimeOptions']['data'] as $key => $value) {
                    if (is_array($value) && isset($value['!code'], $value['php'])) {
                        eval('$ropt[\'data\'][\'' . $key . '\'] = ' . $value['php'] . ';');
                    } else {
                        $ropt['data'][$key] = $value;
                    }
                }
            }
            $result = $renderer($spec['data'], $ropt);
        } catch (\Exception $e) {
            if ($spec['exception']) {
                $this->expectNotToPerformAssertions();
                return;
            }
            $this->fail("Rendering error: {$e->getMessage()}\n\n" . self::getSpecDetails($php, $helpersList));
        }

        if ($spec['exception']) {
            $details = $spec['exception'] === true ? '.' : ": {$spec['exception']}\n";
            $this->fail("Expected exception{$details}\nResult: $result\n\n" . self::getSpecDetails($php, $helpersList));
        }

        $this->assertSame($spec['expected'], $result, self::getSpecDetails($php, $helpersList));
    }

    private static function getSpecDetails(string $code, string $helpers): string
    {
        return "Helpers: $helpers\nPHP code:\n$code";
    }

    /**
     * @return array<string, array{JsonSpec}>
     */
    public static function jsonSpecProvider(): array
    {
        $ret = [];

        // stringParams and trackIds mode were removed from Handlebars in 2015:
        // https://github.com/handlebars-lang/handlebars.js/pull/1148
        $skip = ['parser', 'tokenizer', 'string-params', 'track-ids'];

        $files = glob('vendor/jbboehr/handlebars-spec/spec/*.json');
        if ($files === false) {
            throw new \Exception("Failed to read JSON spec files");
        }

        foreach ($files as $file) {
            $name = basename($file, '.json');
            if (in_array($name, $skip)) {
                continue;
            }
            $contents = file_get_contents($file);
            if ($contents === false) {
                throw new \Exception("Failed to read JSON spec file {$file}");
            }
            $json = json_decode($contents, true);

            foreach ($json as $spec) {
                $number = $spec['number'] ?? null;
                $key = "{$spec['description']}: {$spec['it']}" . ($number !== null ? " #$number" : '');
                if (isset($spec['message'])) {
                    $key .= ": {$spec['message']}";
                }

                $ret[$key] = [[
                    'data' => $spec['data'] ?? null,
                    'it' => $spec['it'],
                    'description' => $spec['description'],
                    'expected' => $spec['expected'] ?? null,
                    'helpers' => $spec['helpers'] ?? [],
                    'partials' => $spec['partials'] ?? [],
                    'compileOptions' => $spec['compileOptions'] ?? [],
                    'template' => $spec['template'] ?? '',
                    'exception' => $spec['exception'] ?? '',
                    'runtimeOptions' => $spec['runtimeOptions'] ?? [],
                ]];
            }
        }

        return $ret;
    }

    /**
     * @param JsonSpec $spec
     */
    private static function addDataHelpers(array &$spec): void
    {
        if (isset($spec['data']) && is_array($spec['data'])) {
            foreach ($spec['data'] as $key => &$value) {
                if (is_array($value) && isset($value['!code'], $value['php'])) {
                    $spec['helpers'][$key] = $value;
                    eval('$value = ' . $value['php'] . ';');
                }
            }
            unset($value);
            self::evalNestedCode($spec['data']);
        }
    }

    /**
     * @param array<mixed> $data
     */
    private static function evalNestedCode(array &$data): void
    {
        foreach ($data as &$value) {
            if (!is_array($value)) {
                continue;
            }
            if (isset($value['!code'], $value['php'])) {
                eval('$value = ' . $value['php'] . ';');
            } else {
                self::evalNestedCode($value);
            }
        }
    }

    private static function patchHelperCode(string $code): string
    {
        $code = preg_replace('/ (\\\Handlebars\\\)?SafeString(\s*\(.*?\))?/', ' \\DevTheorem\\Handlebars\\SafeString$2', $code)
            ?? throw new \Exception("Failed to patch SafeString in $code");
        $code = str_replace('Utils::createFrame(', '\DevTheorem\Handlebars\Handlebars::createFrame(', $code);
        $code = str_replace('$options[\'name\']', '$options->name', $code);
        $code = str_replace('$options[\'data\']', '$options->data', $code);
        $code = str_replace('$options[\'hash\']', '$options->hash', $code);
        return str_replace('$arguments[count($arguments)-1][\'name\'];', '$arguments[count($arguments)-1]->name;', $code);
    }
}
