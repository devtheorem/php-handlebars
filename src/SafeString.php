<?php

namespace DevTheorem\Handlebars;

class SafeString implements \Stringable
{
    public const EXTENDED_COMMENT_SEARCH = '/{{!--.*?--}}/s';
    public const IS_SUBEXP_SEARCH = '/^\(.+\)$/s';
    public const IS_BLOCKPARAM_SEARCH = '/^ +\|(.+)\|$/s';

    private string $string;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    public function __toString()
    {
        return $this->string;
    }

    /**
     * Strip extended comments {{!-- .... --}}
     */
    public static function stripExtendedComments(string $template): string
    {
        return preg_replace(static::EXTENDED_COMMENT_SEARCH, '{{! }}', $template);
    }

    public static function escapeTemplate(string $template): string
    {
        return addcslashes($template, '\\');
    }
}
