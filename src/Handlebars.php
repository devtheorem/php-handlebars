<?php

namespace DevTheorem\Handlebars;

final class Handlebars
{
    protected static Context $lastContext;
    /** @var array<mixed> */
    public static array $lastParsed;

    /**
     * Compiles a template so it can be executed immediately.
     * @return \Closure(mixed=, array<mixed>=):string
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
        static::handleError($context);

        $code = Compiler::compileTemplate($context, $template);
        static::$lastParsed = Compiler::$lastParsed;
        static::handleError($context);

        // return full PHP render code as string
        return Compiler::composePHPRender($context, $code);
    }

    /**
     * Sets up a template that was precompiled with precompile().
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
        $search = ['&', '<', '>', '"', "'", '`', '='];
        $replace = ['&amp;', '&lt;', '&gt;', '&quot;', '&#x27;', '&#x60;', '&#x3D;'];
        return str_replace($search, $replace, $string);
    }

    /**
     * @throws \Exception
     */
    protected static function handleError(Context $context): void
    {
        static::$lastContext = $context;

        if ($context->error) {
            throw new \Exception(implode("\n", $context->error));
        }
    }

    /**
     * Get last compiler context.
     */
    public static function getContext(): Context
    {
        return static::$lastContext;
    }
}
