<?php

namespace DevTheorem\Handlebars;

/**
 * @internal
 */
final class Compiler extends Validator
{
    /** @var array<mixed> */
    public static array $lastParsed;

    /**
     * Compile template into PHP code
     */
    public static function compileTemplate(Context $context, string $template): string
    {
        array_unshift($context->parsed, []);
        Validator::verify($context, $template);
        static::$lastParsed = $context->parsed;

        if (count($context->error)) {
            return '';
        }

        $context->compile = true;

        // Handle dynamic partials
        Partial::handleDynamic($context);

        // Do PHP code generation.
        $code = '';
        foreach ($context->parsed[0] as $info) {
            if (is_array($info)) {
                $code .= "'" . static::compileToken($context, $info) . "'";
            } else {
                $code .= addcslashes($info, "'");
            }
        }

        array_shift($context->parsed);

        return $code;
    }

    /**
     * Compile Handlebars template to PHP function.
     *
     * @param string $code generated PHP code
     */
    public static function composePHPRender(Context $context, string $code): string
    {
        $runtime = Runtime::class;
        $helperOptions = HelperOptions::class;
        $safeStringClass = SafeString::class;
        $runtimeContext = RuntimeContext::class;
        $helpers = Exporter::helpers($context);
        $partials = implode(",\n", $context->partialCode);

        // Return generated PHP code string.
        return <<<VAREND
            use {$runtime} as LR;
            use {$safeStringClass};
            use {$helperOptions};
            use {$runtimeContext};
            return function (mixed \$in = null, array \$options = []) {
                \$helpers = $helpers;
                \$partials = [$partials];
                \$cx = new RuntimeContext(
                    helpers: isset(\$options['helpers']) ? array_merge(\$helpers, \$options['helpers']) : \$helpers,
                    partials: isset(\$options['partials']) ? array_merge(\$partials, \$options['partials']) : \$partials,
                    scopes: [],
                    spVars: isset(\$options['data']) ? array_merge(['root' => \$in], \$options['data']) : ['root' => \$in],
                    blParam: [],
                    partialId: 0,
                );
                {$context->fStart}'$code'{$context->fEnd}
            };
            VAREND;
    }

    /**
     * Get function name for standalone or non-standalone template.
     *
     * @param string $name base function name
     * @param string $tag original handlebars tag for debug
     */
    protected static function getFuncName(Context $context, string $name, string $tag): string
    {
        if ($context->options->strict && $name != 'miss') {
            $dbg = "'$tag', '$name', ";
            $name = 'debug';
        } else {
            $dbg = '';
        }

        return "LR::$name($dbg";
    }

    /**
     * Get string presentation of variables
     *
     * @param array<array<mixed>> $vn variable name array.
     * @param array<mixed> $blockParams
     *
     * @return array<string|array<mixed>> variable names
     */
    protected static function getVariableNames(Context $context, array $vn, array $blockParams = []): array
    {
        $vars = [[], []];
        $exps = [];
        foreach ($vn as $i => $v) {
            $V = static::getVariableNameOrSubExpression($context, $v);
            if (is_string($i)) {
                $vars[1][] = "'$i'=>{$V[0]}";
            } else {
                $vars[0][] = $V[0];
            }
            $exps[] = $V[1];
        }
        $bp = $blockParams ? ',' . Expression::listString($blockParams) : '';
        return ['[[' . implode(',', $vars[0]) . '],[' . implode(',', $vars[1]) . "]$bp]", $exps];
    }

    /**
     * Get string presentation of a sub expression
     *
     * @param array<mixed> $vars parsed arguments list
     *
     * @return array<string> code representing passed expression
     */
    public static function compileSubExpression(Context $context, array $vars): array
    {
        $ret = static::customHelper($context, $vars, true);

        return [$ret, 'FIXME: $subExpression'];
    }

    /**
     * Get string presentation of a subexpression or a variable
     *
     * @param array<array<mixed>|string|int> $var variable parsed path
     *
     * @return array<string> variable names
     */
    protected static function getVariableNameOrSubExpression(Context $context, array $var): array
    {
        return Parser::isSubExp($var) ? static::compileSubExpression($context, $var[1]) : static::getVariableName($context, $var);
    }

