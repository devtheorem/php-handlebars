<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Handlebars;
use DevTheorem\Handlebars\HelperOptions;
use DevTheorem\Handlebars\Options;
use DevTheorem\Handlebars\SafeString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type RegIssue array{
 *     desc?: string, template: string, data?: mixed, options?: Options,
 *     helpers?: array<string, \Closure>, expected: string,
 * }
 */
class RegressionTest extends TestCase
{
    public function testLog(): void
    {
        $template = Handlebars::compile('{{log foo}}');

        date_default_timezone_set('GMT');
        $tmpDir = sys_get_temp_dir();
        $tmpFile = tempnam($tmpDir, 'terr_');
        ini_set('error_log', $tmpFile);

        $template(['foo' => 'OK!']);
        $lines = file($tmpFile);
        if ($lines === false) {
            throw new \Exception("Failed to read temp file $tmpFile");
        }

        $contents = array_map(function ($l) {
            $l = rtrim($l);
            preg_match('/GMT] (.+)/', $l, $m);
            return $m[1] ?? $l;
        }, $lines);

        $this->assertEquals(['array (', "  0 => 'OK!',", ')'], $contents);
        ini_restore('error_log');
    }

    public function testRuntimePartials(): void
    {
        // testcase from https://github.com/zordius/lightncandy/issues/292
        $templateString = '{{#>outer}} {{#>compiledBlock}} inner compiledBlock {{/compiledBlock}} {{>normalTemplate}} {{/outer}}';

        $template = Handlebars::compile($templateString, new Options(
            partials: [
                'outer' => 'outer+{{#>nested}}~{{>@partial-block}}~{{/nested}}+outer-end',
                'nested' => 'nested={{>@partial-block}}=nested-end',
            ],
        ));

        $result = $template(null, [
            'partials' => [
                'compiledBlock' => Handlebars::compile('compiledBlock !!! {{>@partial-block}} !!! compiledBlock'),
                'normalTemplate' => Handlebars::compile('normalTemplate'),
            ],
        ]);

        $this->assertSame('outer+nested=~ compiledBlock !!!  inner compiledBlock  !!! compiledBlock normalTemplate ~=nested-end+outer-end', $result);

        // testcase from https://github.com/zordius/lightncandy/issues/341
        $templateString = '{{#> MyPartial child}}This <b>text</b> was sent from the template to the partial.{{/MyPartial}}';
        $partialTemplateString = '{{name}} says: “{{> @partial-block }}”';
        $template = Handlebars::compile($templateString);
        $context = ['child' => ['name' => 'Jason']];

        $result = $template($context, [
            'partials' => [
                'MyPartial' => Handlebars::compile($partialTemplateString),
            ],
        ]);

        $this->assertSame('Jason says: “This <b>text</b> was sent from the template to the partial.”', $result);
    }

    /**
     * @param array<mixed>|null $data
     * @param array<string, \Closure> $helpers
     */
    #[DataProvider("helperProvider")]
    #[DataProvider("partialProvider")]
    #[DataProvider("builtInProvider")]
    #[DataProvider("syntaxProvider")]
    public function testIssues(string $template, string $expected, mixed $data = null, ?Options $options = null, array $helpers = [], string $desc = ''): void
    {
        $templateSpec = Handlebars::precompile($template, $options ?? new Options());

        try {
            $template = Handlebars::template($templateSpec);
            $result = $template($data, ['helpers' => $helpers]);
        } catch (\Throwable $e) {
            $this->fail("$desc\nError: {$e->getMessage()}\nPHP code:\n$templateSpec");
        }
        $this->assertEquals($expected, $result, "$desc\nPHP code:\n$templateSpec");
    }

