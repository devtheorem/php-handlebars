<?php

namespace DevTheorem\Handlebars;

/**
 * Can be returned from a custom helper to prevent an HTML string from being escaped
 * when the template is rendered. Because SafeString bypasses the automatic HTML escaping
 * that {{ }} applies, any user-supplied content embedded in it must first be escaped with
 * Handlebars::escapeExpression() to prevent XSS vulnerabilities.
 */
final readonly class SafeString implements \Stringable
{
    public function __construct(
        private string $string,
    ) {}

    public function __toString(): string
    {
        return $this->string;
    }
}