    /**
     * Get string presentation of a variable
     *
     * @param array<array<mixed>|string|int> $var variable parsed path
     * @param array<string>|null $lookup extra lookup string as valid PHP variable name
     *
     * @return array<string> variable names
     */
    protected static function getVariableName(Context $context, array $var, ?array $lookup = null): array
    {
        if (isset($var[0]) && ($var[0] === Parser::LITERAL)) {
            if ($var[1] === "undefined") {
                $var[1] = "null";
            }
            return [$var[1], preg_replace('/\'(.*)\'/', '$1', $var[1])];
        }

        [$levels, $spvar, $var] = Expression::analyze($var);
        $exp = Expression::toString($levels, $spvar, $var);
        $base = $spvar ? "\$cx->spVars" : '$in';

        // change base when trace to parent
        if ($levels > 0) {
            if ($spvar) {
                $base .= str_repeat("['_parent']", $levels);
            } else {
                $base = "\$cx->scopes[count(\$cx->scopes)-$levels]";
            }
        }

        if ((!$var || ($var[0] === null && count($var) == 1)) && $lookup === null) {
            return [$base, $exp];
        }

        if (count($var) > 0 && $var[0] === null) {
            array_shift($var);
        }

        $n = Expression::arrayString($var);
        $k = array_pop($var);
        $L = $lookup ? "[{$lookup[0]}]" : '';

        $lenStart = '';
        $lenEnd = '';

        if ($lookup === null && $k === 'length') {
            $checks = [];
            if ($levels > 0) {
                $checks[] = "isset($base)";
            }
            $p = count($var) ? Expression::arrayString($var) : '';
            if ($p !== '' && ($spvar || $levels === 0)) {
                $checks[] = "isset($base$p)";
            }
            if (!$spvar) {
                $checks[] = "$base$p" == '$in' ? '$inary' : "is_array($base$p)";
            }
            $lenStart = '(' . (count($checks) > 1 ? '(' : '') . implode(' && ', $checks) . (count($checks) > 1 ? ')' : '') . " ? count($base" . Expression::arrayString($var) . ') : ';
            $lenEnd = ')';
        }

        return ["$base$n$L ?? $lenStart" . ($context->options->strict ? (static::getFuncName($context, 'miss', '') . "'$exp')") : 'null') . "$lenEnd", $lookup ? "lookup $exp $lookup[1]" : $exp];
    }

    /**
     * Return compiled PHP code for a handlebars token
     *
     * @param array{bool, array<mixed>, array<mixed>, string} $info parsed information
     */
    protected static function compileToken(Context $context, array $info): string
    {
        [$raw, $vars, $token, $indent] = $info;

        $context->partialIndent = $indent;
        $context->currentToken = $token;

        if ($ret = static::operator($token[Token::POS_OP], $context, $vars)) {
            return $ret;
        }

        if (isset($vars[0][0])) {
            if ($ret = static::customHelper($context, $vars, $raw)) {
                return static::compileOutput($context, $ret, 'FIXME: helper', $raw);
            }
            if ($vars[0][0] === 'else') {
                return static::doElse($context, $vars);
            }
            if ($vars[0][0] === 'lookup') {
                return static::compileLookup($context, $vars, $raw);
            }
            if ($vars[0][0] === 'log') {
                return static::compileLog($context, $vars);
            }
        }

        return static::compileVariable($context, $vars, $raw);
    }

    /**
     * handle partial
     *
     * @param array<mixed> $vars parsed arguments list
     */
    public static function partial(Context $context, array &$vars): string
    {
        Parser::getBlockParams($vars);
        $pid = Parser::getPartialBlock($vars);
        $p = array_shift($vars);
        if (!isset($vars[0])) {
            $vars[0] = $context->options->explicitPartialContext ? [0, 'null'] : [];
        }
        $v = static::getVariableNames($context, $vars);
        $tag = ">$p[0] " . implode(' ', $v[1]);
        if (Parser::isSubExp($p)) {
            [$p] = static::compileSubExpression($context, $p[1]);
        } else {
            $p = Expression::quoteString($p[0]);
        }
        $sp = "(\$sp ?? '') . '{$context->partialIndent}'";
        return $context->separator .
            static::getFuncName($context, 'p', $tag) . "\$cx, $p, $v[0], $pid, $sp)" .
            $context->separator;
    }

    /**
     * handle inline partial
     *
     * @param array<mixed> $vars parsed arguments list
     */
    public static function inline(Context $context, array $vars): string
    {
        Parser::getBlockParams($vars);
        [$code] = array_shift($vars);
        $p = array_shift($vars);
        if (!isset($vars[0])) {
            $vars[0] = $context->options->explicitPartialContext ? [0, 'null'] : [];
        }
        $v = static::getVariableNames($context, $vars);
        $tag = ">*inline $p[0]" . implode(' ', $v[1]);
        return $context->separator . static::getFuncName($context, 'in', $tag) . "\$cx, '{$p[0]}', $code){$context->separator}";
    }

