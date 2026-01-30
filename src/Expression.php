<?php

namespace DevTheorem\Handlebars;

/**
 * @internal
 */
final class Expression
{
    /**
     * Get string presentation for a string list
     *
     * @param array<string> $list an array of strings.
     */
    public static function listString(array $list): string
    {
        return '[' . implode(',', array_map(static fn($v) => "'$v'", $list)) . ']';
    }

    /**
     * Get string presentation for an array
     *
     * @param array<string> $list an array of variable names.
     */
    public static function arrayString(array $list): string
    {
        return implode('', array_map(static fn($v) => "[" . self::quoteString($v) . "]", $list));
    }

    public static function quoteString(string $string): string
    {
        return "'" . addcslashes($string, "'") . "'";
    }
}
