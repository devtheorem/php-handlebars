<?php

namespace DevTheorem\Handlebars;

use DevTheorem\HandlebarsParser\ParserFactory;

/**
 * @phpstan-type RenderOptions array{data?: array<mixed>, helpers?: array<string, \Closure>, partials?: array<string, \Closure>}
 * @phpstan-type Template \Closure(mixed=, RenderOptions=): string
 */
final class Handlebars
{
    /**
     * Compiles a template so it can be executed immediately.
     * @return Template
     */
    public static function compile(string $template, Options $options = new Options()): \Closure
    {
        return self::template(self::precompile($template, $options));
    }

    /**
     * Precompiles a handlebars template into PHP code which can be executed later.
     */
    public static function precompile(string $template, Options $options = new Options()): string
    {
        $context = new Context($options);
        $parser = (new ParserFactory())->create($options->ignoreStandalone);
        $program = $parser->parse($template);
        $compiler = new Compiler($parser);
        $code = $compiler->compile($program, $context);
        $compiler->handleDynamicPartials();

        // return full PHP render code as string
        return $compiler->composePHPRender($code);
    }

    /**
     * Sets up a template that was precompiled with precompile().
     * @return Template
     */
    public static function template(string $templateSpec): \Closure
    {
        return eval($templateSpec);
    }

    /**
     * HTML escapes the passed string, making it safe for rendering as text within HTML content.
     * The output of all expressions except for triple-braced expressions are passed through this method.
     * Helpers should also use this method when returning HTML content via a SafeString instance,
     * to prevent possible code injection.
     */
    public static function escapeExpression(string $string): string
    {
        return strtr($string, [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
            '"' => '&quot;',
            "'" => '&#x27;',
            '`' => '&#x60;',
            '=' => '&#x3D;',
        ]);
    }
}