    /**
     * Return compiled PHP code for a handlebars inverted section begin token
     *
     * @param array<mixed> $vars parsed arguments list
     */
    protected static function invertedSection(Context $context, array $vars): string
    {
        $v = static::getVariableName($context, $vars[0]);
        return "{$context->cndStart}("
            . static::getFuncName($context, 'isec', '^' . $v[1])
            . "{$v[0]})){$context->cndThen}";
    }

    /**
     * Return compiled PHP code for a handlebars block custom helper begin token
     *
     * @param array<mixed> $vars parsed arguments list
     * @param bool $inverted the logic will be inverted
     */
    protected static function blockCustomHelper(Context $context, array $vars, bool $inverted = false): string
    {
        $bp = Parser::getBlockParams($vars);
        $ch = array_shift($vars);
        $invertedStr = $inverted ? 'true' : 'false';
        static::markUsedHelper($context, $ch[0]);
        $v = static::getVariableNames($context, $vars, $bp);

        return $context->separator . static::getFuncName($context, 'hbbch', ($inverted ? '^' : '#') . implode(' ', $v[1]))
            . "\$cx, '$ch[0]', {$v[0]}, \$in, $invertedStr, function(\$cx, \$in) {{$context->fStart}";
    }

    /**
     * Return compiled PHP code for a handlebars block end token
     *
     * @param array<mixed> $vars parsed arguments list
     * @param string|null $match should also match to this operator
     */
    protected static function blockEnd(Context $context, array $vars, ?string $match = null): string
    {
        $pop = $context->stack[count($context->stack) - 1];

        switch (isset($context->helpers[$context->currentToken[Token::POS_INNERTAG]]) ? 'skip' : $context->currentToken[Token::POS_INNERTAG]) {
            case 'if':
            case 'unless':
                if ($pop === ':') {
                    array_pop($context->stack);
                    return $context->cndEnd;
                }
                return "{$context->cndElse}''{$context->cndEnd}";
            case 'with':
                return "{$context->fEnd}}){$context->separator}";
        }

        if ($pop === ':') {
            array_pop($context->stack);
            return "{$context->fEnd}}){$context->separator}";
        }

        switch ($pop) {
            case '#':
                return "{$context->fEnd}}){$context->separator}";
            case '^':
                return "{$context->cndElse}''{$context->cndEnd}";
        }

        throw new \Exception('Failed to match case for blockEnd');
    }

    /**
     * Return compiled PHP code for a handlebars block begin token
     *
     * @param array<mixed> $vars parsed arguments list
     */
    protected static function blockBegin(Context $context, array $vars): string
    {
        $v = isset($vars[1]) ? static::getVariableNameOrSubExpression($context, $vars[1]) : [null, []];
        switch ($vars[0][0] ?? null) {
            case 'if':
                $includeZero = (isset($vars['includeZero'][1]) && $vars['includeZero'][1]) ? 'true' : 'false';
                return "{$context->cndStart}(" . static::getFuncName($context, 'ifvar', $v[1])
                    . "{$v[0]}, {$includeZero})){$context->cndThen}";
            case 'unless':
                $includeZero = (isset($vars['includeZero'][1]) && $vars['includeZero'][1]) ? 'true' : 'false';
                return "{$context->cndStart}(!" . static::getFuncName($context, 'ifvar', $v[1])
                    . "{$v[0]}, {$includeZero})){$context->cndThen}";
            case 'each':
                return static::section($context, $vars, true);
            case 'with':
                if ($r = static::with($context, $vars)) {
                    return $r;
                }
        }

        return static::section($context, $vars);
    }

    /**
     * compile {{#foo}} token
     *
     * @param array<mixed> $vars parsed arguments list
     * @param bool $isEach the section is #each
     */
    protected static function section(Context $context, array $vars, bool $isEach = false): string
    {
        $bs = 'null';
        $be = '';
        if ($isEach) {
            $bp = Parser::getBlockParams($vars);
            $bs = $bp ? Expression::listString($bp) : 'null';
            $be = $bp ? (' as |' . implode(' ', $bp) . '|') : '';
            array_shift($vars);
        }
        $v = static::getVariableNameOrSubExpression($context, $vars[0]);
        $each = $isEach ? 'true' : 'false';
        return $context->separator . static::getFuncName($context, 'sec', ($isEach ? 'each ' : '') . $v[1] . $be)
            . "\$cx, {$v[0]}, $bs, \$in, $each, function(\$cx, \$in) {{$context->fStart}";
    }

