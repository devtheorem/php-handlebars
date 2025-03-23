<?php

use DevTheorem\Handlebars\Handlebars;
use DevTheorem\Handlebars\Options;
use PHPUnit\Framework\TestCase;

$tested = 0;

function recursive_unset(&$array, $unwanted_key): void
{
    if (!is_array($array)) {
        return;
    }
    if (isset($array[$unwanted_key])) {
        unset($array[$unwanted_key]);
    }
    foreach ($array as &$value) {
        if (is_array($value)) {
            recursive_unset($value, $unwanted_key);
        }
    }
}

function patch_safestring($code)
{
    $classname = '\\DevTheorem\\Handlebars\\SafeString';
    return preg_replace('/ SafeString(\s*\(.*?\))?/', ' ' . $classname . '$1', $code);
}

function data_helpers_fix(array &$spec)
{
    if (isset($spec['data']) && is_array($spec['data'])) {
        foreach ($spec['data'] as $key => $value) {
            if (is_array($value) && isset($value['!code']) && isset($value['php'])) {
                $spec['helpers'][$key] = $value;
                unset($spec['data'][$key]);
            }
        }
    }
}

/**
 * Used by vendor/jbboehr/handlebars-spec/spec/data.json
 */
class Utils
{
    public static function createFrame($data)
    {
        if (is_array($data)) {
            $r = [];
            foreach ($data as $k => $v) {
                $r[$k] = $v;
            }
            return $r;
        }
        return $data;
    }
}

class HandlebarsSpecTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider("jsonSpecProvider")]
    public function testSpecs($spec)
    {
        global $tested;

        recursive_unset($spec['data'], '!sparsearray');
        data_helpers_fix($spec);

        // Fix {} for these test cases
        if (
            ($spec['it'] === 'should override template partials') ||
            ($spec['it'] === 'should override partials down the entire stack') ||
            ($spec['it'] === 'should define inline partials for block')
        ) {
            $spec['data'] = new \stdClass();
        }

        //// Skip bad specs
        // 1. No expected nor exception in spec
        if (!isset($spec['expected']) && !isset($spec['exception'])) {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , no expected result in spec, skip.");
        }

        // 2. Not supported case: foo/bar path
        if (
            ($spec['it'] === 'literal paths') ||
            ($spec['it'] === 'this keyword nested inside path') ||
            ($spec['it'] === 'this keyword nested inside helpers param') ||
            ($spec['it'] === 'parameter data throws when using complex scope references') ||
            ($spec['it'] === 'block with complex lookup using nested context')
        ) {
            $this->markTestIncomplete('Not supported case: foo/bar path');
        }

        // 4. block parameters, special case now do not support
        if (
            ($spec['it'] === 'should not take presedence over pathed values')
        ) {
            $this->markTestIncomplete('Not supported case: just skip it');
        }

        // 5. Not supported case: helperMissing and blockHelperMissing
        if (
            ($spec['it'] === 'if a context is not found, helperMissing is used') ||
            ($spec['it'] === 'if a context is not found, custom helperMissing is used') ||
            ($spec['it'] === 'if a value is not found, custom helperMissing is used') ||
            ($spec['it'] === 'should include in simple block calls') ||
            ($spec['it'] === 'should include full id') ||
            ($spec['it'] === 'should include full id if a hash is passed') ||
            ($spec['it'] === 'lambdas resolved by blockHelperMissing are bound to the context')
        ) {
            $this->markTestIncomplete('Not supported case: just skip it');
        }

        // 6. Not supported case: misc
        if (
            // compat mode
            $spec['description'] === 'blocks - compat mode' ||
            $spec['description'] === 'partials - compat mode' ||

            // stringParams
            $spec['it'] === 'in string params mode,' ||

            // Decorators are deprecated: https://github.com/wycats/handlebars.js/blob/master/docs/decorators-api.md
            ($spec['description'] === 'blocks - decorators') ||

            // strict mode
            $spec['description'] === 'strict - strict mode' ||
            $spec['description'] === 'strict - assume objects' ||

            // helper for raw block
            ($spec['it'] === 'helper for raw block gets parameters') ||

            // lambda function in data
            $spec['it'] === 'pathed functions with context argument' ||
            $spec['it'] === 'Functions are bound to the context in knownHelpers only mode'
        ) {
            $this->markTestIncomplete('Not supported case: just skip it');
        }

        // TODO: require fix
        if (
            // inline partials
            str_starts_with($spec['it'], 'should render nested inline partials') ||
            $spec['it'] === 'should define inline partials for block' && isset($spec['number']) ||

               // SafeString
               ($spec['it'] === 'rendering function partial in vm mode') ||

            // todo: fix
            $spec['it'] === 'each with block params' ||
            $spec['it'] === 'pathed lambas with parameters' ||
            $spec['it'] === 'lambdas are resolved by blockHelperMissing, not handlebars proper' ||
            $spec['description'] === 'helpers - the lookupProperty-option' ||

               // need confirm
            $spec['it'] === 'if with function argument' ||
            $spec['it'] === 'with with function argument' ||
            $spec['it'] === 'each with function argument' && !isset($spec['number']) ||
            $spec['it'] === 'data can be functions' ||
            $spec['it'] === 'data can be functions with params' ||
            $spec['it'] === 'depthed block functions with context argument' ||
            $spec['it'] === 'depthed functions with context argument'
        ) {
            $this->markTestIncomplete('TODO: require fix');
        }

        // FIX SPEC
        if ($spec['it'] === 'should take presednece over parent block params') {
            $spec['helpers']['goodbyes']['php'] = 'function($options) { static $value; if($value === null) { $value = 1; } return $options->fn(array("value" => "bar"), array("blockParams" => ($options->blockParams === 1) ? array($value++, $value++) : null));}';
        }
        if ($spec['it'] === 'depthed block functions without context argument' && $spec['expected'] === 'inner') {
            $spec['expected'] = '';
        }

        // setup helpers
        $tested++;
        $helpers = [];
        $helpersList = '';
        foreach (is_array($spec['helpers'] ?? null) ? $spec['helpers'] : [] as $name => $func) {
            if (!isset($func['php'])) {
                $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , no PHP helper code provided for this case.");
            }
            $hname = preg_replace('/\\.|\\//', '_', "custom_helper_{$spec['no']}_{$tested}_$name");
            $helpers[$name] = $hname;
            $helper = patch_safestring(
                preg_replace('/function/', "function $hname", $func['php'], 1),
            );
            $helper = str_replace('$options[\'data\']', '$options->data', $helper);
            $helper = str_replace('$options[\'hash\']', '$options->hash', $helper);
            $helper = str_replace('$arguments[count($arguments)-1][\'name\'];', '$arguments[count($arguments)-1]->name;', $helper);
            if (($spec['it'] === 'helper block with complex lookup expression') && ($name === 'goodbyes')) {
                $helper = str_replace('$options->fn();', '$options->fn([]);', $helper);
            }
            $helpersList .= "$helper\n";
            eval($helper);
        }

        try {
            $partials = [];
            $knownHelpersOnly = false;
            $strict = false;
            $preventIndent = false;
            $ignoreStandalone = false;
            $explicitPartialContext = false;

            // Do not use array_merge() here because it destroys numeric key
            if (isset($spec['partials'])) {
                foreach ($spec['partials'] as $k => $v) {
                    $partials[$k] = $v;
                }
            }

            if (isset($spec['compileOptions']['strict'])) {
                if ($spec['compileOptions']['strict']) {
                    $strict = true;
                }
            }

            if (isset($spec['compileOptions']['preventIndent'])) {
                if ($spec['compileOptions']['preventIndent']) {
                    $preventIndent = true;
                }
            }

            if (isset($spec['compileOptions']['explicitPartialContext'])) {
                if ($spec['compileOptions']['explicitPartialContext']) {
                    $explicitPartialContext = true;
                }
            }

            if (isset($spec['compileOptions']['ignoreStandalone'])) {
                if ($spec['compileOptions']['ignoreStandalone']) {
                    $ignoreStandalone = true;
                }
            }

            if (isset($spec['compileOptions']['knownHelpersOnly'])) {
                if ($spec['compileOptions']['knownHelpersOnly']) {
                    $knownHelpersOnly = true;
                }
            }

            $php = Handlebars::precompile($spec['template'], new Options(
                knownHelpersOnly: $knownHelpersOnly,
                strict: $strict,
                preventIndent: $preventIndent,
                ignoreStandalone: $ignoreStandalone,
                explicitPartialContext: $explicitPartialContext,
                helpers: $helpers,
                partials: $partials,
            ));
        } catch (\Exception $e) {
            if (isset($spec['exception'])) {
                $this->assertEquals(true, true);
                return;
            }
            $this->fail("Compile error in {$spec['file']}#{$spec['description']}]#{$spec['no']}:{$spec['it']}\n" . $e->getMessage());
        }
        $renderer = Handlebars::template($php);

        try {
            $ropt = [];
            if (is_array($spec['runtimeOptions']['data'] ?? null)) {
                $ropt['data'] = $spec['runtimeOptions']['data'];
            }
            $result = $renderer($spec['data'], $ropt);
        } catch (\Exception $e) {
            if (isset($spec['exception'])) {
                $this->assertEquals(true, true);
                return;
            }
            $this->fail("Rendering Error in {$spec['file']}#{$spec['description']}]#{$spec['no']}:{$spec['it']}\nPHP code:\n$php\n\n" . $e->getMessage());
        }

        if (isset($spec['exception'])) {
            $this->fail("Should Fail: [{$spec['file']}#{$spec['description']}]#{$spec['no']}:{$spec['it']}\nPHP code:\n$php\n\nResult: $result");
        }

        $this->assertEquals($spec['expected'], $result, "[{$spec['file']}#{$spec['description']}]#{$spec['no']}:{$spec['it']}\nHELPERS:$helpersList");
    }

    public static function jsonSpecProvider()
    {
        $ret = [];

        // stringParams and trackIds mode were removed from Handlebars in 2015:
        // https://github.com/handlebars-lang/handlebars.js/pull/1148
        $skip = ['parser', 'regressions', 'tokenizer', 'string-params', 'track-ids'];

        foreach (glob('vendor/jbboehr/handlebars-spec/spec/*.json') as $file) {
            $name = basename($file, '.json');
            if (in_array($name, $skip)) {
                continue;
            }
            $i = 0;
            $json = json_decode(file_get_contents($file), true);
            $ret = array_merge($ret, array_map(function ($d) use ($file, &$i) {
                $d['file'] = $file;
                $d['no'] = ++$i;
                if (!isset($d['message'])) {
                    $d['message'] = null;
                }
                if (!isset($d['data'])) {
                    $d['data'] = null;
                }
                return [$d];
            }, $json));
        }

        return $ret;
    }
}