    /**
     * @return list<RegIssue>
     */
    public static function helperProvider(): array
    {
        $myIf = function ($conditional, HelperOptions $options) {
            if ($conditional) {
                return $options->fn();
            } else {
                return $options->inverse();
            }
        };

        $myWith = function ($context, HelperOptions $options) {
            return $options->fn($context);
        };

        $myEach = function ($context, HelperOptions $options) {
            $ret = '';
            foreach ($context as $cx) {
                $ret .= $options->fn($cx);
            }
            return $ret;
        };

        $myLogic = function ($input, $yes, $no, HelperOptions $options) {
            if ($input === true) {
                return $options->fn($yes);
            } else {
                return $options->inverse($no);
            }
        };

        $myDash = fn($a, $b) => "$a-$b";
        $list = fn($arg) => join(',', $arg);
        $keys = fn($arg) => array_keys($arg);
        $echo = fn($arg) => "ECHO: $arg";
        $testArray = fn($input) => is_array($input) ? 'IS_ARRAY' : 'NOT_ARRAY';
        $returnBlock = fn(HelperOptions $options) => $options->fn();
        $getThisBar = fn($input, HelperOptions $options) => $input . '-' . $options->scope['bar'];
        $equal = fn($a, $b) => $a === $b;

        $equals = function (mixed $a, mixed $b, HelperOptions $options) {
            $jsEquals = function (mixed $a, mixed $b): bool {
                if ($a === null || $b === null) {
                    // in JS, null is not equal to blank string or false or zero
                    return $a === $b;
                }

                return $a == $b;
            };

            return $jsEquals($a, $b) ? $options->fn() : $options->inverse();
        };

        $inlineHashProp = function ($arg, HelperOptions $options) {
            return "$arg-{$options->hash['bar']}";
        };

        $inlineHashArr = function (HelperOptions $options) {
            $ret = '';
            foreach ($options->hash as $k => $v) {
                $ret .= "$k : $v,";
            }
            return $ret;
        };

        return [
            [
                'desc' => 'LNC #2',
                'template' => <<<_hbs
                    {{#ifEquals @root.item "foo"}}
                        phooey
                    {{else ifEquals @root.item "bar"}}
                        barry
                    {{else}}
                        {{#if @root.item}}
                            {{#ifEquals @root.item "fizz"}}
                                fizzy
                            {{else ifEquals @root.item "buzz"}}
                                buzzy
                            {{else}}
                                no matches
                            {{/ifEquals}}
                        {{/if}}
                    {{/ifEquals}}
                    _hbs,
                'helpers' => ['ifEquals' => $equals],
                'data' => ['item' => 'buzz'],
                'expected' => "            buzzy\n",
            ],

            [
                'desc' => 'LNC #44',
                'template' => '<div class="terms-text"> {{render "artists-terms"}} </div>',
                'helpers' => [
                    'render' => fn($view) => $view . '.html',
                ],
                'expected' => '<div class="terms-text"> artists-terms.html </div>',
            ],

            [
                'desc' => 'LNC #49',
                'template' => '{{date_format}} 1, {{date_format2}} 2',
                'helpers' => [
                    'date_format' => fn() => "OKOK~1",
                    'date_format2' => fn() => "OKOK~2",
                ],
                'expected' => 'OKOK~1 1, OKOK~2 2',
            ],

            [
                'desc' => 'LNC #52',
                'template' => '{{{test_array tmp}}} should be happy!',
                'helpers' => ['test_array' => $testArray],
                'data' => ['tmp' => ['A', 'B', 'C']],
                'expected' => 'IS_ARRAY should be happy!',
            ],

            [
                'desc' => 'LNC #62',
                'template' => '{{{test_join @root.foo.bar}}} should be happy!',
                'helpers' => ['test_join' => $list],
                'data' => ['foo' => ['A', 'B', 'bar' => ['C', 'D']]],
                'expected' => 'C,D should be happy!',
            ],

            [
                'desc' => 'LNC #68',
                'template' => '{{#myeach foo}} Test! {{this}} {{/myeach}}',
                'helpers' => ['myeach' => $myEach],
                'data' => ['foo' => ['A', 'B', 'bar' => ['C', 'D', 'E']]],
                'expected' => ' Test! A  Test! B  Test! C,D,E ',
            ],

            [
                'desc' => 'LNC #85',
                'template' => '{{helper 1 foo bar="q"}}',
                'helpers' => [
                    'helper' => function ($arg1, $arg2, HelperOptions $options) {
                        return "ARG1:$arg1, ARG2:$arg2, HASH:{$options->hash['bar']}";
                    },
                ],
                'data' => ['foo' => 'BAR'],
                'expected' => 'ARG1:1, ARG2:BAR, HASH:q',
            ],

            [
                'desc' => 'LNC #110',
                'template' => 'ABC{{#block "YES!"}}DEF{{foo}}GHI{{else}}NO~{{/block}}JKL',
                'helpers' => [
                    'block' => function ($name, HelperOptions $options) {
                        return "1-$name-2-" . $options->fn() . '-3';
                    },
                ],
                'data' => ['foo' => 'bar'],
                'expected' => 'ABC1-YES!-2-DEFbarGHI-3JKL',
            ],
            [
                'template' => 'ABC{{#block "YES!"}}TRUE{{else}}DEF{{foo}}GHI{{/block}}JKL',
                'helpers' => [
                    'block' => function ($name, HelperOptions $options) {
                        return "1-$name-2-" . $options->inverse() . '-3';
                    },
                ],
                'data' => ['foo' => 'bar'],
                'expected' => 'ABC1-YES!-2-DEFbarGHI-3JKL',
            ],

            [
                'desc' => 'LNC #114',
                'template' => '{{^myeach .}}OK:{{.}},{{else}}NOT GOOD{{/myeach}}',
                'helpers' => ['myeach' => $myEach],
                'data' => [1, 'foo', 3, 'bar'],
                'expected' => 'NOT GOODNOT GOODNOT GOODNOT GOOD',
            ],

            [
                'desc' => 'LNC #124',
                'template' => '{{list foo bar abc=(lt 10 3) def=(lt 3 10)}}',
                'helpers' => [
                    'lt' => function ($a, $b) {
                        return ($a > $b) ? new SafeString("$a>$b") : '';
                    },
                    'list' => function (...$args) {
                        $out = 'List:';
                        /** @var HelperOptions $opts */
                        $opts = array_pop($args);

                        foreach ($args as $v) {
                            if ($v) {
                                $out .= ")$v , ";
                            }
                        }

                        foreach ($opts->hash as $k => $v) {
                            if ($v) {
                                $out .= "]$k=$v , ";
                            }
                        }
                        return new SafeString($out);
                    },
                ],
                'data' => ['foo' => 'OK!', 'bar' => 'OK2', 'abc' => false, 'def' => 123],
                'expected' => 'List:)OK! , )OK2 , ]abc=10>3 , ',
            ],
            [
                'desc' => 'LNC #124',
                'template' => '{{#if (equal \'OK\' cde)}}YES!{{/if}}',
                'helpers' => ['equal' => $equal],
                'data' => ['cde' => 'OK'],
                'expected' => 'YES!',
            ],
            [
                'desc' => 'LNC #124',
                'template' => '{{#if (equal true (equal \'OK\' cde))}}YES!{{/if}}',
                'helpers' => ['equal' => $equal],
                'data' => ['cde' => 'OK'],
                'expected' => 'YES!',
            ],

            [
                'desc' => 'LNC #125',
                'template' => '{{#if (equal true ( equal \'OK\' cde))}}YES!{{/if}}',
                'helpers' => ['equal' => $equal],
                'data' => ['cde' => 'OK'],
                'expected' => 'YES!',
            ],
            [
                'desc' => 'LNC #125',
                'template' => '{{#if (equal true (equal \' OK\' cde))}}YES!{{/if}}',
                'helpers' => ['equal' => $equal],
                'data' => ['cde' => ' OK'],
                'expected' => 'YES!',
            ],
            [
                'desc' => 'LNC #125',
                'template' => '{{#if (equal true (equal \' ==\' cde))}}YES!{{/if}}',
                'helpers' => ['equal' => $equal],
                'data' => ['cde' => ' =='],
                'expected' => 'YES!',
            ],
            [
                'desc' => 'LNC #125',
                'template' => '{{#if (equal true (equal " ==" cde))}}YES!{{/if}}',
                'helpers' => ['equal' => $equal],
                'data' => ['cde' => ' =='],
                'expected' => 'YES!',
            ],
            [
                'desc' => 'LNC #125',
                'template' => '{{list [ abc] " xyz" \' def\' "==" \'==\' "OK"}}',
                'helpers' => [
                    'list' => function (...$args) {
                        $out = 'List:';
                        array_pop($args);
                        foreach ($args as $v) {
                            if ($v) {
                                $out .= ")$v , ";
                            }
                        }
                        return $out;
                    },
                ],
                'data' => [' abc' => 'YES!'],
                'expected' => 'List:)YES! , ) xyz , ) def , )&#x3D;&#x3D; , )&#x3D;&#x3D; , )OK , ',
            ],

            [
                'desc' => 'LNC #127',
                'template' => '{{#each array}}#{{#if true}}{{name}}-{{../name}}-{{../../name}}-{{../../../name}}{{/if}}##{{#myif true}}{{name}}={{../name}}={{../../name}}={{../../../name}}{{/myif}}###{{#mywith true}}{{name}}~{{../name}}~{{../../name}}~{{../../../name}}{{/mywith}}{{/each}}',
                'data' => ['name' => 'john', 'array' => [1, 2, 3]],
                'helpers' => ['myif' => $myIf, 'mywith' => $myWith],
                // HBS.js output is different due to context coercion (https://github.com/handlebars-lang/handlebars.js/issues/1135):
                // 'expected' => '#-john--##==john=###~john~~#-john--##==john=###~~john~#-john--##==john=###~~john~'
                'expected' => '#-john--##==john=###~~john~#-john--##==john=###~~john~#-john--##==john=###~~john~',
            ],

            [
                'desc' => 'LNC #132',
                'template' => '{{list (keys .)}}',
                'data' => ['foo' => 'bar', 'test' => 'ok'],
                'helpers' => ['keys' => $keys, 'list' => $list],
                'expected' => 'foo,test',
            ],

            [
                'desc' => 'LNC #133',
                'template' => "{{list (keys\n .\n ) \n}}",
                'data' => ['foo' => 'bar', 'test' => 'ok'],
                'helpers' => ['keys' => $keys, 'list' => $list],
                'expected' => 'foo,test',
            ],
            [
                'desc' => 'LNC #133',
                'template' => "{{list\n .\n \n \n}}",
                'data' => ['foo', 'bar', 'test'],
                'helpers' => ['list' => $list],
                'expected' => 'foo,bar,test',
            ],

            [
                'desc' => 'LNC #134',
                'template' => "{{#if 1}}{{list (keys names)}}{{/if}}",
                'data' => ['names' => ['foo' => 'bar', 'test' => 'ok']],
                'helpers' => ['keys' => $keys, 'list' => $list],
                'expected' => 'foo,test',
            ],

            [
                'desc' => 'LNC #138',
                'template' => "{{#each (keys .)}}={{.}}{{/each}}",
                'data' => ['foo' => 'bar', 'test' => 'ok', 'Haha'],
                'helpers' => ['keys' => $keys],
                'expected' => '=foo=test=0',
            ],

            [
                'desc' => 'LNC #140',
                'template' => "{{[a.good.helper] .}}",
                'data' => ['ha', 'hey', 'ho'],
                'helpers' => [
                    'a.good.helper' => fn($arg) => join(',', $arg),
                ],
                'expected' => 'ha,hey,ho',
            ],

            [
                'desc' => 'LNC #141',
                'template' => "{{#with foo}}{{#getThis bar}}{{/getThis}}{{/with}}",
                'data' => ['foo' => ['bar' => 'Good!']],
                'helpers' => ['getThis' => $getThisBar],
                'expected' => 'Good!-Good!',
            ],
            [
                'desc' => 'LNC #141',
                'template' => "{{#with foo}}{{getThis bar}}{{/with}}",
                'data' => ['foo' => ['bar' => 'Good!']],
                'helpers' => ['getThis' => $getThisBar],
                'expected' => 'Good!-Good!',
            ],

            [
                'desc' => 'LNC #143',
                'template' => "{{testString foo bar=\" \"}}",
                'data' => ['foo' => 'good!'],
                'helpers' => ['testString' => $inlineHashProp],
                'expected' => 'good!- ',
            ],
            [
                'desc' => 'LNC #143',
                'template' => "{{testString foo bar=\"\"}}",
                'data' => ['foo' => 'good!'],
                'helpers' => ['testString' => $inlineHashProp],
                'expected' => 'good!-',
            ],
            [
                'desc' => 'LNC #143',
                'template' => "{{testString foo bar=' '}}",
                'data' => ['foo' => 'good!'],
                'helpers' => ['testString' => $inlineHashProp],
                'expected' => 'good!- ',
            ],
            [
                'desc' => 'LNC #143',
                'template' => "{{testString foo bar=''}}",
                'data' => ['foo' => 'good!'],
                'helpers' => ['testString' => $inlineHashProp],
                'expected' => 'good!-',
            ],

            [
                'desc' => 'LNC #153',
                'template' => '{{echo "test[]"}}',
                'helpers' => ['echo' => $echo],
                'expected' => "ECHO: test[]",
            ],
            [
                'desc' => 'LNC #153',
                'template' => '{{echo \'test[]\'}}',
                'helpers' => ['echo' => $echo],
                'expected' => "ECHO: test[]",
            ],

            [
                'desc' => 'LNC #157',
                'template' => '{{{du_mp text=(du_mp "123")}}}',
                'helpers' => [
                    'du_mp' => function (HelperOptions|string $a) {
                        return '>' . print_r($a->hash ?? $a, true);
                    },
                ],
                'expected' => <<<VAREND
                    >Array
                    (
                        [text] => >123
                    )

                    VAREND,
            ],

            [
                'desc' => 'LNC #171',
                'template' => '{{#my_private_each .}}{{@index}}:{{.}},{{/my_private_each}}',
                'data' => ['a', 'b', 'c'],
                'helpers' => [
                    'my_private_each' => function ($context, HelperOptions $options) {
                        $data = $options->data;
                        $out = '';
                        foreach ($context as $idx => $cx) {
                            $data['index'] = $idx;
                            $out .= $options->fn($cx, ['data' => $data]);
                        }
                        return $out;
                    },
                ],
                'expected' => '0:a,1:b,2:c,',
            ],

            [
                'desc' => 'Hooks (https://handlebarsjs.com/guide/hooks.html)',
                'template' => <<<_hbs
                    {{foo}}
                    {{foo "value"}}
                    {{foo 2 true}}
                    {{#foo true}}{{/foo}}
                    {{#foo}}Bar{{/foo}}
                    {{#person}}{{firstName}} {{lastName}}{{/person}}
                    _hbs,
                'data' => ['person' => ['firstName' => 'Yehuda', 'lastName' => 'Katz']],
                'helpers' => [
                    'helperMissing' => function (...$args) {
                        $options = array_pop($args);
                        $argVals = array_map(fn($arg) => is_bool($arg) ? ($arg ? 'true' : 'false') : $arg, $args);
                        return "Missing {$options->name}(" . implode(',', $argVals) . ')';
                    },
                    'blockHelperMissing' => function (mixed $context, HelperOptions $options) {
                        return "Helper '{$options->name}' not found. Printing block: {$options->fn($context)}";
                    },
                ],
                'expected' => <<<_result
                    Missing foo()
                    Missing foo(value)
                    Missing foo(2,true)
                    Missing foo(true)
                    Helper 'foo' not found. Printing block: Bar
                    Helper 'person' not found. Printing block: Yehuda Katz
                    _result,
            ],
            [
                'desc' => 'LNC #201',
                'template' => '{{#foo "test"}}World{{/foo}}',
                'helpers' => [
                    'helperMissing' => fn(string $name, HelperOptions $options) => "$name = {$options->fn()}",
                ],
                'expected' => 'test = World',
            ],

            [
                'desc' => 'LNC #213',
                'template' => '{{#if foo}}foo{{else if bar}}{{#moo moo}}moo{{/moo}}{{/if}}',
                'data' => ['foo' => true],
                'helpers' => ['moo' => fn($arg1) => $arg1 === null],
                'expected' => 'foo',
            ],

            [
                'desc' => 'LNC #227',
                'template' => '{{#if moo}}A{{else if bar}}B{{else foo}}C{{/if}}',
                'helpers' => ['foo' => $returnBlock],
                'expected' => 'C',
            ],

            [
                'desc' => 'LNC #233',
                'template' => '{{#if foo}}FOO{{else}}BAR{{/if}}',
                // Opt out of compile-time inlining so the custom runtime helper is dispatched
                'options' => new Options(knownHelpers: ['if' => false]),
                'helpers' => [
                    'if' => fn($arg, HelperOptions $options) => $options->fn(),
                ],
                'expected' => 'FOO',
            ],

            [
                'desc' => 'LNC #252',
                'template' => '{{foo (lookup bar 1)}}',
                'data' => [
                    'bar' => ['nil', [3, 5]],
                ],
                'helpers' => ['foo' => $testArray],
                'expected' => 'IS_ARRAY',
            ],

            [
                'desc' => 'LNC #253',
                'template' => '{{foo.bar}}',
                'data' => ['foo' => ['bar' => 'OK!']],
                'helpers' => ['foo' => fn() => 'bad'],
                'expected' => 'OK!',
            ],

            [
                'desc' => 'LNC #257',
                'template' => '{{foo a=(foo a=(foo a="ok"))}}',
                'helpers' => [
                    'foo' => fn(HelperOptions $opt) => $opt->hash['a'],
                ],
                'expected' => 'ok',
            ],

            [
                'desc' => 'LNC #268',
                'template' => '{{foo}}{{bar}}',
                'helpers' => [
                    'foo' => function (HelperOptions $opt) {
                        $opt->scope['change'] = true;
                    },
                    'bar' => fn(HelperOptions $opt) => $opt->scope['change'] ? 'ok' : 'bad',
                ],
                'expected' => 'ok',
            ],

            [
                'desc' => 'LNC #281',
                'template' => '{{echo (echo "foo bar (moo).")}}',
                'helpers' => ['echo' => $echo],
                'expected' => 'ECHO: ECHO: foo bar (moo).',
            ],

            [
                'desc' => 'LNC #281',
                'template' => "{{test 'foo bar' (toRegex '^(foo|bar|baz)')}}",
                'helpers' => [
                    'toRegex' => fn($regex) => "/$regex/",
                    'test' => fn(string $str, string $regex) => (bool) preg_match($regex, $str),
                ],
                'expected' => 'true',
            ],

            [
                'desc' => 'LNC #297',
                'template' => '{{test "foo" bar="\" "}}',
                'helpers' => ['test' => $inlineHashProp],
                'expected' => 'foo-&quot; ',
            ],

            [
                'desc' => 'LNC #298',
                'template' => '{{test "\"\"\"" bar="\"\"\""}}',
                'helpers' => ['test' => $inlineHashProp],
                'expected' => '&quot;&quot;&quot;-&quot;&quot;&quot;',
            ],
            [
                'template' => "{{test '\'\'\'' bar='\'\'\''}}",
                'helpers' => ['test' => $inlineHashProp],
                'expected' => '&#x27;&#x27;&#x27;-&#x27;&#x27;&#x27;',
            ],

            [
                'desc' => 'LNC #310',
                'template' => <<<_tpl
                    {{#custom-block 'some-text' data=(custom-helper
                      opt_a='foo'
                      opt_b='bar'
                    )}}...{{/custom-block}}
                    _tpl,
                'helpers' => [
                    'custom-block' => function ($string, HelperOptions $opts) {
                        return strtoupper($string) . '-' . $opts->hash['data'] . $opts->fn();
                    },
                    'custom-helper' => function (HelperOptions $options) {
                        return $options->hash['opt_a'] . $options->hash['opt_b'];
                    },
                ],
                'expected' => 'SOME-TEXT-foobar...',
            ],

            [
                'desc' => 'LNC #315',
                'template' => '{{#each foo}}#{{@key}}({{@index}})={{.}}-{{moo}}-{{@irr}}{{/each}}',
                'helpers' => [
                    'moo' => function (HelperOptions $opts) {
                        $opts->data['irr'] = '123';
                        return '321';
                    },
                ],
                'data' => [
                    'foo' => [
                        'a' => 'b',
                        'c' => 'd',
                        'e' => 'f',
                    ],
                ],
                'expected' => '#a(0)=b-321-123#c(1)=d-321-123#e(2)=f-321-123',
            ],

            [
                'desc' => 'LNC #350',
                'template' => <<<_hbs
                    Before: {{var}}
                    (Setting Variable) {{setvar "var" "Foo"}}
                    After: {{var}}
                    _hbs,
                'data' => ['var' => 'value'],
                'helpers' => [
                    'setvar' => function ($name, $value, HelperOptions $options) {
                        $options->data['root'][$name] = $value;
                    },
                ],
                'expected' => "Before: value\n(Setting Variable) \nAfter: Foo",
            ],

            [
                'desc' => 'LNC #357',
                'template' => '{{echo (echo "foobar(moo).")}}',
                'helpers' => ['echo' => $echo],
                'expected' => 'ECHO: ECHO: foobar(moo).',
            ],
            [
                'desc' => 'LNC #357',
                'template' => "{{{debug (debug 'foobar(moo).')}}}",
                'helpers' => ['debug' => $echo],
                'expected' => 'ECHO: ECHO: foobar(moo).',
            ],
            [
                'desc' => 'LNC #357',
                'template' => '{{echo (echo "foobar(moo)." (echo "moobar(foo)"))}}',
                'helpers' => ['echo' => $echo],
                'expected' => 'ECHO: ECHO: foobar(moo).',
            ],

            [
                'desc' => 'LNC #367',
                'template' => "{{#each (myfunc 'foo(bar)' ) }}{{.}},{{/each}}",
                'helpers' => [
                    'myfunc' => fn($arg) => explode('(', $arg),
                ],
                'expected' => 'foo,bar),',
            ],

            [
                'desc' => 'LNC #371',
                'template' => <<<_tpl
                    {{#myeach '[{"a":"ayy", "b":"bee"},{"a":"zzz", "b":"ccc"}]' as | newContext index | }}
                    Foo {{newContext.a}} {{index}}
                    {{/myeach}}
                    _tpl,
                'helpers' => [
                    'myeach' => function ($context, HelperOptions $options) {
                        $theArray = json_decode($context, true);
                        $ret = '';
                        foreach ($theArray as $i => $value) {
                            $ret .= $options->fn([], ['blockParams' => [$value, $i]]);
                        }
                        return $ret;
                    },
                ],
                'expected' => "Foo ayy 0\nFoo zzz 1\n",
            ],

            [
                'template' => '{{testNull null undefined 1}}',
                'data' => 'test',
                'helpers' => [
                    'testNull' => function ($arg1, $arg2) {
                        return ($arg1 === null && $arg2 === null) ? 'YES!' : 'no';
                    },
                ],
                'expected' => 'YES!',
            ],

            [
                'template' => '{{[helper]}}',
                'helpers' => ['helper' => fn() => 'DEF'],
                'expected' => 'DEF',
            ],

            [
                'template' => '{{#[helper3]}}ABC{{/[helper3]}}',
                'helpers' => ['helper3' => fn() => 'DEF'],
                'expected' => 'DEF',
            ],

            [
                'template' => '{{hash abc=["def=123"]}}',
                'helpers' => ['hash' => $inlineHashArr],
                'data' => ['"def=123"' => 'La!'],
                'expected' => 'abc : La!,',
            ],

            [
                'template' => '{{hash abc=[\'def=123\']}}',
                'helpers' => ['hash' => $inlineHashArr],
                'data' => ["'def=123'" => 'La!'],
                'expected' => 'abc : La!,',
            ],

            [
                'template' => '-{{getroot}}=',
                'helpers' => [
                    'getroot' => fn(HelperOptions $options) => $options->data['root'],
                ],
                'data' => 'ROOT!',
                'expected' => '-ROOT!=',
            ],

            [
                'desc' => 'inverted helpers should support hash arguments',
                'template' => '{{^helper fizz="buzz"}}{{/helper}}',
                'helpers' => [
                    'helper' => fn(HelperOptions $options) => $options->hash['fizz'],
                ],
                'expected' => 'buzz',
            ],
            [
                'template' => '{{#myif foo}}YES{{else}}NO{{/myif}}',
                'helpers' => ['myif' => $myIf],
                'expected' => 'NO',
            ],

            [
                'template' => '{{#myif foo}}YES{{else}}NO{{/myif}}',
                'data' => ['foo' => 1],
                'helpers' => ['myif' => $myIf],
                'expected' => 'YES',
            ],

            [
                'template' => '{{#mylogic 0 foo bar}}YES:{{.}}{{else}}NO:{{.}}{{/mylogic}}',
                'data' => ['foo' => 'FOO', 'bar' => 'BAR'],
                'helpers' => ['mylogic' => $myLogic],
                'expected' => 'NO:BAR',
            ],

            [
                'template' => '{{#mylogic true foo bar}}YES:{{.}}{{else}}NO:{{.}}{{/mylogic}}',
                'data' => ['foo' => 'FOO', 'bar' => 'BAR'],
                'helpers' => ['mylogic' => $myLogic],
                'expected' => 'YES:FOO',
            ],

            [
                'template' => '{{#mywith foo}}YA: {{name}}{{/mywith}}',
                'data' => ['name' => 'OK?', 'foo' => ['name' => 'OK!']],
                'helpers' => ['mywith' => $myWith],
                'expected' => 'YA: OK!',
            ],

            [
                'template' => '{{mydash \'abc\' "dev"}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'helpers' => ['mydash' => $myDash],
                'expected' => 'abc-dev',
            ],

            [
                'template' => '{{mydash \'a b c\' "d e f"}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'helpers' => ['mydash' => $myDash],
                'expected' => 'a b c-d e f',
            ],

            [
                'template' => '{{mydash "abc" (test_array 1)}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'helpers' => [
                    'mydash' => $myDash,
                    'test_array' => $testArray,
                ],
                'expected' => 'abc-NOT_ARRAY',
            ],

            [
                'template' => '{{mydash "abc" (mydash a b)}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'helpers' => ['mydash' => $myDash],
                'expected' => 'abc-a-b',
            ],

            [
                'template' => '{{#equals my_var false}}Equal to false{{else}}Not equal{{/equals}}',
                'data' => ['my_var' => 0],
                'helpers' => ['equals' => $equals],
                'expected' => 'Equal to false',
            ],
            [
                'template' => '{{#equals my_var false}}Equal to false{{else}}Not equal{{/equals}}',
                'data' => ['my_var' => 1],
                'helpers' => ['equals' => $equals],
                'expected' => 'Not equal',
            ],
            [
                'template' => '{{#equals my_var false}}Equal to false{{else}}Not equal{{/equals}}',
                'helpers' => ['equals' => $equals],
                'expected' => 'Not equal',
            ],

            [
                'template' => <<<VAREND
                    <ul>
                     <li>1. {{helper1 name}}</li>
                     <li>2. {{helper1 value}}</li>
                     <li>3. {{helper2 name}}</li>
                     <li>4. {{helper2 value}}</li>
                     <li>9. {{link name}}</li>
                     <li>10. {{link value}}</li>
                     <li>11. {{alink url text}}</li>
                     <li>12. {{{alink url text}}}</li>
                    </ul>
                    VAREND
                ,
                'data' => ['name' => 'John', 'value' => 10000, 'url' => 'http://yahoo.com', 'text' => 'You&Me!'],
                'helpers' => [
                    'helper1' => fn($arg) => is_array($arg) ? '-Array-' : "-$arg-",
                    'helper2' => fn($arg) => is_array($arg) ? '=Array=' : "=$arg=",
                    'link' => function ($arg) {
                        if (is_array($arg)) {
                            $arg = 'Array';
                        }
                        return "<a href=\"{$arg}\">click here</a>";
                    },
                    'alink' => function ($u, $t) {
                        $u = is_array($u) ? 'Array' : $u;
                        $t = is_array($t) ? 'Array' : $t;
                        return "<a href=\"$u\">$t</a>";
                    },
                ],
                'expected' => <<<VAREND
                    <ul>
                     <li>1. -John-</li>
                     <li>2. -10000-</li>
                     <li>3. &#x3D;John&#x3D;</li>
                     <li>4. &#x3D;10000&#x3D;</li>
                     <li>9. &lt;a href&#x3D;&quot;John&quot;&gt;click here&lt;/a&gt;</li>
                     <li>10. &lt;a href&#x3D;&quot;10000&quot;&gt;click here&lt;/a&gt;</li>
                     <li>11. &lt;a href&#x3D;&quot;http://yahoo.com&quot;&gt;You&amp;Me!&lt;/a&gt;</li>
                     <li>12. <a href="http://yahoo.com">You&Me!</a></li>
                    </ul>
                    VAREND,
            ],

            [
                'template' => ">{{helper1 \"===\"}}<",
                'helpers' => [
                    'helper1' => fn($arg) => is_array($arg) ? '-Array-' : "-$arg-",
                ],
                'expected' => ">-&#x3D;&#x3D;&#x3D;-<",
            ],

            [
                'desc' => 'inverted block helper with block params',
                'template' => '{{^helper items as |foo bar baz|}}{{foo}}{{bar}}{{baz}}{{/helper}}',
                'helpers' => [
                    'helper' => function (array $items, HelperOptions $options) {
                        return $options->inverse(null, ['blockParams' => [1, 2, 3]]);
                    },
                ],
                'data' => ['items' => []],
                'expected' => '123',
            ],
            [
                'desc' => 'inverted block helper returning truthy non-string: stringified like JS',
                'template' => '{{^helper}}block{{/helper}}',
                'helpers' => ['helper' => fn() => ['truthy', 'array']],
                'expected' => 'truthy,array',
            ],
            [
                'desc' => 'block helper returning truthy non-string: stringified like JS',
                'template' => '{{#helper}}block{{/helper}}',
                'helpers' => ['helper' => fn() => ['truthy', 'array']],
                'expected' => 'truthy,array',
            ],
            [
                'template' => '{{^helper}}block{{/helper}}',
                'options' => new Options(knownHelpers: ['helper' => true]),
                'helpers' => ['helper' => fn() => ['truthy', 'array']],
                'expected' => 'truthy,array',
            ],
            [
                'desc' => 'block helper returning truthy non-string: stringified like JS',
                'template' => '{{#helper}}block{{/helper}}',
                'options' => new Options(knownHelpers: ['helper' => true]),
                'helpers' => ['helper' => fn() => ['truthy', 'array']],
                'expected' => 'truthy,array',
            ],

            [
                'desc' => 'ensure that literal block path helper names are correctly escaped',
                'template' => '{{#"it\'s"}}YES{{/"it\'s"}}',
                'options' => new Options(knownHelpers: ["it's" => true]),
                'helpers' => ["it's" => fn(HelperOptions $options) => $options->fn()],
                'expected' => 'YES',
            ],

            [
                'desc' => 'closures in data can be used like helpers',
                'template' => '{{test "Hello"}}',
                'data' => ['test' => fn(string $arg) => "$arg runtime data"],
                'expected' => 'Hello runtime data',
            ],
            [
                'desc' => 'helpers always take precedence over data closures',
                'template' => '{{test "Hello"}}',
                'data' => ['test' => fn(string $arg) => "$arg runtime data"],
                'helpers' => ['test' => fn(string $arg) => "$arg runtime helper"],
                'expected' => 'Hello runtime helper',
            ],
        ];
    }

    /**
     * @return list<RegIssue>
     */
    public static function partialProvider(): array
    {
        return [
            [
                'desc' => 'LNC #7',
                'template' => "<p>\n  {{> list}}\n</p>",
                'data' => ['items' => ['Hello', 'World']],
                'options' => new Options(
                    partials: ['list' => "{{#each items}}{{this}}\n{{/each}}"],
                ),
                'expected' => "<p>\n  Hello\n  World\n</p>",
            ],

            [
                'desc' => 'LNC #64',
                'template' => '{{#each foo}} Test! {{this}} {{/each}}{{> test1}} ! >>> {{>recursive}}',
                'options' => new Options(
                    partials: [
                        'test1' => "123\n",
                        'recursive' => "{{#if foo}}{{bar}} -> {{#with foo}}{{>recursive}}{{/with}}{{else}}END!{{/if}}\n",
                    ],
                ),
                'data' => [
                    'bar' => 1,
                    'foo' => [
                        'bar' => 3,
                        'foo' => [
                            'bar' => 5,
                            'foo' => [
                                'bar' => 7,
                                'foo' => [
                                    'bar' => 11,
                                    'foo' => [
                                        'no foo here',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'expected' => " Test! 3  Test! [object Object] 123\n ! >>> 1 -> 3 -> 5 -> 7 -> 11 -> END!\n\n\n\n\n\n",
            ],

            [
                'desc' => 'LNC #83',
                'template' => '{{> tests/test1}}',
                'options' => new Options(
                    partials: ['tests/test1' => "123\n"],
                ),
                'expected' => "123\n",
            ],

            [
                'desc' => 'LNC #88',
                'template' => '{{>test2}}',
                'options' => new Options(
                    partials: [
                        'test2' => "a{{> test1}}b\n",
                        'test1' => "123\n",
                    ],
                ),
                'expected' => "a123\nb\n",
            ],

            [
                'desc' => 'LNC #109',
                'template' => '{{foo}} {{> test}}',
                'options' => new Options(
                    noEscape: true,
                    partials: ['test' => '{{foo}}'],
                ),
                'data' => ['foo' => '<'],
                'expected' => '< <',
            ],

            [
                'desc' => 'LNC #147',
                'template' => '{{> test/test3 foo="bar"}}',
                'data' => ['test' => 'OK!', 'foo' => 'error'],
                'options' => new Options(
                    partials: ['test/test3' => '{{test}}, {{foo}}'],
                ),
                'expected' => 'OK!, bar',
            ],

            [
                'desc' => 'LNC #157',
                'template' => '{{>test_js_partial}}',
                'options' => new Options(
                    partials: [
                        'test_js_partial' => <<<VAREND
                            Test GA....
                            <script>
                            (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){console.log('works!')};})();
                            </script>
                            VAREND,
                    ],
                ),
                'expected' => <<<VAREND
                    Test GA....
                    <script>
                    (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){console.log('works!')};})();
                    </script>
                    VAREND,
            ],

            [
                'desc' => 'LNC #175',
                'template' => 'c{{>test}}d',
                'options' => new Options(
                    partials: ['test' => 'a{{!-- {{each}} haha {{/each}} --}}b'],
                ),
                'expected' => 'cabd',
            ],

            [
                'desc' => 'LNC #204',
                'template' => '{{#> test name="A"}}B{{/test}}{{#> test name="C"}}D{{/test}}',
                'data' => ['bar' => true],
                'options' => new Options(
                    partials: ['test' => '{{name}}:{{> @partial-block}},'],
                ),
                'expected' => 'A:B,C:D,',
            ],

            [
                'desc' => 'LNC #224',
                'template' => '{{#> foo bar}}a,b,{{.}},{{!-- comment --}},d{{/foo}}',
                'data' => ['bar' => 'BA!'],
                'options' => new Options(
                    partials: ['foo' => 'hello, {{> @partial-block}}'],
                ),
                'expected' => 'hello, a,b,BA!,,d',
            ],
            [
                'desc' => 'LNC #224',
                'template' => '{{#> foo bar}}{{#if .}}OK! {{.}}{{else}}no bar{{/if}}{{/foo}}',
                'data' => ['bar' => 'BA!'],
                'options' => new Options(
                    partials: ['foo' => 'hello, {{> @partial-block}}'],
                ),
                'expected' => 'hello, OK! BA!',
            ],

            [
                'desc' => 'LNC #234',
                'template' => '{{> (lookup foo 2)}}',
                'data' => ['foo' => ['a', 'b', 'c']],
                'options' => new Options(
                    partials: [
                        'a' => '1st',
                        'b' => '2nd',
                        'c' => '3rd',
                    ],
                ),
                'expected' => '3rd',
            ],

            [
                'desc' => 'LNC #235',
                'template' => '{{#> "myPartial"}}{{#> myOtherPartial}}{{ @root.foo}}{{/myOtherPartial}}{{/"myPartial"}}',
                'data' => ['foo' => 'hello!'],
                'options' => new Options(
                    partials: [
                        'myPartial' => '<div>outer {{> @partial-block}}</div>',
                        'myOtherPartial' => '<div>inner {{> @partial-block}}</div>',
                    ],
                ),
                'expected' => '<div>outer <div>inner hello!</div></div>',
            ],

            [
                'desc' => 'LNC #236',
                'template' => 'A{{#> foo}}B{{#> bar}}C{{>moo}}D{{/bar}}E{{/foo}}F',
                'options' => new Options(
                    partials: [
                        'foo' => 'FOO>{{> @partial-block}}<FOO',
                        'bar' => 'bar>{{> @partial-block}}<bar',
                        'moo' => 'MOO!',
                    ],
                ),
                'expected' => 'AFOO>Bbar>CMOO!D<barE<FOOF',
            ],

            [
                'desc' => 'LNC #241',
                'template' => '{{#>foo}}{{#*inline "bar"}}GOOD!{{#each .}}>{{.}}{{/each}}{{/inline}}{{/foo}}',
                'data' => ['1', '3', '5'],
                'options' => new Options(
                    partials: [
                        'foo' => 'A{{#>bar}}BAD{{/bar}}B',
                        'moo' => 'oh',
                    ],
                ),
                'expected' => 'AGOOD!>1>3>5B',
            ],

            [
                'desc' => 'LNC #244',
                'template' => '{{#>outer}}content{{/outer}}',
                'data' => ['test' => 'OK'],
                'options' => new Options(
                    partials: [
                        'outer' => 'outer+{{#>nested}}~{{>@partial-block}}~{{/nested}}+outer-end',
                        'nested' => 'nested={{>@partial-block}}=nested-end',
                    ],
                ),
                'expected' => 'outer+nested=~content~=nested-end+outer-end',
            ],

            [
                'desc' => 'LNC #284',
                'template' => '{{> foo}}',
                'options' => new Options(
                    partials: ['foo' => "12'34"],
                ),
                'expected' => "12'34",
            ],
            [
                'desc' => 'LNC #284',
                'template' => '{{> (lookup foo 2)}}',
                'data' => ['foo' => ['a', 'b', 'c']],
                'options' => new Options(
                    partials: [
                        'a' => '1st',
                        'b' => '2nd',
                        'c' => "3'r'd",
                    ],
                ),
                'expected' => "3'r'd",
            ],

            [
                'desc' => 'LNC #292',
                'template' => '{ {{#>outer}} {{#>innerBlock}} Hello {{/innerBlock}} {{>simple}} {{/outer}} }',
                'options' => new Options(
                    partials: [
                        'outer' => '( {{#>nested}} « {{>@partial-block}} » {{/nested}} )',
                        'nested' => '[ {{>@partial-block}} ]',
                        'innerBlock' => '< {{>@partial-block}} >',
                        'simple' => 'World!',
                    ],
                ),
                'expected' => '{ ( [  «  <  Hello  > World!  »  ] ) }',
            ],

            [
                'desc' => 'LNC #295',
                'template' => '{{> MyPartial (newObject name="John Doe") message=(echo message=(echo message="Hello World!"))}}',
                'options' => new Options(
                    partials: ['MyPartial' => '{{name}} says: "{{message}}"'],
                ),
                'helpers' => [
                    'newObject' => fn(HelperOptions $options) => $options->hash,
                    'echo' => fn(HelperOptions $options) => $options->hash['message'],
                ],
                'expected' => 'John Doe says: "Hello World!"',
            ],

            [
                'desc' => 'LNC #302',
                'template' => "{{#*inline \"t1\"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}{{#*inline \"t2\"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}{{#*inline \"t3\"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}",
                'expected' => '',
            ],

            [
                'desc' => 'LNC #303',
                'template' => '{{#*inline "t1"}} {{#if url}} <a /> {{else if imageUrl}} <img /> {{else}} <span /> {{/if}} {{/inline}}',
                'expected' => '',
            ],

            [
                'desc' => 'LNC #316',
                'template' => '{{> StrongPartial text="Use the syntax: {{varName}}."}}',
                'options' => new Options(
                    partials: ['StrongPartial' => '<strong>{{text}}</strong>'],
                ),
                'data' => ['varName' => 'unused'],
                'expected' => '<strong>Use the syntax: {{varName}}.</strong>',
            ],

            [
                'template' => '{{> (pname foo) bar}}',
                'data' => ['bar' => 'OK! SUBEXP+PARTIAL!', 'foo' => 'test/test3'],
                'options' => new Options(
                    partials: ['test/test3' => '{{.}}'],
                ),
                'helpers' => ['pname' => fn($arg) => $arg],
                'expected' => 'OK! SUBEXP+PARTIAL!',
            ],

            [
                'template' => '{{> (partial_name_helper type)}}',
                'data' => [
                    'type' => 'dog',
                    'name' => 'Lucky',
                    'age' => 5,
                ],
                'options' => new Options(
                    partials: [
                        'people' => 'This is {{name}}, he is {{age}} years old.',
                        'animal' => 'This is {{name}}, it is {{age}} years old.',
                        'default' => 'This is {{name}}.',
                    ],
                ),
                'helpers' => [
                    'partial_name_helper' => function (string $type) {
                        return match ($type) {
                            'man', 'woman' => 'people',
                            'dog', 'cat' => 'animal',
                            default => 'default',
                        };
                    },
                ],
                'expected' => 'This is Lucky, it is 5 years old.',
            ],

            [
                'template' => '{{> testpartial newcontext mixed=foo}}',
                'data' => ['foo' => 'OK!', 'newcontext' => ['bar' => 'test']],
                'options' => new Options(
                    partials: ['testpartial' => '{{bar}}-{{mixed}}'],
                ),
                'expected' => 'test-OK!',
            ],

            [
                'desc' => 'ensure that partial names are correctly escaped',
                'template' => '{{> "foo\button\'"}} {{> "bar\\\link"}}',
                'options' => new Options(
                    partials: [
                        'foo\button\'' => 'Button!',
                        'bar\\\link' => 'Link!',
                    ],
                ),
                'expected' => 'Button! Link!',
            ],

            [
                'template' => '{{#each .}}->{{>tests/test3}}{{/each}}',
                'data' => ['a', 'b', 'c'],
                'options' => new Options(
                    partials: ['tests/test3' => 'New context:{{.}}'],
                ),
                'expected' => "->New context:a->New context:b->New context:c",
            ],
            [
                'template' => '{{#each .}}->{{>tests/test3 ../foo}}{{/each}}',
                'data' => ['a', 'foo' => ['d', 'e', 'f']],
                'options' => new Options(
                    partials: ['tests/test3' => 'New context:{{.}}'],
                ),
                'expected' => "->New context:d,e,f->New context:d,e,f",
            ],

            [
                'template' => '{{#*inline}}{{/inline}}',
                'expected' => '',
            ],

            [
                'template' => <<<_tpl
                    <div>
                      {{> partialA}}
                      {{> partialB}}
                    </div>
                    _tpl,
                'options' => new Options(
                    partials: [
                        'partialA' => "<div>\n  Partial A\n  {{> partialB}}\n</div>\n",
                        'partialB' => "<p>\n  Partial B\n</p>\n",
                    ],
                ),
                'expected' => <<<_result
                    <div>
                      <div>
                        Partial A
                        <p>
                          Partial B
                        </p>
                      </div>
                      <p>
                        Partial B
                      </p>
                    </div>
                    _result,
            ],

            [
                'template' => "{{>test1}}\n  {{>test1}}\nDONE\n",
                'options' => new Options(
                    partials: ['test1' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n"],
                ),
                'expected' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n  1:A\n   2:B\n    3:C\n   4:D\n  5:E\nDONE\n",
            ],
            [
                'template' => "{{>test1}}\n  {{>test1}}\nDONE\n",
                'options' => new Options(
                    preventIndent: true,
                    partials: ['test1' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n"],
                ),
                'expected' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n  1:A\n 2:B\n  3:C\n 4:D\n5:E\nDONE\n",
            ],

            [
                'template' => "{{>test}}\n",
                'data' => ['foo' => 'ha', 'bar' => 'hey'],
                'options' => new Options(
                    partials: ['test' => "{{foo}}\n  {{bar}}\n"],
                ),
                'expected' => "ha\n  hey\n",
            ],
            [
                'template' => " {{>test}}\n",
                'data' => ['foo' => 'ha', 'bar' => 'hey'],
                'options' => new Options(
                    preventIndent: true,
                    partials: ['test' => "{{foo}}\n  {{bar}}\n"],
                ),
                'expected' => " ha\n  hey\n",
            ],
            [
                'template' => "\n {{>test}}\n",
                'data' => ['foo' => 'ha', 'bar' => 'hey'],
                'options' => new Options(
                    preventIndent: true,
                    partials: ['test' => "{{foo}}\n  {{bar}}\n"],
                ),
                'expected' => "\n ha\n  hey\n",
            ],

            [
                'template' => "ST:\n{{#foo}}\n {{>test1}}\n{{/foo}}\nOK\n",
                'data' => ['foo' => [1, 2]],
                'options' => new Options(
                    partials: ['test1' => "1:A\n 2:B({{@index}})\n"],
                ),
                'expected' => "ST:\n 1:A\n  2:B(0)\n 1:A\n  2:B(1)\nOK\n",
            ],

            [
                'template' => '{{>foo}} and {{>bar}}',
                'options' => new Options(
                    partialResolver: fn(string $name) => "PARTIAL: $name",
                ),
                'expected' => 'PARTIAL: foo and PARTIAL: bar',
            ],

            [
                'desc' => '{{#* inline}} partials registered inside a block section do not leak out after the block ends',
                'template' => '{{#* inline "p"}}BEFORE{{/inline}}{{#section}}{{#* inline "p"}}INSIDE{{/inline}}{{> p}}{{/section}}{{> p}}',
                'options' => new Options(knownHelpersOnly: true),
                'data' => ['section' => ['x' => 1]],
                'expected' => 'INSIDEBEFORE',
            ],

            [
                'template' => '{{#>foo}}inline\'partial{{/foo}}',
                'expected' => 'inline\'partial',
            ],

            [
                'template' => "{{#> testPartial}}\n before\n  {{#> innerPartial}}\n   inner!\n  {{/innerPartial}}\n after\n {{/testPartial}}",
                'expected' => " before\n   inner!\n after\n",
            ],
        ];
    }

    /**
     * @return list<RegIssue>
     */
    public static function builtInProvider(): array
    {
        return [
            [
                'desc' => 'LNC #81',
                'template' => '{{#with ../person}} {{^name}} Unknown {{/name}} {{/with}}?!',
                'data' => ['parent?!' => ['A', 'B', 'bar' => ['C', 'D', 'E']]],
                'expected' => '?!',
            ],

            [
                'desc' => 'LNC #109',
                'template' => '{{#if "OK"}}it\'s great!{{/if}}',
                'options' => new Options(noEscape: true),
                'expected' => 'it\'s great!',
            ],

            [
                'desc' => 'LNC #199',
                'template' => '{{#if foo}}1{{else if bar}}2{{else}}3{{/if}}',
                'expected' => '3',
            ],
            [
                'desc' => 'LNC #199',
                'template' => '{{#if foo}}1{{else if bar}}2{{/if}}',
                'data' => ['bar' => true],
                'expected' => '2',
            ],
            [
                'desc' => 'LNC #200',
                'template' => '{{#unless 0}}1{{else if foo}}2{{else}}3{{/unless}}',
                'data' => ['foo' => false],
                'expected' => '1',
            ],
            [
                'desc' => 'LNC #201',
                'template' => '{{#unless 0 includeZero=true}}1{{else if foo}}2{{else}}3{{/unless}}',
                'data' => ['foo' => true],
                'expected' => '2',
            ],
            [
                'desc' => 'LNC #202',
                'template' => '{{#unless 0 includeZero=true}}1{{else if foo}}2{{else}}3{{/unless}}',
                'data' => ['foo' => false],
                'expected' => '3',
            ],

            [
                'desc' => 'LNC #206',
                'template' => '{{#with bar}}{{#../foo}}YES!{{/../foo}}{{/with}}',
                'data' => ['foo' => 999, 'bar' => true],
                'expected' => 'YES!',
            ],

            [
                'desc' => 'LNC #213',
                'template' => '{{#with .}}bad{{else}}Good!{{/with}}',
                'expected' => 'Good!',
            ],

            [
                'desc' => 'LNC #227',
                'template' => '{{#if moo}}A{{else if bar}}B{{else with foo}}C{{.}}{{/if}}',
                'data' => ['foo' => 'D'],
                'expected' => 'CD',
            ],
            [
                'desc' => 'LNC #227',
                'template' => '{{#if moo}}A{{else if bar}}B{{else each foo}}C{{.}}{{/if}}',
                'data' => ['foo' => [1, 3, 5]],
                'expected' => 'C1C3C5',
            ],

            [
                'desc' => 'LNC #229',
                'template' => '{{#if foo.bar.moo}}TRUE{{else}}FALSE{{/if}}',
                'expected' => 'FALSE',
            ],

            [
                'desc' => 'LNC #243',
                'template' => '{{lookup . 3}}',
                'data' => ['3' => 'OK'],
                'expected' => 'OK',
            ],
            [
                'desc' => 'LNC #243',
                'template' => '{{lookup . "test"}}',
                'data' => ['test' => 'OK'],
                'expected' => 'OK',
            ],

            [
                'desc' => 'LNC #245',
                'template' => '{{#each foo}}{{#with .}}{{bar}}-{{../../name}}{{/with}}{{/each}}',
                'data' => [
                    'name' => 'bad',
                    'foo' => [['bar' => 1], ['bar' => 2]],
                ],
                'expected' => '1-2-',
            ],

            [
                'desc' => 'LNC #254',
                'template' => '{{#if a}}a{{else if b}}b{{else}}c{{/if}}{{#if a}}a{{else if b}}b{{/if}}',
                'data' => ['b' => 1],
                'expected' => 'bb',
            ],

            [
                'desc' => 'LNC #261',
                'template' => '{{#each foo as |bar|}}?{{bar.[0]}}{{/each}}',
                'data' => ['foo' => [['a'], ['b']]],
                'expected' => '?a?b',
            ],

            [
                'desc' => 'LNC #267',
                'template' => '{{#each . as |v k|}}#{{k}}>{{v}}|{{.}}{{/each}}',
                'data' => ['a' => 'b', 'c' => 'd'],
                'expected' => '#a>b|b#c>d|d',
            ],

            [
                'desc' => 'LNC #313',
                'template' => <<<_tpl
                    {{#if conditionA}}
                      {{#if conditionA1}}
                        Do something then do more stuff conditionally
                        {{#if conditionA1.x}}
                          Do something here
                        {{else if conditionA1.y}}
                          Do something else here
                        {{/if}}
                      {{/if}}
                    {{else if conditionB}}
                      Do something else
                    {{else}}
                      Finally, do this last thing if all else fails
                    {{/if}}
                    _tpl,
                'expected' => "  Finally, do this last thing if all else fails\n",
            ],

            [
                'desc' => 'LNC #369',
                'template' => '{{#each paragraphs}}<p>{{this}}</p>{{else}}<p class="empty">{{foo}}</p>{{/each}}',
                'data' => ['foo' => 'bar'],
                'expected' => '<p class="empty">bar</p>',
            ],

            [
                'desc' => '#3',
                'template' => 'ok{{{lookup . "missing"}}}',
                'expected' => 'ok',
            ],

            [
                'template' => '{{#each . as |v k|}}#{{k}}{{/each}}',
                'data' => ['a' => [], 'c' => []],
                'expected' => '#a#c',
            ],
            [
                'template' => '{{#each . as |item|}}{{item.foo}}{{/each}}',
                'data' => [['foo' => 'bar'], ['foo' => 'baz']],
                'expected' => 'barbaz',
            ],

            [
                'template' => 'A{{#each .}}-{{#each .}}={{.}},{{@key}},{{@index}},{{@../index}}~{{/each}}%{{/each}}B',
                'data' => [['a' => 'b'], ['c' => 'd'], ['e' => 'f']],
                'expected' => 'A-=b,a,0,0~%-=d,c,0,1~%-=f,e,0,2~%B',
            ],

            [
                'template' => '{{#each .}}{{..}}>{{/each}}',
                'data' => ['a', 'b', 'c'],
                'expected' => 'a,b,c>a,b,c>a,b,c>',
            ],

            [
                'desc' => 'inverted each: non-empty array renders nothing, empty array renders body',
                'template' => '{{^each items}}EMPTY{{/each}}',
                'data' => ['items' => ['a', 'b']],
                'expected' => '',
            ],
            [
                'template' => '{{^each items}}EMPTY{{/each}}',
                'data' => ['items' => []],
                'expected' => 'EMPTY',
            ],

            [
                'desc' => 'inverted if with else clause',
                'template' => '{{^if exists}}bad{{else}}OK{{/if}}',
                'data' => ['exists' => true],
                'expected' => 'OK',
            ],

            [
                'desc' => 'ensure that block parameters are correctly escaped',
                'template' => "{{#each items as |[it\\'s] item|}}{{item}}{{/each}}",
                'data' => ['items' => ['one', 'two']],
                'expected' => '01',
            ],

            [
                'template' => '{{#with "{{"}}{{.}}{{/with}}',
                'expected' => '{{',
            ],
            [
                'template' => '{{#with true}}{{.}}{{/with}}',
                'expected' => 'true',
            ],

            [
                'template' => '{{#if .}}YES{{else}}NO{{/if}}',
                'data' => true,
                'expected' => 'YES',
            ],

            [
                'template' => '{{#with items}}OK!{{/with}}',
                'expected' => '',
            ],
            [
                'template' => '{{log}}',
                'expected' => '',
            ],

            [
                'template' => '{{#with people}}Yes , {{name}}{{else}}No, {{name}}{{/with}}',
                'data' => ['people' => ['name' => 'Peter'], 'name' => 'NoOne'],
                'expected' => 'Yes , Peter',
            ],

            [
                'template' => '{{#with people}}Yes , {{name}}{{else}}No, {{name}}{{/with}}',
                'data' => ['name' => 'NoOne'],
                'expected' => 'No, NoOne',
            ],

            [
                'template' => '{{#each foo}}{{@key}}: {{.}},{{/each}}',
                'data' => ['foo' => [1, 'a' => 'b', 5]],
                'expected' => '0: 1,a: b,1: 5,',
            ],

            [
                'template' => '{{#each foo}}{{@key}}: {{.}},{{/each}}',
                'data' => ['foo' => new TwoDimensionIterator(2, 3)],
                'expected' => '0x0: 0,1x0: 0,0x1: 0,1x1: 1,0x2: 0,1x2: 2,',
            ],

            [
                'template' => "   {{#if foo}}\nYES\n{{else}}\nNO\n{{/if}}\n",
                'expected' => "NO\n",
            ],

            [
                'template' => "  {{#each foo}}\n{{@key}}: {{.}}\n{{/each}}\nDONE",
                'data' => ['foo' => ['a' => 'A', 'b' => 'BOY!']],
                'expected' => "a: A\nb: BOY!\nDONE",
            ],

            [
                'template' => "\n{{#each foo~}}\n  <li>{{.}}</li>\n{{~/each}}\n\nOK",
                'data' => ['foo' => ['ha', 'hu']],
                'expected' => "\n<li>ha</li><li>hu</li>\nOK",
            ],
        ];
    }

    /**
     * @return list<RegIssue>
     */
    public static function syntaxProvider(): array
    {
        return [
            [
                'desc' => 'LNC #39',
                'template' => '{{{tt}}}',
                'data' => ['tt' => 'bla bla bla'],
                'expected' => 'bla bla bla',
            ],

            [
                'desc' => 'LNC #46',
                'template' => '{{{this.id}}}, {{a.id}}',
                'data' => ['id' => 'bla bla bla', 'a' => ['id' => 'OK!']],
                'expected' => 'bla bla bla, OK!',
            ],

            [
                'desc' => 'LNC #66',
                'template' => '{{&foo}} , {{foo}}, {{{foo}}}',
                'data' => ['foo' => 'Test & " \' :)'],
                'expected' => 'Test & " \' :) , Test &amp; &quot; &#x27; :), Test & " \' :)',
            ],

            [
                'desc' => 'LNC #90',
                'template' => '{{#items}}{{#value}}{{.}}{{/value}}{{/items}}',
                'data' => ['items' => [['value' => '123']]],
                'expected' => '123',
            ],

            [
                'desc' => 'LNC #125',
                'template' => '{{[ abc]}}',
                'data' => [' abc' => 'YES!'],
                'expected' => 'YES!',
            ],

            [
                'desc' => 'LNC #128',
                'template' => 'foo: {{foo}} , parent foo: {{../foo}}',
                'data' => ['foo' => 'OK'],
                'expected' => 'foo: OK , parent foo: ',
            ],

            [
                'desc' => 'LNC #154',
                'template' => 'O{{! this is comment ! ... }}K!',
                'expected' => "OK!",
            ],

            [
                'desc' => 'LNC #159',
                'template' => '{{#.}}true{{else}}false{{/.}}',
                'data' => new \ArrayObject(),
                'expected' => "false",
            ],

            [
                'desc' => 'non-empty list in a section with {{else}} must iterate, not show the else branch',
                'template' => '{{#items}}{{.}},{{else}}empty{{/items}}',
                'data' => ['items' => ['a', 'b', 'c']],
                'expected' => 'a,b,c,',
            ],
            [
                'desc' => 'non-empty ArrayObject should also iterate',
                'template' => '{{#.}}{{@index}}:{{.}},{{else}}empty{{/.}}',
                'data' => new \ArrayObject(['x', 'y']),
                'expected' => '0:x,1:y,',
            ],

            [
                'desc' => 'LNC #169',
                'template' => '{{{{a}}}}true{{else}}false{{{{/a}}}}',
                'data' => ['a' => true],
                'expected' => "true{{else}}false",
            ],

            [
                'desc' => 'LNC #175',
                'template' => 'a{{!-- {{each}} haha {{/each}} --}}b',
                'expected' => 'ab',
            ],

            [
                'desc' => 'LNC #177',
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'data' => ['a' => true],
                'expected' => ' {{{{b}}}} {{{{/b}}}} ',
            ],
            [
                'desc' => 'LNC #177',
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'data' => ['a' => true],
                'helpers' => ['a' => fn(HelperOptions $options) => $options->fn()],
                'expected' => ' {{{{b}}}} {{{{/b}}}} ',
            ],
            [
                'desc' => 'LNC #177',
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'expected' => '',
            ],

            [
                'desc' => 'LNC #216',
                'template' => '{{foo.length}}',
                'data' => ['foo' => []],
                'expected' => '0',
            ],
            [
                'desc' => 'LNC #255',
                'template' => '{{foo.length}}',
                'data' => ['foo' => [1, 2]],
                'expected' => '2',
            ],

            [
                'desc' => 'LNC #278',
                'template' => '{{#foo}}-{{#bar}}={{moo}}{{/bar}}{{/foo}}',
                'data' => [
                    'foo' => [
                        ['bar' => 0, 'moo' => 'A'],
                        ['bar' => 1, 'moo' => 'B'],
                        ['bar' => false, 'moo' => 'C'],
                        ['bar' => true, 'moo' => 'D'],
                    ],
                ],
                'expected' => '-=-=--=D',
            ],

            [
                'desc' => 'LNC #289',
                'template' => "1\n2\n{{~foo~}}\n3",
                'data' => ['foo' => 'OK'],
                'expected' => "1\n2OK3",
            ],
            [
                'desc' => 'LNC #289',
                'template' => <<<'HBS'
                    1
                    2
                    {{#test}}
                    3TEST
                    {{/test}}
                    4
                    HBS,
                'data' => ['test' => 1],
                'expected' => "1\n2\n3TEST\n4",
            ],
            [
                'desc' => 'LNC #289',
                'template' => "1\n2\n{{~#test}}\n3TEST\n{{/test}}\n4",
                'data' => ['test' => 1],
                'expected' => "1\n23TEST\n4",
            ],
            [
                'desc' => 'LNC #289',
                'template' => "1\n2\n{{#>test}}\n3TEST\n{{/test}}\n4",
                'expected' => "1\n2\n3TEST\n4",
            ],
            [
                'desc' => 'LNC #289',
                'template' => "1\n2\n\n{{#>test}}\n3TEST\n{{/test}}\n4",
                'expected' => "1\n2\n\n3TEST\n4",
            ],
            [
                'desc' => 'LNC #289',
                'template' => "1\n2\n\n{{#>test~}}\n\n3TEST\n{{/test}}\n4",
                'expected' => "1\n2\n\n3TEST\n4",
            ],

            [
                'desc' => 'LNC #290',
                'template' => '{{foo}} }} OK',
                'data' => ['foo' => 'YES'],
                'expected' => 'YES }} OK',
            ],
            [
                'desc' => 'LNC #290',
                'template' => '{{foo}}{{#with "}"}}{{.}}{{/with}}OK',
                'data' => ['foo' => 'YES'],
                'expected' => 'YES}OK',
            ],
            [
                'desc' => 'LNC #290',
                'template' => '{ {{foo}}',
                'data' => ['foo' => 'YES'],
                'expected' => '{ YES',
            ],
            [
                'desc' => 'LNC #290',
                'template' => '{{#with "{{"}}{{.}}{{/with}}{{foo}}{{#with "{{"}}{{.}}{{/with}}',
                'data' => ['foo' => 'YES'],
                'expected' => '{{YES{{',
            ],

            [
                'desc' => 'LNC #344',
                'template' => '{{{{raw}}}} {{bar}} {{{{/raw}}}} {{bar}}',
                'data' => [
                    'raw' => true,
                    'bar' => 'content',
                ],
                'expected' => ' {{bar}}  content',
            ],

            [
                'desc' => 'LNC #370',
                'template' => '{{@root.items.length}}',
                'data' => ['items' => [1, 2, 3]],
                'expected' => '3',
            ],

            [
                'template' => '{{{"{{"}}}',
                'data' => ['{{' => ':D'],
                'expected' => ':D',
            ],
            [
                'template' => '{{{\'{{\'}}}',
                'data' => ['{{' => ':D'],
                'expected' => ':D',
            ],

            [
                'template' => '-{{.}}-',
                'data' => 'abc',
                'expected' => '-abc-',
            ],
            [
                'template' => '-{{this}}-',
                'data' => 123,
                'expected' => '-123-',
            ],

            [
                'desc' => 'data can contain closures',
                'template' => '{{foo}}',
                'data' => ['foo' => fn() => 'OK'],
                'expected' => 'OK',
            ],
            [
                'desc' => 'callable strings or arrays should NOT be treated as functions',
                'template' => '{{foo}}',
                'data' => ['foo' => 'hrtime'],
                'expected' => 'hrtime',
            ],
            [
                'template' => '{{#foo}}OK{{else}}bad{{/foo}}',
                'data' => ['foo' => 'is_string'],
                'expected' => 'OK',
            ],

            [
                'template' => '{{foo}}',
                'expected' => '',
            ],
            [
                'template' => '{{foo.bar}}',
                'expected' => '',
            ],
            [
                'template' => '{{foo.bar}}',
                'data' => ['foo' => []],
                'expected' => '',
            ],

            [
                'template' => "{{foo}}\n  {{bar}}\n",
                'data' => ['foo' => 'ha', 'bar' => 'hey'],
                'expected' => "ha\n  hey\n",
            ],

            [
                'template' => "{{foo}}",
                'data' => ['foo' => 'A&B " \''],
                'options' => new Options(noEscape: true),
                'expected' => "A&B \" '",
            ],

            [
                'template' => "{{foo}}",
                'data' => ['foo' => 'A&B " \' ='],
                'expected' => "A&amp;B &quot; &#x27; &#x3D;",
            ],

            [
                'template' => "{{foo}}",
                'data' => ['foo' => '<a href="#">\'</a>'],
                'expected' => '&lt;a href&#x3D;&quot;#&quot;&gt;&#x27;&lt;/a&gt;',
            ],

            [
                'desc' => 'knownHelpersOnly: array section values are correctly handled',
                'template' => '{{#items}}{{name}}{{/items}}',
                'options' => new Options(knownHelpersOnly: true),
                'data' => ['items' => ['name' => 'foo']],
                'expected' => 'foo',
            ],
            [
                'desc' => 'empty array renders else block',
                'template' => '{{#items}}YES{{else}}NO{{/items}}',
                'options' => new Options(knownHelpersOnly: true),
                'data' => ['items' => []],
                'expected' => 'NO',
            ],
            [
                'desc' => 'non-empty array renders fn block even when else is present',
                'template' => '{{#items}}{{@index}}: {{.}}{{#if @last}}last!{{else}}, {{/if}}{{else}}NO{{/items}}',
                'options' => new Options(knownHelpersOnly: true),
                'data' => ['items' => ['a', 'b']],
                'expected' => '0: a, 1: blast!',
            ],
            [
                'desc' => '../path resolves correctly when array context differs from enclosing context',
                'template' => '{{#items}}{{name}}/{{../name}}{{/items}}',
                'options' => new Options(knownHelpersOnly: true),
                'data' => ['name' => 'outer', 'items' => ['name' => 'inner']],
                'expected' => 'inner/outer',
            ],
            [
                'desc' => '../path inside a true-valued section is empty (matches HBS.js: no depths push for true)',
                'template' => '{{#flag}}{{../name}}{{/flag}}',
                'data' => ['flag' => true, 'name' => 'outer'],
                'expected' => '',
            ],
        ];
    }
}
