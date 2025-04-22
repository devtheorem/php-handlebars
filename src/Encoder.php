<?php

namespace DevTheorem\Handlebars;

/**
 * @internal
 */
final class Encoder
{
    /**
     * Get the HTML encoded value of the specified variable.
     *
     * @param array<array|string|int>|string|int|bool|null $var value to be htmlencoded
     */
    public static function enc(array|string|int|bool|null $var): string
    {
        return htmlspecialchars(Runtime::raw($var), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Runtime method for {{var}}, and deal with single quote the same as Handlebars.js.
     *
     * @param array<array|string|int>|string|int|bool|null $var value to be htmlencoded
     *
     * @return string The htmlencoded value of the specified variable
     */
    public static function encq(array|string|int|bool|null $var): string
    {
        return str_replace(['=', '`', '&#039;'], ['&#x3D;', '&#x60;', '&#x27;'], self::enc($var));
    }
}
