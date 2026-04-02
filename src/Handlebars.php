<?php

namespace DevTheorem\Handlebars;

use Closure;
use DevTheorem\HandlebarsParser\Parser;
use DevTheorem\HandlebarsParser\ParserFactory;

/**
 * @phpstan-type RenderOptions array{data?: array<mixed>, helpers?: array<string, Closure>, partials?: array<string, Closure>}
 * @phpstan-type Template Closure(mixed=, RenderOptions=): string
 */
final class Handlebars
{
    private static ?Parser $parser = null;
    private static ?Compiler $compiler = null;

    /**
     * Compiles a template so it can be executed immediately.
     * @return Template
     */
    public static function compile(string $template, Options $options = new Options()): Closure
    {
        return self::template(self::precompile($template, $options));
    }

    /**
     * Precompiles a handlebars template into PHP code which can be executed later.
     */
    public static function precompile(string $template, Options $options = new Options()): string
    {
        self::$parser ??= (new ParserFactory())->create();
        self::$compiler ??= new Compiler(self::$parser);

        $context = new Context($options);
        $program = self::$parser->parse($template, $options->ignoreStandalone);
        $code = self::$compiler->compile($program, $context);
        self::$compiler->handleDynamicPartials();

        return self::$compiler->composePHPRender($code);
    }

    /**
     * Sets up a template that was precompiled with precompile().
     * @return Template
     */
    public static function template(string $templateSpec): Closure
    {
        return eval($templateSpec);
    }

    /**
     * Creates a child @data frame inheriting fields from the given frame.
     * Use this in block helpers before passing a data array to fn() or inverse(),
     * equivalent to Handlebars.createFrame() in Handlebars.js.
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public static function createFrame(array $data): array
    {
        $frame = $data;
        $frame['_parent'] = $data;
        return $frame;
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
