<?php

namespace DevTheorem\Handlebars;

/**
 * @internal
 */
final class Exporter
{
    /**
     * Get PHP code string from a closure of function as string
     */
    public static function closure(\Closure $closure): string
    {
        $ref = new \ReflectionFunction($closure);
        return static::getClosureSource($ref);
    }

    /**
     * Export required custom helper functions
     */
    public static function helpers(Context $context): string
    {
        $ret = '';
        foreach ($context->helpers as $name => $func) {
            if (!isset($context->usedHelpers[$name])) {
                continue;
            }
            if ($func instanceof \Closure) {
                $ret .= ("            '$name' => " . static::closure($func) . ",\n");
                continue;
            }
            if (is_string($func)) {
                $ret .= "            '$name' => '$func',\n";
            } else {
                throw new \Exception('Unexpected helper type: ' . gettype($func));
            }
        }

        return "[$ret]";
    }

    public static function getClosureSource(\ReflectionFunction $fn): string
    {
        $fileContents = file_get_contents($fn->getFileName());
        $startLine = $fn->getStartLine();
        $endLine = $fn->getEndLine();
        $enteredFnToken = null;
        $depth = 0;
        $code = '';

        foreach (\PhpToken::tokenize($fileContents) as $token) {
            if ($token->line < $startLine) {
                continue;
            } elseif ($token->line > $endLine) {
                break;
            } elseif (!$enteredFnToken) {
                if ($token->id !== T_FUNCTION && $token->id !== T_FN) {
                    continue;
                }
                $enteredFnToken = $token;
            }

            $name = $token->getTokenName();

            if (in_array($name, ['(', '[', '{', 'T_CURLY_OPEN'])) {
                $depth++;
            } elseif (in_array($name, [')', ']', '}'])) {
                if ($depth === 0 && $enteredFnToken->id === T_FN) {
                    return rtrim($code);
                }
                $depth--;
            }

            if ($depth === 0) {
                if ($enteredFnToken->id === T_FUNCTION && $name === '}') {
                    $code .= $token->text;
                    return $code;
                } elseif ($enteredFnToken->id === T_FN && in_array($name, [';', ','])) {
                    return $code;
                }
            }

            $code .= $token->text;
        }

        return $code;
    }
}
