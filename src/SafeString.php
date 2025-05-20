<?php

namespace DevTheorem\Handlebars;

/**
 * Can be returned from a custom helper to prevent an HTML string from being escaped
 * when the template is rendered. When constructing, any external content should be
 * properly escaped using Handlebars::escapeExpression() to avoid potential security concerns.
 */
class SafeString implements \Stringable
{
    private string $string;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    public function __toString(): string
    {
        return $this->string;
    }
}
