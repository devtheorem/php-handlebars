<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Context;
use DevTheorem\Handlebars\Handlebars;
use DevTheorem\Handlebars\HelperOptions;
use DevTheorem\Handlebars\Options;
use DevTheorem\Handlebars\SafeString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type RegIssue array{id?: int, template: string, data?: array<mixed>, options?: Options, expected: string}
 */
class RegressionTest extends TestCase
{
    /**
     * @param RegIssue $issue
     */
    #[DataProvider("issueProvider")]
    public function testIssues(array $issue): void
    {
        $templateSpec = Handlebars::precompile($issue['template'], $issue['options'] ?? new Options());
        $context = Handlebars::getContext();
        $parsed = print_r(Handlebars::$lastParsed, true);
        if ($context->error) {
            $this->fail('Compile failed due to: ' . print_r($context->error, true) . "\nPARSED: $parsed");
        }

        try {
            $template = Handlebars::template($templateSpec);
            $result = $template($issue['data'] ?? null);
        } catch (\Throwable $e) {
            $this->fail("Error: {$e->getMessage()}\nPHP code:\n$templateSpec");
        }
        $this->assertEquals($issue['expected'], $result, "PHP CODE:\n$templateSpec");
    }

    /**
     * @return list<array{RegIssue}>
     */
    public static function issueProvider(): array
    {
        $test_helpers = ['ouch' => fn() => 'ok'];

        $test_helpers2 = ['ouch' => fn() => 'wa!'];

        $test_helpers3 = [
            'ouch' => fn() => 'wa!',
            'god' => fn() => 'yo',
        ];

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

        $issues = [
            [
                'id' => 39,
                'template' => '{{{tt}}}',
                'data' => ['tt' => 'bla bla bla'],
                'expected' => 'bla bla bla',
            ],

            [
                'id' => 44,
                'template' => '<div class="terms-text"> {{render "artists-terms"}} </div>',
                'options' => new Options(
                    helpers: [
                        'render' => function ($view, $data = []) {
                            return 'OK!';
                        },
                    ],
                ),
                'data' => ['tt' => 'bla bla bla'],
                'expected' => '<div class="terms-text"> OK! </div>',
            ],

            [
                'id' => 46,
                'template' => '{{{this.id}}}, {{a.id}}',
                'data' => ['id' => 'bla bla bla', 'a' => ['id' => 'OK!']],
                'expected' => 'bla bla bla, OK!',
            ],

            [
                'id' => 49,
                'template' => '{{date_format}} 1, {{date_format2}} 2, {{date_format3}} 3, {{date_format4}} 4',
                'options' => new Options(
                    helpers: [
                        'date_format' => function () {
                            return "OKOK~1";
                        },
                        'date_format2' => function () {
                            return "OKOK~2";
                        },
                        'date_format3' => function () {
                            return "OKOK~3";
                        },
                        'date_format4' => function () {
                            return "OKOK~4";
                        },
                    ],
                ),
                'expected' => 'OKOK~1 1, OKOK~2 2, OKOK~3 3, OKOK~4 4',
            ],

            [
                'id' => 52,
                'template' => '{{{test_array tmp}}} should be happy!',
                'options' => new Options(
                    helpers: [
                        'test_array' => function ($input) {
                            return is_array($input) ? 'IS_ARRAY' : 'NOT_ARRAY';
                        },
                    ],
                ),
                'data' => ['tmp' => ['A', 'B', 'C']],
                'expected' => 'IS_ARRAY should be happy!',
            ],

            [
                'id' => 62,
                'template' => '{{{test_join @root.foo.bar}}} should be happy!',
                'options' => new Options(
                    helpers: [
                        'test_join' => fn($input) => join('.', $input),
                    ],
                ),
                'data' => ['foo' => ['A', 'B', 'bar' => ['C', 'D']]],
                'expected' => 'C.D should be happy!',
            ],

            [
                'id' => 64,
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
                'id' => 66,
                'template' => '{{&foo}} , {{foo}}, {{{foo}}}',
                'data' => ['foo' => 'Test & " \' :)'],
                'expected' => 'Test & " \' :) , Test &amp; &quot; &#x27; :), Test & " \' :)',
            ],

            [
                'id' => 68,
                'template' => '{{#myeach foo}} Test! {{this}} {{/myeach}}',
                'options' => new Options(
                    helpers: ['myeach' => $myEach],
                ),
                'data' => ['foo' => ['A', 'B', 'bar' => ['C', 'D', 'E']]],
                'expected' => ' Test! A  Test! B  Test! C,D,E ',
            ],

            [
                'id' => 81,
                'template' => '{{#with ../person}} {{^name}} Unknown {{/name}} {{/with}}?!',
                'data' => ['parent?!' => ['A', 'B', 'bar' => ['C', 'D', 'E']]],
                'expected' => '?!',
            ],

            [
                'id' => 83,
                'template' => '{{> tests/test1}}',
                'options' => new Options(
                    partials: [
                        'tests/test1' => "123\n",
                    ],
                ),
                'expected' => "123\n",
            ],

            [
                'id' => 85,
                'template' => '{{helper 1 foo bar="q"}}',
                'options' => new Options(
                    helpers: [
                        'helper' => function ($arg1, $arg2, HelperOptions $options) {
                            return "ARG1:$arg1, ARG2:$arg2, HASH:{$options->hash['bar']}";
                        },
                    ],
                ),
                'data' => ['foo' => 'BAR'],
                'expected' => 'ARG1:1, ARG2:BAR, HASH:q',
            ],

            [
                'id' => 88,
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
                'id' => 90,
                'template' => '{{#items}}{{#value}}{{.}}{{/value}}{{/items}}',
                'data' => ['items' => [['value' => '123']]],
                'expected' => '123',
            ],

            [
                'id' => 109,
                'template' => '{{#if "OK"}}it\'s great!{{/if}}',
                'options' => new Options(noEscape: true),
                'expected' => 'it\'s great!',
            ],

            [
                'id' => 110,
                'template' => 'ABC{{#block "YES!"}}DEF{{foo}}GHI{{/block}}JKL',
                'options' => new Options(
                    helpers: [
                        'block' => function ($name, HelperOptions $options) {
                            return "1-$name-2-" . $options->fn() . '-3';
                        },
                    ],
                ),
                'data' => ['foo' => 'bar'],
                'expected' => 'ABC1-YES!-2-DEFbarGHI-3JKL',
            ],

            [
                'id' => 109,
                'template' => '{{foo}} {{> test}}',
                'options' => new Options(
                    noEscape: true,
                    partials: ['test' => '{{foo}}'],
                ),
                'data' => ['foo' => '<'],
                'expected' => '< <',
            ],

            [
                'id' => 114,
                'template' => '{{^myeach .}}OK:{{.}},{{else}}NOT GOOD{{/myeach}}',
                'options' => new Options(
                    helpers: ['myeach' => $myEach],
                ),
                'data' => [1, 'foo', 3, 'bar'],
                'expected' => 'NOT GOODNOT GOODNOT GOODNOT GOOD',
            ],

            [
                'id' => 124,
                'template' => '{{list foo bar abc=(lt 10 3) def=(lt 3 10)}}',
                'options' => new Options(
                    helpers: [
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
                ),
                'data' => ['foo' => 'OK!', 'bar' => 'OK2', 'abc' => false, 'def' => 123],
                'expected' => 'List:)OK! , )OK2 , ]abc=10>3 , ',
            ],

            [
                'id' => 124,
                'template' => '{{#if (equal \'OK\' cde)}}YES!{{/if}}',
                'options' => new Options(
                    helpers: [
                        'equal' => fn($a, $b) => $a === $b,
                    ],
                ),
                'data' => ['cde' => 'OK'],
                'expected' => 'YES!',
            ],

            [
                'id' => 124,
                'template' => '{{#if (equal true (equal \'OK\' cde))}}YES!{{/if}}',
                'options' => new Options(
                    helpers: [
                        'equal' => fn($a, $b) => $a === $b,
                    ],
                ),
                'data' => ['cde' => 'OK'],
                'expected' => 'YES!',
            ],

            [
                'id' => 125,
                'template' => '{{#if (equal true ( equal \'OK\' cde))}}YES!{{/if}}',
                'options' => new Options(
                    helpers: [
                        'equal' => fn($a, $b) => $a === $b,
                    ],
                ),
                'data' => ['cde' => 'OK'],
                'expected' => 'YES!',
            ],

            [
                'id' => 125,
                'template' => '{{#if (equal true (equal \' OK\' cde))}}YES!{{/if}}',
                'options' => new Options(
                    helpers: [
                        'equal' => fn($a, $b) => $a === $b,
                    ],
                ),
                'data' => ['cde' => ' OK'],
                'expected' => 'YES!',
            ],

            [
                'id' => 125,
                'template' => '{{#if (equal true (equal \' ==\' cde))}}YES!{{/if}}',
                'options' => new Options(
                    helpers: [
                        'equal' => fn($a, $b) => $a === $b,
                    ],
                ),
                'data' => ['cde' => ' =='],
                'expected' => 'YES!',
            ],

            [
                'id' => 125,
                'template' => '{{#if (equal true (equal " ==" cde))}}YES!{{/if}}',
                'options' => new Options(
                    helpers: [
                        'equal' => fn($a, $b) => $a === $b,
                    ],
                ),
                'data' => ['cde' => ' =='],
                'expected' => 'YES!',
            ],

            [
                'id' => 125,
                'template' => '{{[ abc]}}',
                'data' => [' abc' => 'YES!'],
                'expected' => 'YES!',
            ],

            [
                'id' => 125,
                'template' => '{{list [ abc] " xyz" \' def\' "==" \'==\' "OK"}}',
                'options' => new Options(
                    helpers: [
                        'list' => function ($a, $b) {
                            $out = 'List:';
                            $args = func_get_args();
                            array_pop($args);
                            foreach ($args as $v) {
                                if ($v) {
                                    $out .= ")$v , ";
                                }
                            }
                            return $out;
                        },
                    ],
                ),
                'data' => [' abc' => 'YES!'],
                'expected' => 'List:)YES! , ) xyz , ) def , )&#x3D;&#x3D; , )&#x3D;&#x3D; , )OK , ',
            ],

            [
                'id' => 127,
                'template' => '{{#each array}}#{{#if true}}{{name}}-{{../name}}-{{../../name}}-{{../../../name}}{{/if}}##{{#myif true}}{{name}}={{../name}}={{../../name}}={{../../../name}}{{/myif}}###{{#mywith true}}{{name}}~{{../name}}~{{../../name}}~{{../../../name}}{{/mywith}}{{/each}}',
                'data' => ['name' => 'john', 'array' => [1, 2, 3]],
                'options' => new Options(
                    helpers: ['myif' => $myIf, 'mywith' => $myWith],
                ),
                // PENDING ISSUE, check for https://github.com/wycats/handlebars.js/issues/1135
                // 'expected' => '#--john-##==john=###~~john~#--john-##==john=###~~john~#--john-##==john=###~~john~',
                'expected' => '#-john--##=john==###~~john~#-john--##=john==###~~john~#-john--##=john==###~~john~',
            ],

            [
                'id' => 128,
                'template' => 'foo: {{foo}} , parent foo: {{../foo}}',
                'data' => ['foo' => 'OK'],
                'expected' => 'foo: OK , parent foo: ',
            ],

            [
                'id' => 132,
                'template' => '{{list (keys .)}}',
                'data' => ['foo' => 'bar', 'test' => 'ok'],
                'options' => new Options(
                    helpers: [
                        'keys' => function ($arg) {
                            return array_keys($arg);
                        },
                        'list' => function ($arg) {
                            return join(',', $arg);
                        },
                    ],
                ),
                'expected' => 'foo,test',
            ],

            [
                'id' => 133,
                'template' => "{{list (keys\n .\n ) \n}}",
                'data' => ['foo' => 'bar', 'test' => 'ok'],
                'options' => new Options(
                    helpers: [
                        'keys' => function ($arg) {
                            return array_keys($arg);
                        },
                        'list' => function ($arg) {
                            return join(',', $arg);
                        },
                    ],
                ),
                'expected' => 'foo,test',
            ],

            [
                'id' => 133,
                'template' => "{{list\n .\n \n \n}}",
                'data' => ['foo', 'bar', 'test'],
                'options' => new Options(
                    helpers: [
                        'list' => fn($arg) => join(',', $arg),
                    ],
                ),
                'expected' => 'foo,bar,test',
            ],

            [
                'id' => 134,
                'template' => "{{#if 1}}{{list (keys names)}}{{/if}}",
                'data' => ['names' => ['foo' => 'bar', 'test' => 'ok']],
                'options' => new Options(
                    helpers: [
                        'keys' => fn($arg) => array_keys($arg),
                        'list' => fn($arg) => join(',', $arg),
                    ],
                ),
                'expected' => 'foo,test',
            ],

            [
                'id' => 138,
                'template' => "{{#each (keys .)}}={{.}}{{/each}}",
                'data' => ['foo' => 'bar', 'test' => 'ok', 'Haha'],
                'options' => new Options(
                    helpers: [
                        'keys' => fn($arg) => array_keys($arg),
                    ],
                ),
                'expected' => '=foo=test=0',
            ],

            [
                'id' => 140,
                'template' => "{{[a.good.helper] .}}",
                'data' => ['ha', 'hey', 'ho'],
                'options' => new Options(
                    helpers: [
                        'a.good.helper' => fn($arg) => join(',', $arg),
                    ],
                ),
                'expected' => 'ha,hey,ho',
            ],

            [
                'id' => 141,
                'template' => "{{#with foo}}{{#getThis bar}}{{/getThis}}{{/with}}",
                'data' => ['foo' => ['bar' => 'Good!']],
                'options' => new Options(
                    helpers: [
                        'getThis' => function ($input, HelperOptions $options) {
                            return $input . '-' . $options->scope['bar'];
                        },
                    ],
                ),
                'expected' => 'Good!-Good!',
            ],

            [
                'id' => 141,
                'template' => "{{#with foo}}{{getThis bar}}{{/with}}",
                'data' => ['foo' => ['bar' => 'Good!']],
                'options' => new Options(
                    helpers: [
                        'getThis' => function ($input, HelperOptions $options) {
                            return $input . '-' . $options->scope['bar'];
                        },
                    ],
                ),
                'expected' => 'Good!-Good!',
            ],

            [
                'id' => 143,
                'template' => "{{testString foo bar=\" \"}}",
                'data' => ['foo' => 'good!'],
                'options' => new Options(
                    helpers: [
                        'testString' => function ($arg, HelperOptions $options) {
                            return $arg . '-' . $options->hash['bar'];
                        },
                    ],
                ),
                'expected' => 'good!- ',
            ],

            [
                'id' => 143,
                'template' => "{{testString foo bar=\"\"}}",
                'data' => ['foo' => 'good!'],
                'options' => new Options(
                    helpers: [
                        'testString' => function ($arg, HelperOptions $options) {
                            return $arg . '-' . $options->hash['bar'];
                        },
                    ],
                ),
                'expected' => 'good!-',
            ],

            [
                'id' => 143,
                'template' => "{{testString foo bar=' '}}",
                'data' => ['foo' => 'good!'],
                'options' => new Options(
                    helpers: [
                        'testString' => function ($arg, HelperOptions $options) {
                            return $arg . '-' . $options->hash['bar'];
                        },
                    ],
                ),
                'expected' => 'good!- ',
            ],

            [
                'id' => 143,
                'template' => "{{testString foo bar=''}}",
                'data' => ['foo' => 'good!'],
                'options' => new Options(
                    helpers: [
                        'testString' => function ($arg, HelperOptions $options) {
                            return $arg . '-' . $options->hash['bar'];
                        },
                    ],
                ),
                'expected' => 'good!-',
            ],

            [
                'id' => 143,
                'template' => "{{testString foo bar=\" \"}}",
                'data' => ['foo' => 'good!'],
                'options' => new Options(
                    helpers: [
                        'testString' => function ($arg1, HelperOptions $options) {
                            return $arg1 . '-' . $options->hash['bar'];
                        },
                    ],
                ),
                'expected' => 'good!- ',
            ],

            [
                'id' => 147,
                'template' => '{{> test/test3 foo="bar"}}',
                'data' => ['test' => 'OK!', 'foo' => 'error'],
                'options' => new Options(
                    partials: ['test/test3' => '{{test}}, {{foo}}'],
                ),
                'expected' => 'OK!, bar',
            ],

            [
                'id' => 153,
                'template' => '{{echo "test[]"}}',
                'options' => new Options(
                    helpers: [
                        'echo' => fn($in) => "-$in-",
                    ],
                ),
                'expected' => "-test[]-",
            ],

            [
                'id' => 153,
                'template' => '{{echo \'test[]\'}}',
                'options' => new Options(
                    helpers: [
                        'echo' => fn($in) => "-$in-",
                    ],
                ),
                'expected' => "-test[]-",
            ],

            [
                'id' => 154,
                'template' => 'O{{! this is comment ! ... }}K!',
                'expected' => "OK!",
            ],

            [
                'id' => 157,
                'template' => '{{{du_mp text=(du_mp "123")}}}',
                'options' => new Options(
                    helpers: [
                        'du_mp' => function (HelperOptions|string $a) {
                            return '>' . print_r($a->hash ?? $a, true);
                        },
                    ],
                ),
                'expected' => <<<VAREND
                    >Array
                    (
                        [text] => >123
                    )
                    
                    VAREND,
            ],

            [
                'id' => 157,
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
                'id' => 159,
                'template' => '{{#.}}true{{else}}false{{/.}}',
                'data' => new \ArrayObject(),
                'expected' => "false",
            ],

            [
                'id' => 169,
                'template' => '{{{{a}}}}true{{else}}false{{{{/a}}}}',
                'data' => ['a' => true],
                'expected' => "true{{else}}false",
            ],

            [
                'id' => 171,
                'template' => '{{#my_private_each .}}{{@index}}:{{.}},{{/my_private_each}}',
                'data' => ['a', 'b', 'c'],
                'options' => new Options(
                    helpers: [
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
                ),
                'expected' => '0:a,1:b,2:c,',
            ],

            [
                'id' => 175,
                'template' => 'a{{!-- {{each}} haha {{/each}} --}}b',
                'expected' => 'ab',
            ],

            [
                'id' => 175,
                'template' => 'c{{>test}}d',
                'options' => new Options(
                    partials: [
                        'test' => 'a{{!-- {{each}} haha {{/each}} --}}b',
                    ],
                ),
                'expected' => 'cabd',
            ],

            [
                'id' => 177,
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'data' => ['a' => true],
                'expected' => ' {{{{b}}}} {{{{/b}}}} ',
            ],

            [
                'id' => 177,
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'data' => ['a' => true],
                'options' => new Options(
                    helpers: [
                        'a' => fn(HelperOptions $options) => $options->fn(),
                    ],
                ),
                'expected' => ' {{{{b}}}} {{{{/b}}}} ',
            ],

            [
                'id' => 177,
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'expected' => '',
            ],

            [
                'id' => 199,
                'template' => '{{#if foo}}1{{else if bar}}2{{else}}3{{/if}}',
                'expected' => '3',
            ],

            [
                'id' => 199,
                'template' => '{{#if foo}}1{{else if bar}}2{{/if}}',
                'data' => ['bar' => true],
                'expected' => '2',
            ],

            [
                'id' => 200,
                'template' => '{{#unless 0}}1{{else if foo}}2{{else}}3{{/unless}}',
                'data' => ['foo' => false],
                'expected' => '1',
            ],
            [
                'id' => 201,
                'template' => '{{#unless 0 includeZero=true}}1{{else if foo}}2{{else}}3{{/unless}}',
                'data' => ['foo' => true],
                'expected' => '2',
            ],
            [
                'id' => 202,
                'template' => '{{#unless 0 includeZero=true}}1{{else if foo}}2{{else}}3{{/unless}}',
                'data' => ['foo' => false],
                'expected' => '3',
            ],

            [
                'id' => 201,
                'template' => '{{foo "world"}}',
                'options' => new Options(
                    helperResolver: function () {
                        return fn(string $name) => "Hello, $name";
                    },
                ),
                'expected' => 'Hello, world',
            ],
            [
                'id' => 201,
                'template' => '{{#foo "test"}}World{{/foo}}',
                'options' => new Options(
                    helperResolver: function (Context $cx, string $name) {
                        return function ($name, HelperOptions $options) {
                            return "$name = " . $options->fn();
                        };
                    },
                ),
                'expected' => 'test = World',
            ],

            [
                'id' => 204,
                'template' => '{{#> test name="A"}}B{{/test}}{{#> test name="C"}}D{{/test}}',
                'data' => ['bar' => true],
                'options' => new Options(
                    partials: [
                        'test' => '{{name}}:{{> @partial-block}},',
                    ],
                ),
                'expected' => 'A:B,C:D,',
            ],

            [
                'id' => 206,
                'template' => '{{#with bar}}{{#../foo}}YES!{{/../foo}}{{/with}}',
                'data' => ['foo' => 999, 'bar' => true],
                'expected' => 'YES!',
            ],

            [
                'id' => 213,
                'template' => '{{#if foo}}foo{{else if bar}}{{#moo moo}}moo{{/moo}}{{/if}}',
                'data' => ['foo' => true],
                'options' => new Options(
                    helpers: [
                        'moo' => fn($arg1) => $arg1 === null,
                    ],
                ),
                'expected' => 'foo',
            ],

            [
                'id' => 213,
                'template' => '{{#with .}}bad{{else}}Good!{{/with}}',
                'data' => [],
                'expected' => 'Good!',
            ],

            [
                'id' => 216,
                'template' => '{{foo.length}}',
                'data' => ['foo' => []],
                'expected' => '0',
            ],

            [
                'id' => 221,
                'template' => 'a{{ouch}}b',
                'options' => new Options(
                    helpers: $test_helpers,
                ),
                'expected' => 'aokb',
            ],

            [
                'id' => 221,
                'template' => 'a{{ouch}}b',
                'options' => new Options(
                    helpers: $test_helpers2,
                ),
                'expected' => 'awa!b',
            ],

            [
                'id' => 221,
                'template' => 'a{{ouch}}b',
                'options' => new Options(
                    helpers: $test_helpers3,
                ),
                'expected' => 'awa!b',
            ],

            [
                'id' => 224,
                'template' => '{{#> foo bar}}a,b,{{.}},{{!-- comment --}},d{{/foo}}',
                'data' => ['bar' => 'BA!'],
                'options' => new Options(
                    partials: ['foo' => 'hello, {{> @partial-block}}'],
                ),
                'expected' => 'hello, a,b,BA!,,d',
            ],

            [
                'id' => 224,
                'template' => '{{#> foo bar}}{{#if .}}OK! {{.}}{{else}}no bar{{/if}}{{/foo}}',
                'data' => ['bar' => 'BA!'],
                'options' => new Options(
                    partials: ['foo' => 'hello, {{> @partial-block}}'],
                ),
                'expected' => 'hello, OK! BA!',
            ],

            [
                'id' => 227,
                'template' => '{{#if moo}}A{{else if bar}}B{{else foo}}C{{/if}}',
                'options' => new Options(
                    helpers: [
                        'foo' => fn(HelperOptions $options) => $options->fn(),
                    ],
                ),
                'expected' => 'C',
            ],

            [
                'id' => 227,
                'template' => '{{#if moo}}A{{else if bar}}B{{else with foo}}C{{.}}{{/if}}',
                'data' => ['foo' => 'D'],
                'expected' => 'CD',
            ],

            [
                'id' => 227,
                'template' => '{{#if moo}}A{{else if bar}}B{{else each foo}}C{{.}}{{/if}}',
                'data' => ['foo' => [1, 3, 5]],
                'expected' => 'C1C3C5',
            ],

            [
                'id' => 229,
                'template' => '{{#if foo.bar.moo}}TRUE{{else}}FALSE{{/if}}',
                'data' => [],
                'expected' => 'FALSE',
            ],

            [
                'id' => 233,
                'template' => '{{#if foo}}FOO{{else}}BAR{{/if}}',
                'data' => [],
                'options' => new Options(
                    helpers: [
                        'if' => function ($arg, HelperOptions $options) {
                            return $options->fn();
                        },
                    ],
                ),
                'expected' => 'FOO',
            ],

            [
                'id' => 234,
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
                'id' => 235,
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
                'id' => 236,
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
                'id' => 241,
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
                'id' => 243,
                'template' => '{{lookup . 3}}',
                'data' => ['3' => 'OK'],
                'expected' => 'OK',
            ],

            [
                'id' => 243,
                'template' => '{{lookup . "test"}}',
                'data' => ['test' => 'OK'],
                'expected' => 'OK',
            ],

            [
                'id' => 244,
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
                'id' => 245,
                'template' => '{{#each foo}}{{#with .}}{{bar}}-{{../../name}}{{/with}}{{/each}}',
                'data' => ['name' => 'bad', 'foo' => [
                    ['bar' => 1],
                    ['bar' => 2],
                ]],
                'expected' => '1-2-',
            ],

            [
                'id' => 252,
                'template' => '{{foo (lookup bar 1)}}',
                'data' => ['bar' => [
                    'nil',
                    [3, 5],
                ]],
                'options' => new Options(
                    helpers: [
                        'foo' => fn($arg1) => is_array($arg1) ? 'OK' : 'bad',
                    ],
                ),
                'expected' => 'OK',
            ],

            [
                'id' => 253,
                'template' => '{{foo.bar}}',
                'data' => ['foo' => ['bar' => 'OK!']],
                'options' => new Options(
                    helpers: [
                        'foo' => fn() => 'bad',
                    ],
                ),
                'expected' => 'OK!',
            ],

            [
                'id' => 254,
                'template' => '{{#if a}}a{{else if b}}b{{else}}c{{/if}}{{#if a}}a{{else if b}}b{{/if}}',
                'data' => ['b' => 1],
                'expected' => 'bb',
            ],

            [
                'id' => 255,
                'template' => '{{foo.length}}',
                'data' => ['foo' => [1, 2]],
                'expected' => '2',
            ],

            [
                'id' => 256,
                'template' => '{{lookup . "foo"}}',
                'data' => ['foo' => 'ok'],
                'expected' => 'ok',
            ],

            [
                'id' => 257,
                'template' => '{{foo a=(foo a=(foo a="ok"))}}',
                'options' => new Options(
                    helpers: [
                        'foo' => fn(HelperOptions $opt) => $opt->hash['a'],
                    ],
                ),
                'expected' => 'ok',
            ],

            [
                'id' => 261,
                'template' => '{{#each foo as |bar|}}?{{bar.[0]}}{{/each}}',
                'data' => ['foo' => [['a'], ['b']]],
                'expected' => '?a?b',
            ],

            [
                'id' => 267,
                'template' => '{{#each . as |v k|}}#{{k}}>{{v}}|{{.}}{{/each}}',
                'data' => ['a' => 'b', 'c' => 'd'],
                'expected' => '#a>b|b#c>d|d',
            ],

            [
                'id' => 268,
                'template' => '{{foo}}{{bar}}',
                'options' => new Options(
                    helpers: [
                        'foo' => function (HelperOptions $opt) {
                            $opt->scope['change'] = true;
                        },
                        'bar' => function (HelperOptions $opt) {
                            return $opt->scope['change'] ? 'ok' : 'bad';
                        },
                    ],
                ),
                'expected' => 'ok',
            ],

            [
                'id' => 278,
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
                'id' => 281,
                'template' => '{{echo (echo "foo bar (moo).")}}',
                'options' => new Options(
                    helpers: [
                        'echo' => fn($arg1) => "ECHO: $arg1",
                    ],
                ),
                'expected' => 'ECHO: ECHO: foo bar (moo).',
            ],

            [
                'id' => 281,
                'template' => "{{test 'foo bar' (toRegex '^(foo|bar|baz)')}}",
                'options' => new Options(
                    helpers: [
                        'toRegex' => fn($regex) => "/$regex/",
                        'test' => fn(string $str, string $regex) => (bool) preg_match($regex, $str),
                    ],
                ),
                'expected' => 'true',
            ],

            [
                'id' => 284,
                'template' => '{{> foo}}',
                'options' => new Options(
                    partials: ['foo' => "12'34"],
                ),
                'expected' => "12'34",
            ],

            [
                'id' => 284,
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
                'id' => 289,
                'template' => "1\n2\n{{~foo~}}\n3",
                'data' => ['foo' => 'OK'],
                'expected' => "1\n2OK3",
            ],

            [
                'id' => 289,
                'template' => "1\n2\n{{#test}}\n3TEST\n{{/test}}\n4",
                'data' => ['test' => 1],
                'expected' => "1\n2\n3TEST\n4",
            ],

            [
                'id' => 289,
                'template' => "1\n2\n{{~#test}}\n3TEST\n{{/test}}\n4",
                'data' => ['test' => 1],
                'expected' => "1\n23TEST\n4",
            ],

            [
                'id' => 289,
                'template' => "1\n2\n{{#>test}}\n3TEST\n{{/test}}\n4",
                'expected' => "1\n2\n3TEST\n4",
            ],

            [
                'id' => 289,
                'template' => "1\n2\n\n{{#>test}}\n3TEST\n{{/test}}\n4",
                'expected' => "1\n2\n\n3TEST\n4",
            ],

            [
                'id' => 289,
                'template' => "1\n2\n\n{{#>test~}}\n\n3TEST\n{{/test}}\n4",
                'expected' => "1\n2\n\n3TEST\n4",
            ],

            [
                'id' => 290,
                'template' => '{{foo}} }} OK',
                'data' => [
                    'foo' => 'YES',
                ],
                'expected' => 'YES }} OK',
            ],

            [
                'id' => 290,
                'template' => '{{foo}}{{#with "}"}}{{.}}{{/with}}OK',
                'data' => [
                    'foo' => 'YES',
                ],
                'expected' => 'YES}OK',
            ],

            [
                'id' => 290,
                'template' => '{ {{foo}}',
                'data' => [
                    'foo' => 'YES',
                ],
                'expected' => '{ YES',
            ],

            [
                'id' => 290,
                'template' => '{{#with "{{"}}{{.}}{{/with}}{{foo}}{{#with "{{"}}{{.}}{{/with}}',
                'data' => [
                    'foo' => 'YES',
                ],
                'expected' => '{{YES{{',
            ],

            [
                'id' => 295,
                'template' => '{{> MyPartial (newObject name="John Doe") message=(echo message=(echo message="Hello World!"))}}',
                'options' => new Options(
                    helpers: [
                        'newObject' => fn(HelperOptions $options) => $options->hash,
                        'echo' => fn(HelperOptions $options) => $options->hash['message'],
                    ],
                    partials: [
                        'MyPartial' => '{{name}} says: "{{message}}"',
                    ],
                ),
                'expected' => 'John Doe says: "Hello World!"',
            ],

            [
                'id' => 297,
                'template' => '{{test "foo" prop="\" "}}',
                'options' => new Options(
                    helpers: [
                        'test' => function ($arg1, HelperOptions $options) {
                            return "{$arg1} {$options->hash['prop']}";
                        },
                    ],
                ),
                'expected' => 'foo &quot; ',
            ],

            [
                'id' => 298,
                'template' => '{{test "\"\"\"" prop="\"\"\""}}',
                'options' => new Options(
                    helpers: [
                        'test' => function ($arg1, HelperOptions $options) {
                            return "{$arg1} {$options->hash['prop']}";
                        },
                    ],
                ),
                'expected' => '&quot;&quot;&quot; &quot;&quot;&quot;',
            ],

            [
                'id' => 302,
                'template' => "{{#*inline \"t1\"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}{{#*inline \"t2\"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}{{#*inline \"t3\"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}",
                'expected' => '',
            ],

            [
                'id' => 303,
                'template' => '{{#*inline "t1"}} {{#if url}} <a /> {{else if imageUrl}} <img /> {{else}} <span /> {{/if}} {{/inline}}',
                'expected' => '',
            ],

            [
                'id' => 310,
                'template' => <<<_tpl
                    {{#custom-block 'some-text' data=(custom-helper 
                      opt_a='foo'
                      opt_b='bar'
                    )}}...{{/custom-block}}
                    _tpl,
                'options' => new Options(
                    helpers: [
                        'custom-block' => function ($string, HelperOptions $opts) {
                            return strtoupper($string) . '-' . $opts->hash['data'] . $opts->fn();
                        },
                        'custom-helper' => function (HelperOptions $options) {
                            return $options->hash['opt_a'] . $options->hash['opt_b'];
                        },
                    ],
                ),
                'expected' => 'SOME-TEXT-foobar...',
            ],

            [
                'id' => 313,
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
                'id' => 315,
                'template' => '{{#each foo}}#{{@key}}({{@index}})={{.}}-{{moo}}-{{@irr}}{{/each}}',
                'options' => new Options(
                    helpers: [
                        'moo' => function (HelperOptions $opts) {
                            $opts->data['irr'] = '123';
                            return '321';
                        },
                    ],
                ),
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
                'id' => 344,
                'template' => '{{{{raw}}}} {{bar}} {{{{/raw}}}} {{bar}}',
                'data' => [
                    'raw' => true,
                    'bar' => 'content',
                ],
                'expected' => ' {{bar}}  content',
            ],

            [
                'id' => 357,
                'template' => '{{echo (echo "foobar(moo).")}}',
                'options' => new Options(
                    helpers: [
                        'echo' => fn($arg1) => "ECHO: $arg1",
                    ],
                ),
                'expected' => 'ECHO: ECHO: foobar(moo).',
            ],
            [
                'id' => 357,
                'template' => "{{{debug (debug 'foobar(moo).')}}}",
                'options' => new Options(
                    helpers: [
                        'debug' => fn($arg1) => "ECHO: $arg1",
                    ],
                ),
                'expected' => 'ECHO: ECHO: foobar(moo).',
            ],
            [
                'id' => 357,
                'template' => '{{echo (echo "foobar(moo)." (echo "moobar(foo)"))}}',
                'options' => new Options(
                    helpers: [
                        'echo' => fn($arg1) => "ECHO: $arg1",
                    ],
                ),
                'expected' => 'ECHO: ECHO: foobar(moo).',
            ],

            [
                'id' => 367,
                'template' => "{{#each (myfunc 'foo(bar)' ) }}{{.}},{{/each}}",
                'options' => new Options(
                    helpers: [
                        'myfunc' => fn($arg) => explode('(', $arg),
                    ],
                ),
                'expected' => 'foo,bar),',
            ],

            [
                'id' => 369,
                'template' => '{{#each paragraphs}}<p>{{this}}</p>{{else}}<p class="empty">{{foo}}</p>{{/each}}',
                'data' => ['foo' => 'bar'],
                'expected' => '<p class="empty">bar</p>',
            ],

            [
                'id' => 370,
                'template' => '{{@root.items.length}}',
                'data' => ['items' => [1, 2, 3]],
                'expected' => '3',
            ],

            [
                'id' => 371,
                'template' => <<<_tpl
                    {{#myeach '[{"a":"ayy", "b":"bee"},{"a":"zzz", "b":"ccc"}]' as | newContext index | }}
                    Foo {{newContext.a}} {{index}}
                    {{/myeach}}
                    _tpl,
                'options' => new Options(
                    helpers: [
                        'myeach' => function ($context, HelperOptions $options) {
                            $theArray = json_decode($context, true);
                            if (!is_array($theArray)) {
                                return '';
                            }

                            $ret = '';
                            foreach ($theArray as $i => $value) {
                                $ret .= $options->fn([], ['blockParams' => [$value, $i]]);
                            }
                            return $ret;
                        },
                    ],
                ),
                'expected' => "Foo ayy 0\nFoo zzz 1\n",
            ],

            [
                'template' => '{{#each . as |v k|}}#{{k}}{{/each}}',
                'data' => ['a' => [], 'c' => []],
                'expected' => '#a#c',
            ],

            [
                'template' => '{{testNull null undefined 1}}',
                'data' => 'test',
                'options' => new Options(
                    helpers: [
                        'testNull' => function ($arg1, $arg2) {
                            return ($arg1 === null && $arg2 === null) ? 'YES!' : 'no';
                        },
                    ],
                ),
                'expected' => 'YES!',
            ],

            [
                'template' => '{{> (pname foo) bar}}',
                'data' => ['bar' => 'OK! SUBEXP+PARTIAL!', 'foo' => 'test/test3'],
                'options' => new Options(
                    helpers: [
                        'pname' => fn($arg) => $arg,
                    ],
                    partials: ['test/test3' => '{{.}}'],
                ),
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
                    helpers: [
                        'partial_name_helper' => function (string $type) {
                            return match ($type) {
                                'man', 'woman' => 'people',
                                'dog', 'cat' => 'animal',
                                default => 'default',
                            };
                        },
                    ],
                    partials: [
                        'people' => 'This is {{name}}, he is {{age}} years old.',
                        'animal' => 'This is {{name}}, it is {{age}} years old.',
                        'default' => 'This is {{name}}.',
                    ],
                ),
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
                'template' => '{{[helper]}}',
                'options' => new Options(
                    helpers: [
                        'helper' => fn() => 'DEF',
                    ],
                ),
                'data' => [],
                'expected' => 'DEF',
            ],

            [
                'template' => '{{#[helper3]}}ABC{{/[helper3]}}',
                'options' => new Options(
                    helpers: [
                        'helper3' => fn() => 'DEF',
                    ],
                ),
                'data' => [],
                'expected' => 'DEF',
            ],

            [
                'template' => '{{hash abc=["def=123"]}}',
                'options' => new Options(
                    helpers: [
                        'hash' => function (HelperOptions $options) {
                            $ret = '';
                            foreach ($options->hash as $k => $v) {
                                $ret .= "$k : $v,";
                            }
                            return $ret;
                        },
                    ],
                ),
                'data' => ['"def=123"' => 'La!'],
                'expected' => 'abc : La!,',
            ],

            [
                'template' => '{{hash abc=[\'def=123\']}}',
                'options' => new Options(
                    helpers: [
                        'hash' => function (HelperOptions $options) {
                            $ret = '';
                            foreach ($options->hash as $k => $v) {
                                $ret .= "$k : $v,";
                            }
                            return $ret;
                        },
                    ],
                ),
                'data' => ["'def=123'" => 'La!'],
                'expected' => 'abc : La!,',
            ],

            [
                'template' => 'ABC{{#block "YES!"}}DEF{{foo}}GHI{{else}}NO~{{/block}}JKL',
                'options' => new Options(
                    helpers: [
                        'block' => function ($name, HelperOptions $options) {
                            return "1-$name-2-" . $options->fn() . '-3';
                        },
                    ],
                ),
                'data' => ['foo' => 'bar'],
                'expected' => 'ABC1-YES!-2-DEFbarGHI-3JKL',
            ],

            [
                'template' => '-{{getroot}}=',
                'options' => new Options(
                    helpers: [
                        'getroot' => fn(HelperOptions $options) => $options->data['root'],
                    ],
                ),
                'data' => 'ROOT!',
                'expected' => '-ROOT!=',
            ],

            [
                'template' => 'A{{#each .}}-{{#each .}}={{.}},{{@key}},{{@index}},{{@../index}}~{{/each}}%{{/each}}B',
                'data' => [['a' => 'b'], ['c' => 'd'], ['e' => 'f']],
                'expected' => 'A-=b,a,0,0~%-=d,c,0,1~%-=f,e,0,2~%B',
            ],

            [
                'template' => 'ABC{{#block "YES!"}}TRUE{{else}}DEF{{foo}}GHI{{/block}}JKL',
                'options' => new Options(
                    helpers: [
                        'block' => function ($name, HelperOptions $options) {
                            return "1-$name-2-" . $options->inverse() . '-3';
                        },
                    ],
                ),
                'data' => ['foo' => 'bar'],
                'expected' => 'ABC1-YES!-2-DEFbarGHI-3JKL',
            ],

            [
                'template' => '{{#each .}}{{..}}>{{/each}}',
                'data' => ['a', 'b', 'c'],
                'expected' => 'a,b,c>a,b,c>a,b,c>',
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
                'template' => '{{#with "{{"}}{{.}}{{/with}}',
                'expected' => '{{',
            ],

            [
                'template' => '{{good_helper}}',
                'options' => new Options(
                    helpers: [
                        'good_helper' => fn() => 'OK!',
                    ],
                ),
                'expected' => 'OK!',
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
                'template' => '{{#if .}}YES{{else}}NO{{/if}}',
                'data' => true,
                'expected' => 'YES',
            ],

            [
                'template' => '{{foo}}',
                'data' => ['foo' => 'OK'],
                'expected' => 'OK',
            ],

            [
                'template' => '{{foo}}',
                'expected' => '',
            ],

            [
                'template' => '{{#myif foo}}YES{{else}}NO{{/myif}}',
                'options' => new Options(
                    helpers: ['myif' => $myIf],
                ),
                'expected' => 'NO',
            ],

            [
                'template' => '{{#myif foo}}YES{{else}}NO{{/myif}}',
                'data' => ['foo' => 1],
                'options' => new Options(
                    helpers: ['myif' => $myIf],
                ),
                'expected' => 'YES',
            ],

            [
                'template' => '{{#mylogic 0 foo bar}}YES:{{.}}{{else}}NO:{{.}}{{/mylogic}}',
                'data' => ['foo' => 'FOO', 'bar' => 'BAR'],
                'options' => new Options(
                    helpers: ['mylogic' => $myLogic],
                ),
                'expected' => 'NO:BAR',
            ],

            [
                'template' => '{{#mylogic true foo bar}}YES:{{.}}{{else}}NO:{{.}}{{/mylogic}}',
                'data' => ['foo' => 'FOO', 'bar' => 'BAR'],
                'options' => new Options(
                    helpers: ['mylogic' => $myLogic],
                ),
                'expected' => 'YES:FOO',
            ],

            [
                'template' => '{{#mywith foo}}YA: {{name}}{{/mywith}}',
                'data' => ['name' => 'OK?', 'foo' => ['name' => 'OK!']],
                'options' => new Options(
                    helpers: ['mywith' => $myWith],
                ),
                'expected' => 'YA: OK!',
            ],

            [
                'template' => '{{mydash \'abc\' "dev"}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'options' => new Options(
                    helpers: ['mydash' => $myDash],
                ),
                'expected' => 'abc-dev',
            ],

            [
                'template' => '{{mydash \'a b c\' "d e f"}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'options' => new Options(
                    helpers: ['mydash' => $myDash],
                ),
                'expected' => 'a b c-d e f',
            ],

            [
                'template' => '{{mydash "abc" (test_array 1)}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'options' => new Options(
                    helpers: [
                        'mydash' => $myDash,
                        'test_array' => function ($input) {
                            return is_array($input) ? 'IS_ARRAY' : 'NOT_ARRAY';
                        },
                    ],
                ),
                'expected' => 'abc-NOT_ARRAY',
            ],

            [
                'template' => '{{mydash "abc" (myjoin a b)}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'options' => new Options(
                    helpers: [
                        'mydash' => $myDash,
                        'myjoin' => function ($a, $b) {
                            return "$a$b";
                        },
                    ],
                ),
                'expected' => 'abc-ab',
            ],

