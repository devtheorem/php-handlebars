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

        $code = Compiler::compileTemplate($context, SafeString::escapeTemplate($template));
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
