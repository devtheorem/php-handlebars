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
 *     template: string, expected: string, data?: mixed, options?: Options,
 *     helpers?: array<string, \Closure>, runtimePartials?: array<string, string>,
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

        $this->assertSame(['array (', "  0 => 'OK!',", ')'], $contents);
        ini_restore('error_log');
    }

    /**
     * @param array<string, \Closure> $helpers
     * @param array<string, string> $runtimePartials
     */
    #[DataProvider("helperProvider")]
    #[DataProvider("partialProvider")]
    #[DataProvider("nestedPartialProvider")]
    #[DataProvider("builtInProvider")]
    #[DataProvider("whitespaceProvider")]
    #[DataProvider("escapeProvider")]
    #[DataProvider("noEscapeProvider")]
    #[DataProvider("preventIndentProvider")]
    #[DataProvider("rawProvider")]
    #[DataProvider("ifElseProvider")]
    #[DataProvider("sectionProvider")]
    #[DataProvider("contextProvider")]
    #[DataProvider("arrayLengthProvider")]
    #[DataProvider("dataClosuresProvider")]
    #[DataProvider("missingDataProvider")]
    #[DataProvider("syntaxProvider")]
    #[DataProvider("subexpressionPathProvider")]
    public function testIssues(
        string $template,
        string $expected,
        mixed $data = null,
        ?Options $options = null,
        array $helpers = [],
        array $runtimePartials = [],
    ): void {
        $templateSpec = Handlebars::precompile($template, $options ?? new Options());

        try {
            $template = Handlebars::template($templateSpec);
            $compiledPartials = array_map(fn($p) => Handlebars::compile($p), $runtimePartials);
            $result = $template($data, ['helpers' => $helpers, 'partials' => $compiledPartials]);
        } catch (\Throwable $e) {
            $this->fail("Error: {$e->getMessage()}\nPHP code:\n$templateSpec");
        }
        $this->assertSame($expected, $result, "PHP code:\n$templateSpec");
    }

    /** @return array<string, RegIssue> */
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
        $getThisBar = fn($input, HelperOptions $options) => $input . '-' . $options->scope['bar'];
        $equal = fn($a, $b) => $a === $b;

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

        $equals = function (mixed $a, mixed $b, HelperOptions $options) {
            // In JS, null is not equal to blank string or false or zero,
            // and when both operands are strings no coercion is performed.
            $equal = ($a === null || $b === null || is_string($a) && is_string($b))
                ? $a === $b
                : $a == $b;

            return $equal ? $options->fn() : $options->inverse();
        };

        return [
            '#2 - nested else if with custom helper' => [
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

            'LNC#49 - custom helper alias' => [
                'template' => '{{date_format date "M j, Y"}}',
                'helpers' => [
                    'date_format' => date_format(...),
                ],
                'data' => ['date' => new \DateTime('2014-06-06')],
                'expected' => 'Jun 6, 2014',
            ],

            'LNC#52 - helper receives array input' => [
                'template' => '{{{test_array tmp}}} should be happy!',
                'helpers' => ['test_array' => $testArray],
                'data' => ['tmp' => ['A', 'B', 'C']],
                'expected' => 'IS_ARRAY should be happy!',
            ],

            'LNC#62 - pass root context value to helper' => [
                'template' => '{{{test_join @root.foo.bar}}} should be happy!',
                'helpers' => ['test_join' => $list],
                'data' => ['foo' => ['A', 'B', 'bar' => ['C', 'D']]],
                'expected' => 'C,D should be happy!',
            ],

            'LNC#68 - custom each' => [
                'template' => '{{#myeach foo}} Test! {{this}} {{/myeach}}',
                'helpers' => ['myeach' => $myEach],
                'data' => ['foo' => ['A', 'B', 'bar' => ['C', 'D', 'E']]],
                'expected' => ' Test! A  Test! B  Test! C,D,E ',
            ],

            'LNC#85 - literal values passed to helper' => [
                'template' => '{{helper 1 foo bar="q"}}',
                'helpers' => [
                    'helper' => function ($arg1, $arg2, HelperOptions $options) {
                        return "ARG1:$arg1, ARG2:$arg2, HASH:{$options->hash['bar']}";
                    },
                ],
                'data' => ['foo' => 'BAR'],
                'expected' => 'ARG1:1, ARG2:BAR, HASH:q',
            ],

            'LNC#110 - helpers work with best performance' => [
                'template' => 'ABC{{#block "YES!"}}DEF{{foo}}GHI{{else}}NO~{{/block}}JKL',
                'helpers' => [
                    'block' => function ($name, HelperOptions $options) {
                        return "1-$name-2-" . $options->fn() . '-3';
                    },
                ],
                'data' => ['foo' => 'bar'],
                'expected' => 'ABC1-YES!-2-DEFbarGHI-3JKL',
            ],
            'LNC#110 - block helper calls inverse' => [
                'template' => 'ABC{{#block "YES!"}}TRUE{{else}}DEF{{foo}}GHI{{/block}}JKL',
                'helpers' => [
                    'block' => function ($name, HelperOptions $options) {
                        return "1-$name-2-" . $options->inverse() . '-3';
                    },
                ],
                'data' => ['foo' => 'bar'],
                'expected' => 'ABC1-YES!-2-DEFbarGHI-3JKL',
            ],

            'LNC#114 - inverse block helpers' => [
                'template' => '{{^myeach .}}bad:{{.}} {{else}}OK:{{.}} {{/myeach}}',
                'helpers' => ['myeach' => $myEach],
                'data' => [1, 3.5, 'foo', true, false, null],
                'expected' => 'OK:1 OK:3.5 OK:foo OK:true OK:false OK: ',
            ],

            'LNC#124 - helper in subexpression' => [
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
            'LNC#124 - helper in subexpression (2)' => [
                'template' => '{{#if (equal \'OK\' cde)}}YES!{{/if}}',
                'helpers' => ['equal' => $equal],
                'data' => ['cde' => 'OK'],
                'expected' => 'YES!',
            ],
            'LNC#124 - helper in subexpression (3)' => [
                'template' => '{{#if (equal true (equal \'OK\' cde))}}YES!{{/if}}',
                'helpers' => ['equal' => $equal],
                'data' => ['cde' => 'OK'],
                'expected' => 'YES!',
            ],

            'LNC#125 - correctly parse space after parenthesis' => [
                'template' => '{{#if (equal true ( equal \'OK\' cde))}}YES!{{/if}}',
                'helpers' => ['equal' => $equal],
                'data' => ['cde' => 'OK'],
                'expected' => 'YES!',
            ],
            'LNC#125 - correctly parse single-quoted strings' => [
                'template' => '{{#if (equal true (equal \' ==\' cde))}}YES!{{/if}}',
                'helpers' => ['equal' => $equal],
                'data' => ['cde' => ' =='],
                'expected' => 'YES!',
            ],
            'LNC#125 - correctly parse double-quoted strings' => [
                'template' => '{{#if (equal true (equal " ==" cde))}}YES!{{/if}}',
                'helpers' => ['equal' => $equal],
                'data' => ['cde' => ' =='],
                'expected' => 'YES!',
            ],
            'LNC#125 - correctly parse path expression with space' => [
                'template' => '{{[ abc]}}',
                'data' => [' abc' => 'YES!'],
                'expected' => 'YES!',
            ],
            'LNC#125 - correctly parse helper arguments' => [
                'template' => '{{list [ abc] " xyz" \' def\' "==" \'==\' "OK"}}',
                'helpers' => [
                    'list' => function (...$args) {
                        $out = 'List:';
                        array_pop($args); // remove options
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

            'LNC#127 - custom block helper creates new scope' => [
                'template' => '{{#each array}}#{{#if true}}{{name}}-{{../name}}-{{../../name}}-{{../../../name}}{{/if}}##{{#myif true}}{{name}}={{../name}}={{../../name}}={{../../../name}}{{/myif}}###{{#mywith true}}{{name}}~{{../name}}~{{../../name}}~{{../../../name}}{{/mywith}}{{/each}}',
                'data' => ['name' => 'john', 'array' => [1, 2, 3]],
                'helpers' => ['myif' => $myIf, 'mywith' => $myWith],
                // HBS.js output is different due to context coercion (https://github.com/handlebars-lang/handlebars.js/issues/1135):
                // 'expected' => '#-john--##==john=###~john~~#-john--##==john=###~~john~#-john--##==john=###~~john~'
                'expected' => '#-john--##==john=###~~john~#-john--##==john=###~~john~#-john--##==john=###~~john~',
            ],

            'LNC#132 - array returned from helper passed to another helper' => [
                'template' => '{{list (keys .)}}',
                'data' => ['foo' => 'bar', 'test' => 'ok'],
                'helpers' => ['keys' => $keys, 'list' => $list],
                'expected' => 'foo,test',
            ],

            'LNC#133 - line breaks in subexpression' => [
                'template' => "{{list (keys\n .\n ) \n}}",
                'data' => ['foo' => 'bar', 'test' => 'ok'],
                'helpers' => ['keys' => $keys, 'list' => $list],
                'expected' => 'foo,test',
            ],
            'LNC#133 - line breaks in mustache' => [
                'template' => "{{list\n .\n \n \n}}",
                'data' => ['foo', 'bar', 'test'],
                'helpers' => ['list' => $list],
                'expected' => 'foo,bar,test',
            ],

            'LNC#134 - helper with subexpression in if' => [
                'template' => "{{#if 1}}{{list (keys names)}}{{/if}}",
                'data' => ['names' => ['foo' => 'bar', 'test' => 'ok']],
                'helpers' => ['keys' => $keys, 'list' => $list],
                'expected' => 'foo,test',
            ],

            'LNC#138 - loop over array returned by subexpression' => [
                'template' => "{{#each (keys .)}}={{.}}{{/each}}",
                'data' => ['foo' => 'bar', 'test' => 'ok', 'Haha'],
                'helpers' => ['keys' => $keys],
                'expected' => '=foo=test=0',
            ],

            'LNC#140 - helper names containing dots' => [
                'template' => "{{[a.good.helper] .}}",
                'data' => ['ha', 'hey', 'ho'],
                'helpers' => ['a.good.helper' => $list],
                'expected' => 'ha,hey,ho',
            ],

            'LNC#141 - block helpers can access current context' => [
                'template' => "{{#with foo}}{{#getThis bar}}{{/getThis}}{{/with}}",
                'data' => ['foo' => ['bar' => 'Good!']],
                'helpers' => ['getThis' => $getThisBar],
                'expected' => 'Good!-Good!',
            ],
            'LNC#141 - inline helpers can access current context' => [
                'template' => "{{#with foo}}{{getThis bar}}{{/with}}",
                'data' => ['foo' => ['bar' => 'Good!']],
                'helpers' => ['getThis' => $getThisBar],
                'expected' => 'Good!-Good!',
            ],

            'LNC#143 - double-quoted space as hash argument' => [
                'template' => "{{testString foo bar=\" \"}}",
                'data' => ['foo' => 'good!'],
                'helpers' => ['testString' => $inlineHashProp],
                'expected' => 'good!- ',
            ],
            'LNC#143 - empty double-quoted string as hash argument' => [
                'template' => "{{testString foo bar=\"\"}}",
                'data' => ['foo' => 'good!'],
                'helpers' => ['testString' => $inlineHashProp],
                'expected' => 'good!-',
            ],
            'LNC#143 - single-quoted space as hash argument' => [
                'template' => "{{testString foo bar=' '}}",
                'data' => ['foo' => 'good!'],
                'helpers' => ['testString' => $inlineHashProp],
                'expected' => 'good!- ',
            ],
            'LNC#143 - empty single-quoted string as hash argument' => [
                'template' => "{{testString foo bar=''}}",
                'data' => ['foo' => 'good!'],
                'helpers' => ['testString' => $inlineHashProp],
                'expected' => 'good!-',
            ],

            'LNC#153 - brackets in double-quoted string argument' => [
                'template' => '{{echo "test[]"}}',
                'helpers' => ['echo' => $echo],
                'expected' => "ECHO: test[]",
            ],
            'LNC#153 - brackets in single-quoted string argument' => [
                'template' => '{{echo \'test[]\'}}',
                'helpers' => ['echo' => $echo],
                'expected' => "ECHO: test[]",
            ],

            'LNC#157 - nested helper in subexpression' => [
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

            'LNC#171 - helpers can set data variables' => [
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

            'Hooks - based on examples at https://handlebarsjs.com/guide/hooks.html' => [
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

            'LNC#201 - resolve missing helpers' => [
                'template' => '{{#foo "test"}}World{{/foo}}',
                'helpers' => [
                    'helperMissing' => fn(string $name, HelperOptions $options) => "$name = {$options->fn()}",
                ],
                'expected' => 'test = World',
            ],

            'LNC#233 - overload if helper' => [
                'template' => '{{#if foo}}FOO{{else}}BAR{{/if}}',
                // Opt out of compile-time inlining so the custom runtime helper is dispatched
                'options' => new Options(knownHelpers: ['if' => false]),
                'helpers' => [
                    'if' => fn($arg, HelperOptions $options) => $options->fn(),
                ],
                'expected' => 'FOO',
            ],

            'LNC#252 - lookup subexpression passed to helper' => [
                'template' => '{{foo (lookup bar 1)}}',
                'data' => [
                    'bar' => ['nil', [3, 5]],
                ],
                'helpers' => ['foo' => $testArray],
                'expected' => 'IS_ARRAY',
            ],

            'LNC#253 - subproperty used rather than helper' => [
                'template' => '{{foo.bar}}',
                'data' => ['foo' => ['bar' => 'OK!']],
                'helpers' => ['foo' => fn() => 'bad'],
                'expected' => 'OK!',
            ],

            'LNC#257 - nested subexpressions' => [
                'template' => '{{foo a=(foo a=(foo a="ok"))}}',
                'helpers' => [
                    'foo' => fn(HelperOptions $opt) => $opt->hash['a'],
                ],
                'expected' => 'ok',
            ],

            'LNC#268 - support updating context inside custom helpers' => [
                'template' => '{{foo}}{{bar}}',
                'helpers' => [
                    'foo' => function (HelperOptions $opt) {
                        $opt->scope['change'] = true;
                    },
                    'bar' => fn(HelperOptions $opt) => $opt->scope['change'] ? 'ok' : 'bad',
                ],
                'expected' => 'ok',
            ],

            'LNC#281 - parentheses in subexpression string' => [
                'template' => '{{echo (echo "foo bar (moo).")}}',
                'helpers' => ['echo' => $echo],
                'expected' => 'ECHO: ECHO: foo bar (moo).',
            ],
            'LNC#281 - parentheses in subexpression string (2)' => [
                'template' => "{{test 'foo bar' (toRegex '^(foo|bar|baz)')}}",
                'helpers' => [
                    'toRegex' => fn($regex) => "/$regex/",
                    'test' => fn(string $str, string $regex) => (bool) preg_match($regex, $str),
                ],
                'expected' => 'true',
            ],

            'LNC#295 - double-nested helper in partial hash parameter' => [
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

            'LNC#297 - escaped double quote followed by a space' => [
                'template' => '{{test "foo" bar="\" "}}',
                'helpers' => ['test' => $inlineHashProp],
                'expected' => 'foo-&quot; ',
            ],

            'LNC#298 - escaping three or more double quotes in sequence' => [
                'template' => '{{test "\"\"\"" bar="\"\"\""}}',
                'helpers' => ['test' => $inlineHashProp],
                'expected' => '&quot;&quot;&quot;-&quot;&quot;&quot;',
            ],
            'LNC#298 - escaping three or more single quotes in sequence' => [
                'template' => "{{test '\'\'\'' bar='\'\'\''}}",
                'helpers' => ['test' => $inlineHashProp],
                'expected' => '&#x27;&#x27;&#x27;-&#x27;&#x27;&#x27;',
            ],

            'LNC#310 - line breaks in hash options' => [
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

            'LNC#315 - {{@index}} in custom helper function' => [
                'template' => '{{#each foo}}#{{@key}}({{@index}})={{.}}-{{moo}}-{{@irr}}{{/each}}',
                'helpers' => [
                    'moo' => function (HelperOptions $opts) {
                        $opts->data['irr'] = '123';
                        return '321';
                    },
                ],
                'data' => [
                    'foo' => ['a' => 'b', 'c' => 'd', 'e' => 'f'],
                ],
                'expected' => '#a(0)=b-321-123#c(1)=d-321-123#e(2)=f-321-123',
            ],

            'LNC#350 - modify root context in helper' => [
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

            'LNC#357 - subexpression containing string with parentheses' => [
                'template' => '{{debug (debug "foobar(moo).")}}',
                'helpers' => ['debug' => $echo],
                'expected' => 'ECHO: ECHO: foobar(moo).',
            ],
            'LNC#357 - subexpression containing string with parentheses (2)' => [
                'template' => "{{{debug (debug 'foobar(moo).')}}}",
                'helpers' => ['debug' => $echo],
                'expected' => 'ECHO: ECHO: foobar(moo).',
            ],
            'LNC#357 - unused helper argument containing string with parentheses' => [
                'template' => '{{debug (debug "foobar(moo)." (debug "moobar(foo)"))}}',
                'helpers' => ['debug' => $echo],
                'expected' => 'ECHO: ECHO: foobar(moo).',
            ],

            'LNC#367 - parentheses in subexpression argument' => [
                'template' => "{{#each (myfunc 'foo(bar)' ) }}{{.}},{{/each}}",
                'helpers' => [
                    'myfunc' => fn($arg) => explode('(', $arg),
                ],
                'expected' => 'foo,bar),',
            ],

            'LNC#371 - block params supported on custom each helper' => [
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

            'null and undefined literals both passed as null' => [
                'template' => '{{testNull null undefined}}',
                'data' => 'test',
                'helpers' => [
                    'testNull' => function ($arg1, $arg2) {
                        return ($arg1 === null && $arg2 === null) ? 'YES!' : 'no';
                    },
                ],
                'expected' => 'YES!',
            ],

            'literal bracket helper mustache' => [
                'template' => '{{[helper]}}',
                'helpers' => ['helper' => fn() => 'DEF'],
                'expected' => 'DEF',
            ],
            'literal bracket block helper' => [
                'template' => '{{#[helper3]}}ABC{{/[helper3]}}',
                'helpers' => ['helper3' => fn() => 'DEF'],
                'expected' => 'DEF',
            ],

            'hash with double-quoted bracket key' => [
                'template' => '{{hash abc=["def=123"]}}',
                'helpers' => ['hash' => $inlineHashArr],
                'data' => ['"def=123"' => 'La!'],
                'expected' => 'abc : La!,',
            ],
            'hash with single-quoted bracket key' => [
                'template' => '{{hash abc=[\'def=123\']}}',
                'helpers' => ['hash' => $inlineHashArr],
                'data' => ["'def=123'" => 'La!'],
                'expected' => 'abc : La!,',
            ],

            'Helper can access root data' => [
                'template' => '-{{getroot}}=',
                'helpers' => [
                    'getroot' => fn(HelperOptions $options) => $options->data['root'],
                ],
                'data' => 'ROOT!',
                'expected' => '-ROOT!=',
            ],

            'inverted helpers should support hash arguments' => [
                'template' => '{{^helper fizz="buzz"}}{{/helper}}',
                'helpers' => [
                    'helper' => fn(HelperOptions $options) => $options->hash['fizz'],
                ],
                'expected' => 'buzz',
            ],

            'inverted helpers should support block params' => [
                'template' => '{{^helper items as |foo bar baz|}}{{foo}}{{bar}}{{baz}}{{/helper}}',
                'helpers' => [
                    'helper' => function (array $items, HelperOptions $options) {
                        return $options->inverse($options->scope, ['blockParams' => [1, 2, 3]]);
                    },
                ],
                'data' => ['items' => []],
                'expected' => '123',
            ],
            'inverse() called with no args at top level: ../ in else body resolves to current scope' => [
                'template' => '{{^myHelper}}{{../parent}}{{/myHelper}}',
                'data' => ['parent' => 'value'],
                'helpers' => [
                    'myHelper' => fn(HelperOptions $options) => $options->inverse(),
                ],
                'expected' => 'value',
            ],
            'inverted block helper returning truthy non-string: stringified like JS' => [
                'template' => '{{^helper}}block{{/helper}}',
                'helpers' => ['helper' => fn() => ['truthy', 'array']],
                'expected' => 'truthy,array',
            ],
            'block helper returning truthy non-string: stringified like JS' => [
                'template' => '{{#helper}}block{{/helper}}',
                'helpers' => ['helper' => fn() => ['truthy', 'array']],
                'expected' => 'truthy,array',
            ],
            'inverted known block helper returning truthy non-string: stringified like JS' => [
                'template' => '{{^helper}}block{{/helper}}',
                'options' => new Options(knownHelpers: ['helper' => true]),
                'helpers' => ['helper' => fn() => ['truthy', 'array']],
                'expected' => 'truthy,array',
            ],
            'known block helper returning truthy non-string: stringified like JS' => [
                'template' => '{{#helper}}block{{/helper}}',
                'options' => new Options(knownHelpers: ['helper' => true]),
                'helpers' => ['helper' => fn() => ['truthy', 'array']],
                'expected' => 'truthy,array',
            ],

            'literal block path helper names should be correctly escaped' => [
                'template' => '{{#"it\'s"}}YES{{/"it\'s"}}',
                'options' => new Options(knownHelpers: ["it's" => true]),
                'helpers' => ["it's" => fn(HelperOptions $options) => $options->fn()],
                'expected' => 'YES',
            ],
            'inverted literal block path routes through blockHelperMissing' => [
                'template' => '{{^"foo"}}EMPTY{{/"foo"}}',
                'data' => ['foo' => false],
                'helpers' => [
                    'blockHelperMissing' => fn(mixed $ctx, HelperOptions $opts) => 'BHM:' . $opts->inverse(),
                ],
                'expected' => 'BHM:EMPTY',
            ],

            'myif with falsy context' => [
                'template' => '{{#myif foo}}YES{{else}}NO{{/myif}}',
                'helpers' => ['myif' => $myIf],
                'expected' => 'NO',
            ],

            'myif with truthy context' => [
                'template' => '{{#myif foo}}YES{{else}}NO{{/myif}}',
                'data' => ['foo' => 1],
                'helpers' => ['myif' => $myIf],
                'expected' => 'YES',
            ],

            'mylogic zero input: else branch' => [
                'template' => '{{#mylogic 0 foo bar}}YES:{{.}}{{else}}NO:{{.}}{{/mylogic}}',
                'data' => ['foo' => 'FOO', 'bar' => 'BAR'],
                'helpers' => ['mylogic' => $myLogic],
                'expected' => 'NO:BAR',
            ],

            'mylogic true input: fn branch' => [
                'template' => '{{#mylogic true foo bar}}YES:{{.}}{{else}}NO:{{.}}{{/mylogic}}',
                'data' => ['foo' => 'FOO', 'bar' => 'BAR'],
                'helpers' => ['mylogic' => $myLogic],
                'expected' => 'YES:FOO',
            ],

            'mywith with nested context' => [
                'template' => '{{#mywith foo}}YA: {{name}}{{/mywith}}',
                'data' => ['name' => 'OK?', 'foo' => ['name' => 'OK!']],
                'helpers' => ['mywith' => $myWith],
                'expected' => 'YA: OK!',
            ],

            'mydash with quoted string args' => [
                'template' => '{{mydash \'abc\' "dev"}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'helpers' => ['mydash' => $myDash],
                'expected' => 'abc-dev',
            ],

            'mydash with spaces in quoted args' => [
                'template' => '{{mydash \'a b c\' "d e f"}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'helpers' => ['mydash' => $myDash],
                'expected' => 'a b c-d e f',
            ],

            'mydash with subexpression arg' => [
                'template' => '{{mydash "abc" (test_array 1)}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'helpers' => [
                    'mydash' => $myDash,
                    'test_array' => $testArray,
                ],
                'expected' => 'abc-NOT_ARRAY',
            ],

            'mydash with nested subexpression' => [
                'template' => '{{mydash "abc" (mydash a b)}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'helpers' => ['mydash' => $myDash],
                'expected' => 'abc-a-b',
            ],

            'equals: 0 equals false' => [
                'template' => '{{#equals my_var false}}Equal to false{{else}}Not equal{{/equals}}',
                'data' => ['my_var' => 0],
                'helpers' => ['equals' => $equals],
                'expected' => 'Equal to false',
            ],
            'equals: 1 does not equal false' => [
                'template' => '{{#equals my_var false}}Equal to false{{else}}Not equal{{/equals}}',
                'data' => ['my_var' => 1],
                'helpers' => ['equals' => $equals],
                'expected' => 'Not equal',
            ],
            'equals: null does not equal false' => [
                'template' => '{{#equals my_var false}}Equal to false{{else}}Not equal{{/equals}}',
                'helpers' => ['equals' => $equals],
                'expected' => 'Not equal',
            ],

            'data state when helper does not create child frame should match Handlebars.js' => [
                'template' => '{{#each foo}}{{#helper}}{{@key}} {{.}} {{@foo}}{{@root.t}}, {{/helper}} {{@key}} {{.}} {{@foo}}{{@root.t}}; {{/each}}',
                'helpers' => [
                    'helper' => fn(HelperOptions $options) => $options->fn($options->scope, ['data' => ['foo' => 'bar']]),
                ],
                'data' => ['t' => 'val', 'foo' => ['1st', '2nd']],
                'expected' => ' 1st bar,  0 1st val;  2nd bar,  1 2nd val; ',
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function partialProvider(): array
    {
        return [
            'LNC#64 - recursive partial support' => [
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

            'LNC#88 - subpartial support' => [
                'template' => '{{>test2}}',
                'options' => new Options(
                    partials: [
                        'test2' => "a{{> test1}}b\n",
                        'test1' => "123\n",
                    ],
                ),
                'expected' => "a123\nb\n",
            ],

            'partial names should be correctly escaped' => [
                'template' => '{{> "foo\button\'"}} {{> "bar\\\link"}}',
                'options' => new Options(
                    partials: [
                        'foo\button\'' => 'Button!',
                        'bar\\\link' => 'Link!',
                    ],
                ),
                'expected' => 'Button! Link!',
            ],

            'LNC#83 - partial names containing slash' => [
                'template' => '{{> tests/test1}}',
                'options' => new Options(
                    partials: ['tests/test1' => "123\n"],
                ),
                'expected' => "123\n",
            ],

            'partial in {{#each}} is passed correct context' => [
                'template' => '{{#each .}}->{{>tests/test3 ../foo}}{{/each}}',
                'data' => ['a', 'foo' => ['d', 'e', 'f']],
                'options' => new Options(
                    partials: ['tests/test3' => 'New context:{{.}}'],
                ),
                'expected' => "->New context:d,e,f->New context:d,e,f",
            ],
            'partial in {{#each}} has correct context' => [
                'template' => '{{#each .}}->{{>tests/test3}}{{/each}}',
                'data' => ['a', 'b', 'c'],
                'options' => new Options(
                    partials: ['tests/test3' => 'New context:{{.}}'],
                ),
                'expected' => "->New context:a->New context:b->New context:c",
            ],

            'LNC#147 - pass hash arguments to partial' => [
                'template' => '{{> test/test3 foo="bar"}}',
                'data' => ['test' => 'OK!', 'foo' => 'error'],
                'options' => new Options(
                    partials: ['test/test3' => '{{test}}, {{foo}}'],
                ),
                'expected' => 'OK!, bar',
            ],

            'LNC#158 - partial can contain JavaScript' => [
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

            'LNC#204 - partial blocks should not duplicate content' => [
                'template' => '{{#> test name="A"}}B{{/test}}{{#> test name="C"}}D{{/test}}',
                'data' => ['bar' => true],
                'options' => new Options(
                    partials: ['test' => '{{name}}:{{> @partial-block}},'],
                ),
                'expected' => 'A:B,C:D,',
            ],

            'LNC#224 - partial block containing a comment' => [
                'template' => '{{#> foo bar}}a,b,{{.}},{{!-- comment --}},d{{/foo}}',
                'data' => ['bar' => 'BA!'],
                'options' => new Options(
                    partials: ['foo' => 'hello, {{> @partial-block}}'],
                ),
                'expected' => 'hello, a,b,BA!,,d',
            ],
            'LNC#224 - partial block containing if/else' => [
                'template' => '{{#> foo bar}}{{#if .}}OK! {{.}}{{else}}no bar{{/if}}{{/foo}}',
                'data' => ['bar' => 'BA!'],
                'options' => new Options(
                    partials: ['foo' => 'hello, {{> @partial-block}}'],
                ),
                'expected' => 'hello, OK! BA!',
            ],

            'LNC#234 - use lookup helper for dynamic partial' => [
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

            'dynamic partial with subexpression and context' => [
                'template' => '{{> (pname foo) bar}}',
                'data' => ['bar' => 'OK! SUBEXP+PARTIAL!', 'foo' => 'test/test3'],
                'options' => new Options(
                    partials: ['test/test3' => '{{.}}'],
                ),
                'helpers' => ['pname' => fn($arg) => $arg],
                'expected' => 'OK! SUBEXP+PARTIAL!',
            ],

            'dynamic partial name from helper' => [
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

            'loadPartial: hasPartial returns false before registration' => [
                'template' => '{{check}} {{> (loadPartial partialName)}} {{check}}',
                'data' => ['partialName' => 'greet', 'name' => 'World'],
                'helpers' => [
                    'check' => function (HelperOptions $options): string {
                        return $options->hasPartial('greet') ? 'found' : 'missing';
                    },
                    'loadPartial' => function (string $name, HelperOptions $options): string {
                        if (!$options->hasPartial($name)) {
                            $options->registerPartial($name, Handlebars::compile('Hello {{name}}'));
                        }
                        return $name;
                    },
                ],
                'expected' => 'missing Hello World found',
            ],

            'LNC#241 - each block inside inline block context' => [
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

            'LNC#284 - partial strings should be escaped' => [
                'template' => '{{> foo}}',
                'options' => new Options(
                    partials: ['foo' => "12'34"],
                ),
                'expected' => "12'34",
            ],
            'LNC#284 - partial strings should be escaped (2)' => [
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

            'LNC#302 - closing {{/if}} in inline partial' => [
                'template' => "{{#*inline \"t1\"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}{{#*inline \"t2\"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}{{#*inline \"t3\"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}",
                'expected' => '',
            ],

            'LNC#303 - {{else if}} in inline partials' => [
                'template' => '{{#*inline "t1"}} {{#if url}} <a /> {{else if imageUrl}} <img /> {{else}} <span /> {{/if}} {{/inline}}',
                'expected' => '',
            ],

            'empty inline partial' => [
                'template' => '{{#*inline}}{{/inline}}',
                'expected' => '',
            ],

            'LNC#316 - curly braces in a string parameter for a partial' => [
                'template' => '{{> StrongPartial text="Use the syntax: {{varName}}."}}',
                'options' => new Options(
                    partials: ['StrongPartial' => '<strong>{{text}}</strong>'],
                ),
                'data' => ['varName' => 'unused'],
                'expected' => '<strong>Use the syntax: {{varName}}.</strong>',
            ],

            'partial with context and hash' => [
                'template' => '{{> testpartial newcontext mixed=foo}}',
                'data' => ['foo' => 'OK!', 'newcontext' => ['bar' => 'test']],
                'options' => new Options(
                    partials: ['testpartial' => '{{bar}}-{{mixed}}'],
                ),
                'expected' => 'test-OK!',
            ],

            'inline partial with single-quote content' => [
                'template' => '{{#>foo}}inline\'partial{{/foo}}',
                'expected' => 'inline\'partial',
            ],

            'partial resolver callback' => [
                'template' => '{{>foo}} and {{>bar}}',
                'options' => new Options(
                    partialResolver: fn(string $name) => "PARTIAL: $name",
                ),
                'expected' => 'PARTIAL: foo and PARTIAL: bar',
            ],

            'nested inline and outer partial block' => [
                'template' => "{{#> testPartial}}\n outer!\n  {{#> innerPartial}}\n   inner!\n   inner!\n  {{/innerPartial}}\n outer!\n {{/testPartial}}",
                'expected' => " outer!\n   inner!\n   inner!\n outer!\n",
            ],

            'Prior partial call should not suppress later block syntax failover content' => [
                'template' => '{{#if condition}}{{> foo}}{{/if}} {{#> foo}}Failover{{/foo}}',
                'data' => ['condition' => false],
                'expected' => ' Failover',
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function nestedPartialProvider(): array
    {
        return [
            'LNC#235 - nested partial blocks' => [
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

            'LNC#236 - more nested partial blocks' => [
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

            'LNC#244 - nested partial blocks' => [
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

            'LNC#292 - nested compile-time and runtime partials should render correctly' => [
                'template' => '{{#>outer}} {{#>compiledBlock}} inner compiledBlock {{/compiledBlock}} {{>normalTemplate}} {{/outer}}',
                'options' => new Options(
                    partials: [
                        'outer' => 'outer+{{#>nested}}~{{>@partial-block}}~{{/nested}}+outer-end',
                        'nested' => 'nested={{>@partial-block}}=nested-end',
                    ],
                ),
                'runtimePartials' => [
                    'compiledBlock' => 'compiledBlock !!! {{>@partial-block}} !!! compiledBlock',
                    'normalTemplate' => 'normalTemplate',
                ],
                'expected' => 'outer+nested=~ compiledBlock !!!  inner compiledBlock  !!! compiledBlock normalTemplate ~=nested-end+outer-end',
            ],
            'LNC#292 - nested compile-time partials should render correctly' => [
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
            'LNC#292 - nested runtime partials should render correctly' => [
                'template' => '{ {{#>outer}} {{#>innerBlock}} Hello {{/innerBlock}} {{>simple}} {{/outer}} }',
                'runtimePartials' => [
                    'outer' => '( {{#>nested}} « {{>@partial-block}} » {{/nested}} )',
                    'nested' => '[ {{>@partial-block}} ]',
                    'innerBlock' => '< {{>@partial-block}} >',
                    'simple' => 'World!',
                ],
                'expected' => '{ ( [  «  <  Hello  > World!  »  ] ) }',
            ],

            'partial called with child context must not corrupt root $in reference' => [
                'template' => '{{heading}} {{#each items}}{{> item}}{{/each}} {{heading}}',
                'data' => ['heading' => 'Title', 'items' => ['a', 'b']],
                'runtimePartials' => ['item' => '({{.}})'],
                'expected' => 'Title (a)(b) Title',
            ],

            'LNC#341 - render-time partials can access @partial-block' => [
                'template' => '{{#> MyPartial child}}This <b>text</b> was sent from the template to the partial.{{/MyPartial}}',
                'runtimePartials' => [
                    'MyPartial' => '{{name}} says: “{{> @partial-block }}”',
                ],
                'data' => ['child' => ['name' => 'Jason']],
                'expected' => 'Jason says: “This <b>text</b> was sent from the template to the partial.”',
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function builtInProvider(): array
    {
        return [
            '#3 - lookup with non-existent key' => [
                'template' => 'ok{{{lookup . "missing"}}}',
                'expected' => 'ok',
            ],
            'LNC#243 - lookup with dot' => [
                'template' => '{{lookup . 3}}',
                'data' => ['3' => 'OK'],
                'expected' => 'OK',
            ],
            'LNC#243 - lookup with dot (2)' => [
                'template' => '{{lookup . "test"}}',
                'data' => ['test' => 'OK'],
                'expected' => 'OK',
            ],

            'LNC#245 - with inside each' => [
                'template' => '{{#each foo}}{{#with .}}{{bar}}-{{../../name}}{{/with}}{{/each}}',
                'data' => [
                    'name' => 'bad',
                    'foo' => [['bar' => 1], ['bar' => 2]],
                ],
                'expected' => '1-2-',
            ],

            'LNC#261 - block param containing array' => [
                'template' => '{{#each foo as |bar|}}?{{bar.[0]}}{{/each}}',
                'data' => ['foo' => [['a'], ['b']]],
                'expected' => '?a?b',
            ],

            'LNC#267 - block params not containing array' => [
                'template' => '{{#each . as |v k|}}#{{k}}>{{v}}|{{.}}{{/each}}',
                'data' => ['a' => 'b', 'c' => 'd'],
                'expected' => '#a>b|b#c>d|d',
            ],

            'LNC#369 - input data of current scope passed to {{else}} of {{#each}}' => [
                'template' => '{{#each paragraphs}}<p>{{this}}</p>{{else}}<p class="empty">{{foo}}</p>{{/each}}',
                'data' => ['foo' => 'bar'],
                'expected' => '<p class="empty">bar</p>',
            ],

            'each with block params: key only' => [
                'template' => '{{#each . as |v k|}}#{{k}}{{/each}}',
                'data' => ['a' => [], 'c' => []],
                'expected' => '#a#c',
            ],
            'each with block params: item property' => [
                'template' => '{{#each . as |item|}}{{item.foo}}{{/each}}',
                'data' => [['foo' => 'bar'], ['foo' => 'baz']],
                'expected' => 'barbaz',
            ],

            'nested each with @index depth tracking' => [
                'template' => 'A{{#each .}}-{{#each .}}={{.}},{{@key}},{{@index}},{{@../index}}~{{/each}}%{{/each}}B',
                'data' => [['a' => 'b'], ['c' => 'd'], ['e' => 'f']],
                'expected' => 'A-=b,a,0,0~%-=d,c,0,1~%-=f,e,0,2~%B',
            ],

            'each with parent reference' => [
                'template' => '{{#each .}}{{..}}>{{/each}}',
                'data' => ['a', 'b', 'c'],
                'expected' => 'a,b,c>a,b,c>a,b,c>',
            ],

            'inverted each: non-empty array renders nothing' => [
                'template' => '{{^each items}}EMPTY{{/each}}',
                'data' => ['items' => ['a', 'b']],
                'expected' => '',
            ],
            'inverted each: empty array renders body' => [
                'template' => '{{^each items}}EMPTY{{/each}}',
                'data' => ['items' => []],
                'expected' => 'EMPTY',
            ],

            'ensure that block parameters are correctly escaped' => [
                'template' => "{{#each items as |[it\\'s] item|}}{{item}}{{/each}}",
                'data' => ['items' => ['one', 'two']],
                'expected' => '01',
            ],

            'each over array with @key' => [
                'template' => '{{#each foo}}{{@key}}: {{.}},{{/each}}',
                'data' => ['foo' => [1, 'a' => 'b', 5]],
                'expected' => '0: 1,a: b,1: 5,',
            ],
            'each over custom iterator' => [
                'template' => '{{#each foo}}{{@key}}: {{.}},{{/each}}',
                'data' => ['foo' => new TwoDimensionIterator(2, 3)],
                'expected' => '0x0: 0,1x0: 0,0x1: 0,1x1: 1,0x2: 0,1x2: 2,',
            ],

            'empty array renders else block' => [
                'template' => '{{#with .}}bad{{else}}Good!{{/with}}',
                'data' => [],
                'expected' => 'Good!',
            ],
            'with using {{ as string value' => [
                'template' => '{{#with "{{"}}{{.}}{{/with}}',
                'expected' => '{{',
            ],
            'with using boolean true' => [
                'template' => '{{#with true}}{{.}}{{/with}}',
                'expected' => 'true',
            ],
            'with missing key renders empty' => [
                'template' => '{{#with items}}OK!{{/with}}',
                'expected' => '',
            ],
            'with truthy object: fn branch' => [
                'template' => '{{#with people}}Yes , {{name}}{{else}}No, {{name}}{{/with}}',
                'data' => ['people' => ['name' => 'Peter'], 'name' => 'NoOne'],
                'expected' => 'Yes , Peter',
            ],
            'with falsy: else branch' => [
                'template' => '{{#with people}}Yes , {{name}}{{else}}No, {{name}}{{/with}}',
                'data' => ['name' => 'NoOne'],
                'expected' => 'No, NoOne',
            ],

            'bare log renders empty' => [
                'template' => '{{log}}',
                'expected' => '',
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function whitespaceProvider(): array
    {
        return [
            '#7 - correct spacing for each block in partial' => [
                'template' => "<p>\n  {{> list}}\n</p>",
                'data' => ['items' => ['Hello', 'World']],
                'options' => new Options(
                    partials: ['list' => "{{#each items}}{{this}}\n{{/each}}"],
                ),
                'expected' => "<p>\n  Hello\n  World\n</p>",
            ],

            'LNC#289 - whitespace control' => [
                'template' => "1\n2\n{{~foo~}}\n3",
                'data' => ['foo' => 'OK'],
                'expected' => "1\n2OK3",
            ],
            'LNC#289 - whitespace control (2)' => [
                'template' => "1\n2\n{{#test}}\n3TEST\n{{/test}}\n4",
                'data' => ['test' => 1],
                'expected' => "1\n2\n3TEST\n4",
            ],
            'LNC#289 - whitespace control (3)' => [
                'template' => "1\n2\n{{~#test}}\n3TEST\n{{/test}}\n4",
                'data' => ['test' => 1],
                'expected' => "1\n23TEST\n4",
            ],
            'LNC#289 - whitespace control (4)' => [
                'template' => "1\n2\n{{#>test}}\n3TEST\n{{/test}}\n4",
                'expected' => "1\n2\n3TEST\n4",
            ],
            'LNC#289 - whitespace control (5)' => [
                'template' => "1\n2\n\n{{#>test}}\n3TEST\n{{/test}}\n4",
                'expected' => "1\n2\n\n3TEST\n4",
            ],
            'LNC#289 - whitespace control (6)' => [
                'template' => "1\n2\n\n{{#>test~}}\n\n3TEST\n{{/test}}\n4",
                'expected' => "1\n2\n\n3TEST\n4",
            ],

            'each with whitespace stripping both sides' => [
                'template' => "\n{{#each foo~}}\n  <li>{{.}}</li>\n{{~/each}}\n\nOK",
                'data' => ['foo' => ['ha', 'hu']],
                'expected' => "\n<li>ha</li><li>hu</li>\nOK",
            ],

            'if with leading whitespace' => [
                'template' => "   {{#if foo}}\nYES\n{{else}}\nNO\n{{/if}}\n",
                'expected' => "NO\n",
            ],

            'each with leading whitespace' => [
                'template' => "  {{#each foo}}\n{{@key}}: {{.}}\n{{/each}}\nDONE",
                'data' => ['foo' => ['a' => 'A', 'b' => 'BOY!']],
                'expected' => "a: A\nb: BOY!\nDONE",
            ],

            'deeply nested partial indentation' => [
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

            'partial double include with indentation' => [
                'template' => "{{>test1}}\n  {{>test1}}\nDONE\n",
                'options' => new Options(
                    partials: ['test1' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n"],
                ),
                'expected' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n  1:A\n   2:B\n    3:C\n   4:D\n  5:E\nDONE\n",
            ],

            'simple variables preserve whitespace' => [
                'template' => "{{foo}}\n  {{bar}}\n",
                'data' => ['foo' => 'ha', 'bar' => 'hey'],
                'expected' => "ha\n  hey\n",
            ],

            'partial preserves internal indentation' => [
                'template' => "{{>test}}\n",
                'data' => ['foo' => 'ha', 'bar' => 'hey'],
                'options' => new Options(
                    partials: ['test' => "{{foo}}\n  {{bar}}\n"],
                ),
                'expected' => "ha\n  hey\n",
            ],

            'section with partial and indentation' => [
                'template' => "ST:\n{{#foo}}\n {{>test1}}\n{{/foo}}\nOK\n",
                'data' => ['foo' => [1, 2]],
                'options' => new Options(
                    partials: ['test1' => "1:A\n 2:B({{@index}})\n"],
                ),
                'expected' => "ST:\n 1:A\n  2:B(0)\n 1:A\n  2:B(1)\nOK\n",
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function escapeProvider(): array
    {
        return [
            'Helper response is escaped correctly' => [
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

            'helper output with equals signs escaped' => [
                'template' => ">{{helper1 \"===\"}}<",
                'helpers' => ['helper1' => fn($arg) => "-$arg-"],
                'expected' => ">-&#x3D;&#x3D;&#x3D;-<",
            ],
            'value with special html chars' => [
                'template' => "{{foo}}",
                'data' => ['foo' => 'A&B " \' ='],
                'expected' => "A&amp;B &quot; &#x27; &#x3D;",
            ],
            'value with html tags' => [
                'template' => "{{foo}}",
                'data' => ['foo' => '<a href="#">\'</a>'],
                'expected' => '&lt;a href&#x3D;&quot;#&quot;&gt;&#x27;&lt;/a&gt;',
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function noEscapeProvider(): array
    {
        return [
            'noEscape: value with special chars unescaped' => [
                'template' => "{{foo}}",
                'data' => ['foo' => 'A&B " \''],
                'options' => new Options(noEscape: true),
                'expected' => "A&B \" '",
            ],
            'LNC#109 - if works correctly with noEscape' => [
                'template' => '{{#if "OK"}}it\'s great!{{/if}}',
                'options' => new Options(noEscape: true),
                'expected' => 'it\'s great!',
            ],
            'LNC#109 - partials work with noEscape' => [
                'template' => '{{foo}} {{> test}}',
                'options' => new Options(
                    noEscape: true,
                    partials: ['test' => '{{foo}}'],
                ),
                'data' => ['foo' => '<'],
                'expected' => '< <',
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function preventIndentProvider(): array
    {
        return [
            'preventIndent: double include' => [
                'template' => "{{>test1}}\n  {{>test1}}\nDONE\n",
                'options' => new Options(
                    preventIndent: true,
                    partials: ['test1' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n"],
                ),
                'expected' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n  1:A\n 2:B\n  3:C\n 4:D\n5:E\nDONE\n",
            ],
            'preventIndent: leading space preserved' => [
                'template' => " {{>test}}\n",
                'data' => ['foo' => 'ha', 'bar' => 'hey'],
                'options' => new Options(
                    preventIndent: true,
                    partials: ['test' => "{{foo}}\n  {{bar}}\n"],
                ),
                'expected' => " ha\n  hey\n",
            ],
            'preventIndent: newline then leading space' => [
                'template' => "\n {{>test}}\n",
                'data' => ['foo' => 'ha', 'bar' => 'hey'],
                'options' => new Options(
                    preventIndent: true,
                    partials: ['test' => "{{foo}}\n  {{bar}}\n"],
                ),
                'expected' => "\n ha\n  hey\n",
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function rawProvider(): array
    {
        return [
            '#11 - floats are output as expected' => [
                'template' => "{{{foo}}}",
                'data' => ['foo' => 1.23],
                'expected' => '1.23',
            ],

            'LNC#66 - support {{&foo}} mustache raw syntax' => [
                'template' => '{{&foo}} , {{foo}}, {{{foo}}}',
                'data' => ['foo' => 'Test & " \' :)'],
                'expected' => 'Test & " \' :) , Test &amp; &quot; &#x27; :), Test & " \' :)',
            ],

            'LNC#169 - support raw blocks' => [
                'template' => '{{{{a}}}}true{{else}}false{{{{/a}}}}',
                'data' => ['a' => true],
                'expected' => "true{{else}}false",
            ],

            'LNC#177 - handle nested raw block' => [
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'data' => ['a' => true],
                'expected' => ' {{{{b}}}} {{{{/b}}}} ',
            ],
            'LNC#177 - handle nested raw block (2)' => [
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'helpers' => ['a' => fn(HelperOptions $options) => $options->fn()],
                'expected' => ' {{{{b}}}} {{{{/b}}}} ',
            ],
            'LNC#177 - handle nested raw block (3)' => [
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'expected' => '',
            ],

            'LNC#344 - escaped expression after raw block' => [
                'template' => '{{{{raw}}}} {{bar}} {{{{/raw}}}} {{bar}}',
                'data' => [
                    'raw' => true,
                    'bar' => 'content',
                ],
                'expected' => ' {{bar}}  content',
            ],

            'raw output of double-quoted {{ literal' => [
                'template' => '{{{"{{"}}}',
                'data' => ['{{' => ':D'],
                'expected' => ':D',
            ],
            'raw output of single-quoted {{ literal' => [
                'template' => "{{{'{{'}}}",
                'data' => ['{{' => ':D'],
                'expected' => ':D',
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function ifElseProvider(): array
    {
        return [
            'LNC#199 - else if falsy' => [
                'template' => '{{#if foo}}1{{else if bar}}2{{else}}3{{/if}}',
                'expected' => '3',
            ],
            'LNC#199 - else if true' => [
                'template' => '{{#if foo}}1{{else if bar}}2{{/if}}',
                'data' => ['bar' => true],
                'expected' => '2',
            ],
            'LNC#199 - unless zero, else if false' => [
                'template' => '{{#unless 0}}1{{else if foo}}2{{else}}3{{/unless}}',
                'data' => ['foo' => false],
                'expected' => '1',
            ],
            'LNC#199 - unless includeZero, else if true' => [
                'template' => '{{#unless 0 includeZero=true}}1{{else if foo}}2{{else}}3{{/unless}}',
                'data' => ['foo' => true],
                'expected' => '2',
            ],
            'LNC#199 - unless includeZero, else if false' => [
                'template' => '{{#unless 0 includeZero=true}}1{{else if foo}}2{{else}}3{{/unless}}',
                'data' => ['foo' => false],
                'expected' => '3',
            ],

            'if with truthy dot data' => [
                'template' => '{{#if .}}YES{{else}}NO{{/if}}',
                'data' => true,
                'expected' => 'YES',
            ],
            'inverted if with else clause' => [
                'template' => '{{^if exists}}bad{{else}}OK{{/if}}',
                'data' => ['exists' => true],
                'expected' => 'OK',
            ],

            'LNC#213 - custom helper inside else if' => [
                'template' => '{{#if foo}}foo{{else if bar}}{{#moo moo}}moo{{/moo}}{{/if}}',
                'data' => ['foo' => true],
                'helpers' => ['moo' => fn($arg1) => $arg1 === null],
                'expected' => 'foo',
            ],

            'LNC#227 - chained {{else}} with helpers' => [
                'template' => '{{#if moo}}A{{else if bar}}B{{else foo}}C{{/if}}',
                'helpers' => ['foo' => fn(HelperOptions $options) => $options->fn()],
                'expected' => 'C',
            ],
            'LNC#227 - chained {{else}} with helpers (2)' => [
                'template' => '{{#if moo}}A{{else if bar}}B{{else with foo}}C{{.}}{{/if}}',
                'data' => ['foo' => 'D'],
                'expected' => 'CD',
            ],
            'LNC#227 - chained {{else}} with helpers (3)' => [
                'template' => '{{#if moo}}A{{else if bar}}B{{else each foo}}C{{.}}{{/if}}',
                'data' => ['foo' => [1, 3, 5]],
                'expected' => 'C1C3C5',
            ],

            'LNC#229 - properties of missing variables' => [
                'template' => '{{#if foo.bar.moo}}TRUE{{else}}FALSE{{/if}}',
                'data' => [],
                'expected' => 'FALSE',
            ],

            'LNC#254 - else conditionals that check for a property' => [
                'template' => '{{#if a}}a{{else if b}}b{{else}}c{{/if}}{{#if a}}a{{else if b}}b{{/if}}',
                'data' => ['b' => 1],
                'expected' => 'bb',
            ],

            'LNC#313 - nested {{else if}}' => [
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

            '#15 - if should work with multi-segment path expression' => [
                'template' => '{{#if foo.bar}}bad{{else}}OK{{/if}}',
                'data' => ['foo' => 'foo'],
                'expected' => 'OK',
            ],

            'strict mode should not throw if the final property of a helper argument is missing' => [
                'template' => '{{#if foo.bar}}bad{{else}}OK{{/if}}',
                'options' => new Options(strict: true),
                'data' => ['foo' => []],
                'expected' => 'OK',
            ],

            'if null is falsy' => [
                'template' => '{{#if .}}T{{else}}F{{/if}}',
                'data' => null,
                'expected' => 'F',
            ],
            'if 0 is falsy' => [
                'template' => '{{#if .}}T{{else}}F{{/if}}',
                'data' => 0,
                'expected' => 'F',
            ],
            'if false is falsy' => [
                'template' => '{{#if .}}T{{else}}F{{/if}}',
                'data' => false,
                'expected' => 'F',
            ],
            'if empty string is falsy' => [
                'template' => '{{#if .}}T{{else}}F{{/if}}',
                'data' => '',
                'expected' => 'F',
            ],
            'if string zero is truthy' => [
                'template' => '{{#if .}}T{{else}}F{{/if}}',
                'data' => '0',
                'expected' => 'T',
            ],
            'if empty array is falsy' => [
                'template' => '{{#if .}}T{{else}}F{{/if}}',
                'data' => [],
                'expected' => 'F',
            ],
            'if array with empty string is truthy' => [
                'template' => '{{#if foo}}T{{else}}F{{/if}}',
                'data' => ['foo' => ['']],
                'expected' => 'T',
            ],
            'if array with zero is truthy' => [
                'template' => '{{#if foo}}T{{else}}F{{/if}}',
                'data' => ['foo' => [0]],
                'expected' => 'T',
            ],
            'if SafeString empty is falsy' => [
                'template' => '{{#if .}}T{{else}}F{{/if}}',
                'data' => new SafeString(''),
                'expected' => 'F',
            ],
            'if SafeString zero is truthy' => [
                'template' => '{{#if .}}T{{else}}F{{/if}}',
                'data' => new SafeString('0'),
                'expected' => 'T',
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function sectionProvider(): array
    {
        return [
            'LNC#90 - nested array containing string' => [
                'template' => '{{#items}}{{#value}}{{.}}{{/value}}{{/items}}',
                'data' => ['items' => [['value' => '123']]],
                'expected' => '123',
            ],
            'non-empty list in a section with {{else}} must iterate, not show the else branch' => [
                'template' => '{{#items}}{{.}},{{else}}empty{{/items}}',
                'data' => ['items' => ['a', 'b', 'c']],
                'expected' => 'a,b,c,',
            ],
            'LNC#159 - Empty ArrayObject in section' => [
                'template' => '{{#.}}true{{else}}false{{/.}}',
                'data' => new \ArrayObject(),
                'expected' => "false",
            ],
            'non-empty ArrayObject in a section with {{else}} must iterate' => [
                'template' => '{{#.}}{{@index}}:{{.}},{{else}}empty{{/.}}',
                'data' => new \ArrayObject(['x', 'y']),
                'expected' => '0:x,1:y,',
            ],
            'LNC#278 - non-boolean conditionals in mustache' => [
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

            'knownHelpersOnly: array section values are correctly handled' => [
                'template' => '{{#items}}{{name}}{{/items}}',
                'options' => new Options(knownHelpersOnly: true),
                'data' => ['items' => ['name' => 'foo']],
                'expected' => 'foo',
            ],
            'knownHelpersOnly: empty array renders else block' => [
                'template' => '{{#items}}YES{{else}}NO{{/items}}',
                'options' => new Options(knownHelpersOnly: true),
                'data' => ['items' => []],
                'expected' => 'NO',
            ],
            'non-empty array renders fn block even when else is present' => [
                'template' => '{{#items}}{{@index}}: {{.}}{{#if @last}}last!{{else}}, {{/if}}{{else}}NO{{/items}}',
                'options' => new Options(knownHelpersOnly: true),
                'data' => ['items' => ['a', 'b']],
                'expected' => '0: a, 1: blast!',
            ],
            'knownHelpersOnly: inverted section skips dispatch to unregistered helpers' => [
                'template' => '{{^items}}EMPTY{{/items}}',
                'options' => new Options(knownHelpersOnly: true),
                'data' => ['items' => false],
                'helpers' => ['items' => fn() => 'HELPER_CALLED'],
                'expected' => 'EMPTY',
            ],
            'knownHelpersOnly: blockHelperMissing is called for inverted sections' => [
                'template' => '{{^items}}EMPTY{{/items}}',
                'options' => new Options(knownHelpersOnly: true),
                'data' => ['items' => false],
                'helpers' => [
                    'blockHelperMissing' => fn($context, HelperOptions $options) => 'BHM:' . $options->inverse(),
                ],
                'expected' => 'BHM:EMPTY',
            ],
            'knownHelpersOnly: blockHelperMissing is called for forward sections' => [
                'template' => '{{#items}}content{{/items}}',
                'options' => new Options(knownHelpersOnly: true),
                'data' => ['items' => true],
                'helpers' => [
                    'blockHelperMissing' => fn($context, HelperOptions $options) => 'BHM:' . $options->fn(),
                ],
                'expected' => 'BHM:content',
            ],
            'complex path with argument treats closure as helper and passes HelperOptions' => [
                'template' => '{{#obj.fn "x"}}BODY{{/obj.fn}}',
                'data' => [
                    'obj' => [
                        'fn' => fn($context, HelperOptions $options) => $context . ':' . $options->fn(),
                    ],
                ],
                'expected' => 'x:BODY',
            ],
            'lambda at complex path in inverted block is called with no arguments' => [
                'template' => '{{^obj.fn}}BODY{{/obj.fn}}',
                'data' => [
                    'obj' => [
                        'fn' => fn(mixed ...$args) => count($args) . ' arguments',
                    ],
                ],
                'expected' => '0 arguments',
            ],
            'forward block with no else: isset($options->inverse) is true and inverse() returns empty string' => [
                'template' => '{{#checkInv}}BODY{{/checkInv}}',
                'helpers' => [
                    'checkInv' => function (HelperOptions $options): string {
                        return (isset($options->inverse) ? 'HAS_INV' : 'NO_INV') . ":{$options->fn()}:{$options->inverse()}";
                    },
                ],
                'expected' => 'HAS_INV:BODY:',
            ],
            'known-helper inverted block: isset($options->fn) is true and fn() returns empty string' => [
                'template' => '{{^checkFn val}}BODY{{/checkFn}}',
                'options' => new Options(knownHelpers: ['checkFn' => true]),
                'data' => ['val' => 'x'],
                'helpers' => [
                    'checkFn' => function (mixed $context, HelperOptions $options): string {
                        return (isset($options->fn) ? 'HAS_FN' : 'NO_FN') . ":{$options->fn()}:{$options->inverse()}";
                    },
                ],
                'expected' => 'HAS_FN::BODY',
            ],
            'unknown simple-identifier inverted block: isset($options->fn) is true and fn() returns empty string' => [
                'template' => '{{^checkFn}}BODY{{/checkFn}}',
                'helpers' => [
                    'checkFn' => function (HelperOptions $options): string {
                        return (isset($options->fn) ? 'HAS_FN' : 'NO_FN') . ":{$options->fn()}:{$options->inverse()}";
                    },
                ],
                'expected' => 'HAS_FN::BODY',
            ],
            'inline partials registered inside a block section do not leak out after the block ends' => [
                'template' => '{{#* inline "p"}}BEFORE{{/inline}}{{#section}}{{#* inline "p"}}INSIDE{{/inline}}{{> p}}{{/section}}{{> p}}',
                'options' => new Options(knownHelpersOnly: true),
                'data' => ['section' => ['x' => 1]],
                'expected' => 'INSIDEBEFORE',
            ],
            'inline partials registered inside an else block do not leak out after the section ends' => [
                'template' => '{{#* inline "p"}}BEFORE{{/inline}}{{#foo}}{{else}}{{#* inline "p"}}INSIDE{{/inline}}{{> p}}{{/foo}}{{> p}}',
                'expected' => 'INSIDEBEFORE',
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function contextProvider(): array
    {
        return [
            'LNC#46 - {{this}} supports subscripting' => [
                'template' => '{{{this.id}}}, {{a.id}}',
                'data' => ['id' => 'bla bla bla', 'a' => ['id' => 'OK!']],
                'expected' => 'bla bla bla, OK!',
            ],
            'dot with string data' => [
                'template' => '-{{.}}-',
                'data' => 'abc',
                'expected' => '-abc-',
            ],
            'this with integer data' => [
                'template' => '-{{this}}-',
                'data' => 123,
                'expected' => '-123-',
            ],

            'LNC#81 - inverse expression with parent scope' => [
                'template' => '{{#with ../person}} {{^name}} Unknown {{/name}} {{/with}}?!',
                'expected' => '?!',
            ],
            'LNC#128 - parent scope reference in root context' => [
                'template' => 'foo: {{foo}} , parent foo: {{../foo}}',
                'data' => ['foo' => 'OK'],
                'expected' => 'foo: OK , parent foo: ',
            ],
            'LNC#206 - parent traversal for condition check' => [
                'template' => '{{#with bar}}{{#../foo}}YES!{{/../foo}}{{/with}}',
                'data' => ['foo' => 999, 'bar' => true],
                'expected' => 'YES!',
            ],
            'knownHelpersOnly: ../path works when array context differs from enclosing context' => [
                'template' => '{{#items}}{{name}}/{{../name}}{{/items}}',
                'options' => new Options(knownHelpersOnly: true),
                'data' => ['name' => 'outer', 'items' => ['name' => 'inner']],
                'expected' => 'inner/outer',
            ],
            '../path inside a true-valued section is empty (matches HBS.js: no depths push for true)' => [
                'template' => '{{#flag}}{{../name}}{{/flag}}',
                'data' => ['flag' => true, 'name' => 'outer'],
                'expected' => '',
            ],

            '#16 - access array items with numeric keys' => [
                'template' => "0: {{this.0.title}}\n1: {{this.1.title}}\n2: {{this.2.title}}",
                'data' => [
                    ['title' => 'Page A'],
                    ['title' => 'Page B'],
                    ['title' => 'Page C'],
                ],
                'expected' => "0: Page A\n1: Page B\n2: Page C",
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function arrayLengthProvider(): array
    {
        return [
            'LNC#216 - {{array.length}} evaluation support - empty array' => [
                'template' => '{{foo.length}}',
                'data' => ['foo' => []],
                'expected' => '0',
            ],
            'LNC#216 - {{array.length}} evaluation support' => [
                'template' => '{{foo.length}}',
                'data' => ['foo' => [1, 2]],
                'expected' => '2',
            ],
            'LNC#370 - length with @root' => [
                'template' => '{{@root.items.length}}',
                'data' => ['items' => [1, 2, 3]],
                'expected' => '3',
            ],
            'length in block params' => [
                'template' => '{{#each items as |item|}}{{item.length}}{{/each}}',
                'data' => ['items' => [[1, 2, 3]]],
                'expected' => '3',
            ],
            'length in block params with nested path' => [
                'template' => '{{#each items as |item|}}{{item.nested.length}}{{/each}}',
                'data' => ['items' => [['nested' => [1, 2]]]],
                'expected' => '2',
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function dataClosuresProvider(): array
    {
        return [
            'data can contain closures' => [
                'template' => '{{foo}}',
                'data' => ['foo' => fn() => 'OK'],
                'expected' => 'OK',
            ],
            'callable strings or arrays should NOT be treated as functions' => [
                'template' => '{{foo}}',
                'data' => ['foo' => 'hrtime'],
                'expected' => 'hrtime',
            ],
            'callable strings or arrays should NOT be treated as functions (2)' => [
                'template' => '{{#foo}}OK{{else}}bad{{/foo}}',
                'data' => ['foo' => 'is_string'],
                'expected' => 'OK',
            ],

            'closures in data can be used like helpers' => [
                'template' => '{{test "Hello"}}',
                'data' => ['test' => fn(string $arg) => "$arg runtime data"],
                'expected' => 'Hello runtime data',
            ],
            'helpers always take precedence over data closures' => [
                'template' => '{{test "Hello"}}',
                'data' => ['test' => fn(string $arg) => "$arg runtime data"],
                'helpers' => ['test' => fn(string $arg) => "$arg runtime helper"],
                'expected' => 'Hello runtime helper',
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function missingDataProvider(): array
    {
        return [
            'missing top-level key' => [
                'template' => '{{foo}}',
                'expected' => '',
            ],
            'missing nested key no data' => [
                'template' => '{{foo.bar}}',
                'expected' => '',
            ],
            'missing nested key empty array' => [
                'template' => '{{foo.bar}}',
                'data' => ['foo' => []],
                'expected' => '',
            ],
            'strict mode should not throw for null block param property value' => [
                'template' => '{{#each items as |item|}}{{item.val}}{{/each}}',
                'options' => new Options(strict: true),
                'data' => ['items' => [['val' => null]]],
                'expected' => '',
            ],
            'strict mode should not throw for explicit null value at literal mustache path' => [
                'template' => '{{"foo"}}',
                'options' => new Options(strict: true),
                'data' => ['foo' => null],
                'expected' => '',
            ],
            'strict mode should not throw for explicit null value at literal block section path' => [
                'template' => '{{#"foo"}}YES{{/"foo"}}',
                'options' => new Options(strict: true),
                'data' => ['foo' => null],
                'expected' => '',
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function syntaxProvider(): array
    {
        return [
            'LNC#154 - comments can contain exclamation mark' => [
                'template' => 'O{{! this is comment ! ... }}K!',
                'expected' => "OK!",
            ],
            'LNC#175 - comments can contain mustache syntax' => [
                'template' => 'a{{!-- {{each}} haha {{/each}} --}}b',
                'expected' => 'ab',
            ],
            'LNC#175 - partial comments can contain mustache syntax' => [
                'template' => 'c{{>test}}d',
                'options' => new Options(
                    partials: ['test' => 'a{{!-- {{each}} haha {{/each}} --}}b'],
                ),
                'expected' => 'cabd',
            ],

            'LNC#290 - unpaired }} should be displayed' => [
                'template' => '{{foo}} }} OK',
                'data' => ['foo' => 'YES'],
                'expected' => 'YES }} OK',
            ],
            'LNC#290 - string containing }' => [
                'template' => '{{foo}}{{#with "}"}}{{.}}{{/with}}OK',
                'data' => ['foo' => 'YES'],
                'expected' => 'YES}OK',
            ],
            'LNC#290 - unpaired { should be displayed' => [
                'template' => '{ {{foo}}',
                'data' => ['foo' => 'YES'],
                'expected' => '{ YES',
            ],
            'LNC#290 - string containing {{' => [
                'template' => '{{#with "{{"}}{{.}}{{/with}}{{foo}}{{#with "{{"}}{{.}}{{/with}}',
                'data' => ['foo' => 'YES'],
                'expected' => '{{YES{{',
            ],
        ];
    }

    /** @return array<string, RegIssue> */
    public static function subexpressionPathProvider(): array
    {
        return [
            'sub-expression as path head: standalone mustache' => [
                'template' => '{{(my-helper foo).bar}}',
                'data' => ['foo' => 'val'],
                'helpers' => ['my-helper' => fn($arg) => ['bar' => "got:$arg"]],
                'expected' => 'got:val',
            ],
            'sub-expression as path head: callable' => [
                'template' => '{{((my-helper foo).bar baz)}}',
                'data' => ['foo' => 'x', 'baz' => 'y'],
                'helpers' => ['my-helper' => fn($arg) => ['bar' => fn($x) => "called:$x"]],
                'expected' => 'called:y',
            ],
            'sub-expression as path head: argument' => [
                'template' => '{{(foo (my-helper bar).baz)}}',
                'data' => ['bar' => 'hello'],
                'helpers' => [
                    'my-helper' => fn($arg) => ['baz' => strtoupper($arg)],
                    'foo' => fn($val) => "foo:$val",
                ],
                'expected' => 'foo:HELLO',
            ],
            'sub-expression as path head: named argument' => [
                'template' => '{{(foo bar=(my-helper baz).qux)}}',
                'data' => ['baz' => 'world'],
                'helpers' => [
                    'my-helper' => fn($arg) => ['qux' => strtoupper($arg)],
                    'foo' => fn(HelperOptions $options) => $options->hash['bar'],
                ],
                'expected' => 'WORLD',
            ],
        ];
    }

    public function testLoadPartialPersistsAcrossFnCalls(): void
    {
        // registerPartial() writes to the persistent $cx->partials array, which fn() does not
        // reset, so each template is compiled and registered only once even when a block helper
        // calls fn() per iteration.
        $compileCounts = ['a' => 0, 'b' => 0];

        $helpers = [
            'repeat' => function (array $items, HelperOptions $options): string {
                $ret = '';
                foreach ($items as $item) {
                    $ret .= $options->fn($item);
                }
                return $ret;
            },
            'loadPartial' => function (string $name, HelperOptions $options) use (&$compileCounts): string {
                if (!$options->hasPartial($name)) {
                    $templates = ['a' => '[A:{{val}}]', 'b' => '[B:{{val}}]'];
                    $options->registerPartial($name, Handlebars::compile($templates[$name]));
                    $compileCounts[$name]++;
                }
                return $name;
            },
        ];

        $template = Handlebars::compile('{{#repeat items}}{{> (loadPartial type)}}{{/repeat}}');
        $items = [
            ['type' => 'a', 'val' => 1],
            ['type' => 'b', 'val' => 2],
            ['type' => 'a', 'val' => 3],
        ];
        $result = $template(['items' => $items], ['helpers' => $helpers]);

        $this->assertSame('[A:1][B:2][A:3]', $result);
        $this->assertSame(1, $compileCounts['a']);
        $this->assertSame(1, $compileCounts['b']);
    }
}