            [
                'template' => '{{#equals my_var false}}Equal to false{{else}}Not equal{{/equals}}',
                'data' => ['my_var' => 0],
                'options' => new Options(
                    helpers: ['equals' => $equals],
                ),
                'expected' => 'Equal to false',
            ],
            [
                'template' => '{{#equals my_var false}}Equal to false{{else}}Not equal{{/equals}}',
                'data' => ['my_var' => 1],
                'options' => new Options(
                    helpers: ['equals' => $equals],
                ),
                'expected' => 'Not equal',
            ],
            [
                'template' => '{{#equals my_var false}}Equal to false{{else}}Not equal{{/equals}}',
                'data' => [],
                'options' => new Options(
                    helpers: ['equals' => $equals],
                ),
                'expected' => 'Not equal',
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
                'options' => new Options(
                    helpers: [
                        'helper1' => function ($arg) {
                            $arg = is_array($arg) ? 'Array' : $arg;
                            return "-$arg-";
                        },
                        'helper2' => function ($arg) {
                            return is_array($arg) ? '=Array=' : "=$arg=";
                        },
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
                ),
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
                'template' => "{{foo}}\n  {{bar}}\n",
                'data' => ['foo' => 'ha', 'bar' => 'hey'],
                'expected' => "ha\n  hey\n",
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
                'template' => "\n{{#each foo~}}\n  <li>{{.}}</li>\n{{~/each}}\n\nOK",
                'data' => ['foo' => ['ha', 'hu']],
                'expected' => "\n<li>ha</li><li>hu</li>\nOK",
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
                'template' => ">{{helper1 \"===\"}}<",
                'options' => new Options(
                    helpers: [
                        'helper1' => fn($arg) => is_array($arg) ? '-Array-' : "-$arg-",
                    ],
                ),
                'expected' => ">-&#x3D;&#x3D;&#x3D;-<",
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
                'template' => '{{#>foo}}inline\'partial{{/foo}}',
                'expected' => 'inline\'partial',
            ],

            [
                'template' => '{{>foo}} and {{>bar}}',
                'options' => new Options(
                    partialResolver: function (Context $context, string $name) {
                        return "PARTIAL: $name";
                    },
                ),
                'expected' => 'PARTIAL: foo and PARTIAL: bar',
            ],

            [
                'template' => "{{#> testPartial}}\n ERROR: testPartial is not found!\n  {{#> innerPartial}}\n   ERROR: innerPartial is not found!\n   ERROR: innerPartial is not found!\n  {{/innerPartial}}\n ERROR: testPartial is not found!\n {{/testPartial}}",
                'expected' => " ERROR: testPartial is not found!\n   ERROR: innerPartial is not found!\n   ERROR: innerPartial is not found!\n ERROR: testPartial is not found!\n",
            ],

        ];

        return array_map(function ($i) {
            return [$i];
        }, $issues);
    }
}
