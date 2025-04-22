<?php

namespace DevTheorem\Handlebars;

class SafeString implements \Stringable
{
    public const EXTENDED_COMMENT_SEARCH = '/{{!--.*?--}}/s';
    public const IS_SUBEXP_SEARCH = '/^\(.+\)$/s';
    public const IS_BLOCKPARAM_SEARCH = '/^ +\|(.+)\|$/s';

    private string $string;

    /**
     * @param string $str input string
     * @param bool|string $escape false to not escape, true to escape, 'encq' to escape as handlebars.js
     */
    public function __construct(string $str, bool|string $escape = false)
    {
        $this->string = $escape ? ($escape === 'encq' ? Encoder::encq($str) : Encoder::enc($str)) : $str;
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

    /**
     * Escape template
     *
     * @param string $template handlebars template string
     */
    public static function escapeTemplate(string $template): string
    {
        return addcslashes(addcslashes($template, '\\'), "'");
    }
}