    /**
     * compile {{with}} token
     *
     * @param array<mixed> $vars parsed arguments list
     *
     * @return string Return compiled code segment for the token
     */
    protected static function with(Context $context, array $vars)
    {
        $v = isset($vars[1]) ? static::getVariableNameOrSubExpression($context, $vars[1]) : [null, []];
        $bp = Parser::getBlockParams($vars);
        $bs = $bp ? Expression::listString($bp) : 'null';
        $be = $bp ? " as |$bp[0]|" : '';
        return $context->separator . static::getFuncName($context, 'wi', 'with ' . $v[1] . $be)
            . "\$cx, {$v[0]}, $bs, \$in, function(\$cx, \$in) {{$context->fStart}";
    }

    /**
     * Return compiled PHP code for a handlebars custom helper token
     *
     * @param array<mixed> $vars parsed arguments list
     * @param bool $raw is this {{{ token or not
     */
    protected static function customHelper(Context $context, array $vars, bool $raw): string
    {
        if (count($vars[0]) > 1) {
            return '';
        }

        if (!isset($context->helpers[$vars[0][0]])) {
            if ($vars[0][0] == 'lookup') {
                return static::compileLookup($context, $vars, $raw, true);
            } elseif ($context->options->knownHelpersOnly && count($vars) > 1) {
                throw new \Exception('Missing helper: "' . $vars[0][0] . '"');
            }
            return '';
        }

        $ch = array_shift($vars);
        $v = static::getVariableNames($context, $vars);
        static::markUsedHelper($context, $ch[0]);

        return static::getFuncName($context, 'hbch', "$ch[0] " . implode(' ', $v[1]))
            . "\$cx, '$ch[0]', {$v[0]}, \$in)";
    }

    /**
     * Return compiled PHP code for a handlebars else token
     *
     * @param array<mixed> $vars parsed arguments list
     */
    protected static function doElse(Context $context, array $vars): string
    {
        $v = $context->stack[count($context->stack) - 2];

        if (($v === '[if]' && !isset($context->helpers['if'])) ||
           ($v === '[unless]' && !isset($context->helpers['unless']))) {
            $context->stack[] = ':';
            return $context->cndElse;
        }

        return "{$context->fEnd}}, function(\$cx, \$in) {{$context->fStart}";
    }

    /**
     * Return compiled PHP code for a handlebars log token
     *
     * @param array<mixed> $vars parsed arguments list
     */
    protected static function compileLog(Context $context, array &$vars): string
    {
        array_shift($vars);
        $v = static::getVariableNames($context, $vars);
        return $context->separator . static::getFuncName($context, 'lo', $v[1][0])
            . "{$v[0]}){$context->separator}";
    }

    /**
     * Return compiled PHP code for a handlebars lookup token
     *
     * @param array<mixed> $vars parsed arguments list
     * @param bool $raw is this {{{ token or not
     * @param bool $nosep true to compile without separator
     */
    protected static function compileLookup(Context $context, array &$vars, bool $raw, bool $nosep = false): string
    {
        $v2 = static::getVariableName($context, $vars[2]);
        $v = static::getVariableName($context, $vars[1], $v2);
        $sep = $nosep ? '' : $context->separator;
        $ex = $nosep ? ', 1' : '';

        return $sep . static::getFuncName($context, $raw ? 'raw' : 'encq', $v[1]) . "{$v[0]}$ex){$sep}";
    }

    /**
     * Return compiled PHP code for template output
     *
     * @param string $variable PHP code for the variable
     * @param string $expression normalized handlebars expression
     * @param bool $raw is this {{{ token or not
     */
    protected static function compileOutput(Context $context, string $variable, string $expression, bool $raw): string
    {
        $sep = $context->separator;
        return $sep . static::getFuncName($context, $raw ? 'raw' : 'encq', $expression) . "$variable)$sep";
    }

    /**
     * Return compiled PHP code for a handlebars variable token
     *
     * @param array<mixed> $vars parsed arguments list
     * @param bool $raw is this {{{ token or not
     */
    protected static function compileVariable(Context $context, array $vars, bool $raw): string
    {
        $v = static::getVariableName($context, $vars[0]);
        return static::compileOutput($context, $v[0], $v[1], $raw);
    }

    protected static function markUsedHelper(Context $context, string $name): void
    {
        if (!isset($context->usedHelpers[$name])) {
            $context->usedHelpers[$name] = true;
        }
    }
}
