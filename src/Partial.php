<?php

namespace DevTheorem\Handlebars;

class Partial
{
    /**
     * Include all partials when using dynamic partials
     */
    public static function handleDynamic(Context $context): void
    {
        if ($context->usedDynPartial == 0) {
            return;
        }

        foreach ($context->partials as $name => $code) {
            static::read($context, $name);
        }
    }

    /**
     * Read partial file content as string and store in context
     */
    public static function read(Context $context, string $name): void
    {
        $isPB = ($name === '@partial-block');

        if (isset($context->usedPartial[$name])) {
            return;
        }

        $cnt = static::resolve($context, $name);

        if ($cnt !== null) {
            $context->usedPartial[$name] = SafeString::escapeTemplate($cnt);
            static::compileDynamic($context, $name);
            return;
        }

        if (!$isPB) {
            $context->error[] = "The partial $name could not be found";
        }
    }

    /**
     * resolve partial, return the partial content
     *
     * @return string|null $content partial content
     */
    public static function resolve(Context $context, string &$name): ?string
    {
        if ($name === '@partial-block') {
            $name = "@partial-block{$context->usedPBlock}";
        }
        if (isset($context->partials[$name])) {
            return $context->partials[$name];
        }
        if ($context->partialResolver) {
            return ($context->partialResolver)($context, $name);
        }
        return null;
    }

    /**
     * compile partial as closure, stored in context
     *
     * @return string|null $code compiled PHP code when success
     */
    public static function compileDynamic(Context $context, string $name): ?string
    {
        $func = static::compile($context, $context->usedPartial[$name], $name);

        if (!isset($context->partialCode[$name]) && $func) {
            $context->partialCode[$name] = "'$name' => $func";
        }

        return $func;
    }

    /**
     * compile a template into a closure function
     */
    public static function compile(Context $context, string $template, string $name): ?string
    {
        if (end($context->partialStack) === $name && str_starts_with($name, '@partial-block')) {
            return null;
        }

        $tmpContext = clone $context;
        $tmpContext->inlinePartial = [];
        $tmpContext->partialBlock = [];
        $tmpContext->partialStack[] = $name;

        $code = Compiler::compileTemplate($tmpContext, $template);
        $context->merge($tmpContext);

        if (!$context->options->preventIndent) {
            $code = preg_replace('/^/m', "'{$context->separator}\$sp{$context->separator}'", $code);
            // remove extra spaces before partial
            $code = preg_replace('/^\'\\.\\$sp\\.\'(\'\\.LR::p\\()/m', '$1', $code, 1);
            // add spaces after partial
            $code = preg_replace('/^(\'\\.LR::p\\(.+\\)\\.)(\'.+)/m', '$1\$sp.$2', $code, 1);
        }
        return "function (\$cx, \$in, \$sp) {{$context->fStart}'$code'{$context->fEnd}}";
    }
}
