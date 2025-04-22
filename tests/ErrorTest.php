<?php

namespace DevTheorem\Handlebars\Test;

use DevTheorem\Handlebars\Handlebars;
use DevTheorem\Handlebars\Options;
use PHPUnit\Framework\TestCase;

class ErrorTest extends TestCase
{
    public function testException()
    {
        try {
            $php = Handlebars::precompile('{{{foo}}');
        } catch (\Exception $E) {
            $this->assertEquals('Bad token {{{foo}} ! Do you mean {{foo}} or {{{foo}}}?', $E->getMessage());
        }
    }

    public function testLog()
    {
        $template = Handlebars::compile('{{log foo}}');

        date_default_timezone_set('GMT');
        $tmpDir = sys_get_temp_dir();
        $tmpFile = tempnam($tmpDir, 'terr_');
        ini_set('error_log', $tmpFile);

        $template(['foo' => 'OK!']);

        $contents = array_map(function ($l) {
            $l = rtrim($l);
            preg_match('/GMT] (.+)/', $l, $m);
            return $m[1] ?? $l;
        }, file($tmpFile));

        $this->assertEquals(['array (', "  0 => 'OK!',", ')'], $contents);
        ini_restore('error_log');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("renderErrorProvider")]
    public function testRenderingException($test)
    {
        $php = Handlebars::precompile($test['template'], $test['options'] ?? new Options());
        $renderer = Handlebars::template($php);
        try {
            $renderer($test['data'] ?? null);
            $this->fail("Expected to throw exception: {$test['expected']}. CODE: $php");
        } catch (\Exception $E) {
            $this->assertEquals($test['expected'], $E->getMessage());
        }
    }

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
                'expected' => 'Runtime: [foo] does not exist',
            ],
            [
                'template' => '{{#foo}}OK{{/foo}}',
                'options' => new Options(strict: true),
                'expected' => 'Runtime: [foo] does not exist',
            ],
            [
                'template' => '{{{foo}}}',
                'options' => new Options(strict: true),
                'expected' => 'Runtime: [foo] does not exist',
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

    #[\PHPUnit\Framework\Attributes\DataProvider("errorProvider")]
    public function testErrors($test)
    {
        if (!isset($test['expected'])) {
            // should compile without error
            $code = Handlebars::precompile($test['template'], $test['options']);
            $this->assertNotEmpty($code);
            return;
        }

        try {
            Handlebars::precompile($test['template'], $test['options']);
            $this->fail("Expected to throw exception: {$test['expected']}");
        } catch (\Exception $e) {
            $this->assertEquals($test['expected'], explode("\n", $e->getMessage()));
        }
    }

    public static function errorProvider(): array
    {
        $errorCases = [
            [
                'template' => '{{testerr1}}}',
                'expected' => 'Bad token {{testerr1}}} ! Do you mean {{testerr1}} or {{{testerr1}}}?',
            ],
            [
                'template' => '{{{testerr2}}',
                'expected' => 'Bad token {{{testerr2}} ! Do you mean {{testerr2}} or {{{testerr2}}}?',
            ],
            [
                'template' => '{{{#testerr3}}}',
                'expected' => 'Bad token {{{#testerr3}}} ! Do you mean {{#testerr3}} ?',
            ],
            [
                'template' => '{{{!testerr4}}}',
                'expected' => 'Bad token {{{!testerr4}}} ! Do you mean {{!testerr4}} ?',
            ],
            [
                'template' => '{{{^testerr5}}}',
                'expected' => 'Bad token {{{^testerr5}}} ! Do you mean {{^testerr5}} ?',
            ],
            [
                'template' => '{{{/testerr6}}}',
                'expected' => 'Bad token {{{/testerr6}}} ! Do you mean {{/testerr6}} ?',
            ],
            [
                'template' => '{{win[ner.test1}}',
                'expected' => [
                    "Error in 'win[ner.test1': expect ']' but the token ended!!",
                    'Wrong variable naming in {{win[ner.test1}}',
                ],
            ],
            [
                'template' => '{{win]ner.test2}}',
                'expected' => 'Wrong variable naming as \'win]ner.test2\' in {{win]ner.test2}} !',
            ],
            [
                'template' => '{{wi[n]ner.test3}}',
                'expected' => [
                    'Wrong variable naming as \'wi[n]ner.test3\' in {{wi[n]ner.test3}} !',
                    "Unexpected character in 'wi[n]ner.test3' (should it be 'wi.[n].ner.test3' ?)",
                ],
            ],
            [
                'template' => '{{winner].[test4]}}',
                'expected' => [
                    'Wrong variable naming as \'winner].[test4]\' in {{winner].[test4]}} !',
                    "Unexpected character in 'winner].[test4]' (should it be 'winner.[test4]' ?)",
                ],
            ],
            [
                'template' => '{{winner[.test5]}}',
                'expected' => [
                    'Wrong variable naming as \'winner[.test5]\' in {{winner[.test5]}} !',
                    "Unexpected character in 'winner[.test5]' (should it be 'winner.[.test5]' ?)",
                ],
            ],
            [
                'template' => '{{winner.[.test6]}}',
            ],
            [
                'template' => '{{winner.[#te.st7]}}',
            ],
            [
                'template' => '{{test8}}',
            ],
            [
                'template' => '{{test9]}}',
                'expected' => [
                    'Wrong variable naming as \'test9]\' in {{test9]}} !',
                    "Unexpected character in 'test9]' (should it be 'test9' ?)",
                ],
            ],
            [
                'template' => '{{testA[}}',
                'expected' => [
                    "Error in 'testA[': expect ']' but the token ended!!",
                    'Wrong variable naming in {{testA[}}',
                ],
            ],
            [
                'template' => '{{[testB}}',
                'expected' => [
                    "Error in '[testB': expect ']' but the token ended!!",
                    'Wrong variable naming in {{[testB}}',
                ],
            ],
            [
                'template' => '{{]testC}}',
                'expected' => [
                    'Wrong variable naming as \']testC\' in {{]testC}} !',
                    "Unexpected character in ']testC' (should it be 'testC' ?)",
                ],
            ],
            [
                'template' => '{{[testD]}}',
            ],
            [
                'template' => '{{te]stE}}',
                'expected' => 'Wrong variable naming as \'te]stE\' in {{te]stE}} !',
            ],
            [
                'template' => '{{tee[stF}}',
                'expected' => [
                    "Error in 'tee[stF': expect ']' but the token ended!!",
                    'Wrong variable naming in {{tee[stF}}',
                ],
            ],
            [
                'template' => '{{te.e[stG}}',
                'expected' => [
                    "Error in 'te.e[stG': expect ']' but the token ended!!",
                    'Wrong variable naming in {{te.e[stG}}',
                ],
            ],
            [
                'template' => '{{te.e]stH}}',
                'expected' => 'Wrong variable naming as \'te.e]stH\' in {{te.e]stH}} !',
            ],
            [
                'template' => '{{te.e[st.endI}}',
                'expected' => [
                    "Error in 'te.e[st.endI': expect ']' but the token ended!!",
                    'Wrong variable naming in {{te.e[st.endI}}',
                ],
            ],
            [
                'template' => '{{te.e]st.endJ}}',
                'expected' => 'Wrong variable naming as \'te.e]st.endJ\' in {{te.e]st.endJ}} !',
            ],
            [
                'template' => '{{te.[est].endK}}',
            ],
            [
                'template' => '{{te.t[est].endL}}',
                'expected' => [
                    'Wrong variable naming as \'te.t[est].endL\' in {{te.t[est].endL}} !',
                    "Unexpected character in 'te.t[est].endL' (should it be 'te.t.[est].endL' ?)",
                ],
            ],
            [
                'template' => '{{te.t[est]o.endM}}',
                'expected' => [
                    'Wrong variable naming as \'te.t[est]o.endM\' in {{te.t[est]o.endM}} !',
                    "Unexpected character in 'te.t[est]o.endM' (should it be 'te.t.[est].o.endM' ?)",
                ],
            ],
            [
                'template' => '{{te.[est]o.endN}}',
                'expected' => [
                    'Wrong variable naming as \'te.[est]o.endN\' in {{te.[est]o.endN}} !',
                    "Unexpected character in 'te.[est]o.endN' (should it be 'te.[est].o.endN' ?)",
                ],
            ],
            [
                'template' => '{{te.[e.st].endO}}',
            ],
            [
                'template' => '{{te.[e.s[t].endP}}',
            ],
            [
                'template' => '{{te.[e[s.t].endQ}}',
            ],
            [
                'template' => '{{helper}}',
                'options' => new Options(
                    helpers: [
                        'foo' => ['bad input'],
                    ],
                ),
                'expected' => 'Custom helper foo must be a function, not an array.',
            ],
            [
                'template' => '{{typeof hello}}',
            ],
            [
                'template' => '{{typeof hello}}',
                'options' => new Options(knownHelpersOnly: true),
                'expected' => 'Missing helper: "typeof"',
            ],
            [
                'template' => '<ul>{{#each item}}<li>{{name}}</li>',
                'expected' => 'Unclosed token {{#each item}} !!',
            ],
            [
                'template' => 'issue63: {{test_join}} Test! {{this}} {{/test_join}}',
                'expected' => 'Unexpect token: {{/test_join}} !',
            ],
            [
                'template' => '{{#if a}}TEST{{/with}}',
                'expected' => 'Unexpect token: {{/with}} !',
            ],
            [
                'template' => '{{#foo}}error{{/bar}}',
                'expected' => 'Unexpect token {{/bar}} ! Previous token {{#[foo]}} is not closed',
            ],
            [
                'template' => '{{a=b}}',
                'expected' => 'Do not support name=value in {{a=b}}, you should use it after a custom helper.',
            ],
            [
                'template' => '{{#with a}OK!{{/with}}',
                'expected' => [
                    'Wrong variable naming as \'a}OK!{{/with\' in {{#with a}OK!{{/with}} ! You should wrap ! " # % & \' * + , ; < = > { | } ~ into [ ]',
                    'Unclosed token {{#with a}OK!{{/with}} !!',
                ],
            ],
            [
                'template' => '{{#each a}OK!{{/each}}',
                'expected' => [
                    'Wrong variable naming as \'a}OK!{{/each\' in {{#each a}OK!{{/each}} ! You should wrap ! " # % & \' * + , ; < = > { | } ~ into [ ]',
                    'Unclosed token {{#each a}OK!{{/each}} !!',
                ],
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
                'expected' => 'No argument after {{#each}} !',
            ],
            [
                'template' => '{{lookup}}',
                'expected' => 'No argument after {{lookup}} !',
            ],
            [
                'template' => '{{lookup foo}}',
                'expected' => '{{lookup}} requires 2 arguments !',
            ],
            [
                'template' => '{{#test foo}}{{/test}}',
                'expected' => 'Custom helper not found: test in {{#test foo}} !',
            ],
            [
                'template' => '{{>not_found}}',
                'expected' => "The partial not_found could not be found",
            ],
            [
                'template' => '{{abc}}',
                'options' => new Options(
                    helpers: ['abc'],
                ),
                'expected' => "Custom helper 'abc' must be a function.",
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
                'template' => '{{1 + 2}}',
                'expected' => "Wrong variable naming as '+' in {{1 + 2}} ! You should wrap ! \" # % & ' * + , ; < = > { | } ~ into [ ]",
            ],
            [
                'template' => '{{> (foo) bar}}',
                'expected' => [
                    'Missing helper: "foo"',
                ],
            ],
            [
                'template' => '{{{{#foo}}}',
                'expected' => [
                    'Bad token {{{{#foo}}} ! Do you mean {{{{#foo}}}} ?',
                    'Wrong raw block begin with {{{{#foo}}} ! Remove "#" to fix this issue.',
                    'Unclosed token {{{{foo}}}} !!',
                ],
            ],
            [
                'template' => '{{{{foo}}}} {{ {{{{/bar}}}}',
                'expected' => 'Unclosed token {{{{foo}}}} !!',
            ],
            [
                'template' => '{{foo (foo (foo 1 2) 3))}}',
                'options' => new Options(
                    helpers: [
                        'foo' => function () {},
                    ],
                ),
                'expected' => 'Unexpected \')\' in expression \'foo (foo (foo 1 2) 3))\' !!',
            ],
            [
                'template' => '{{{{foo}}}} {{ {{{{#foo}}}}',
                'expected' => 'Unclosed token {{{{foo}}}} !!',
            ],
            [
                'template' => '{{else}}',
                'expected' => '{{else}} only valid in if, unless, each, and #section context',
            ],
            [
                'template' => '{{log}}',
                'expected' => 'No argument after {{log}} !',
            ],
            [
                'template' => '{{#*help me}}{{/help}}',
                'expected' => 'Do not support {{#*help me}}, now we only support {{#*inline "partialName"}}template...{{/inline}}',
            ],
            [
                'template' => '{{#*inline}}{{/inline}}',
                'expected' => 'Error in {{#*inline}}: inline require 1 argument for partial name!',
            ],
            [
                'template' => '{{#>foo}}bar',
                'expected' => 'Unclosed token {{#>foo}} !!',
            ],
            [
                'template' => '{{ #2 }}',
                'expected' => 'Unclosed token {{#2}} !!',
            ],
        ];

        return array_map(function ($i) {
            if (!isset($i['options'])) {
                $i['options'] = new Options();
            }
            if (isset($i['expected']) && is_string($i['expected'])) {
                $i['expected'] = [$i['expected']];
            }
            return [$i];
        }, $errorCases);
    }
}
