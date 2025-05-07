<?php

namespace DevTheorem\Handlebars;

/**
 * @internal
 */
class Validator
{
    /**
     * Verify template
     */
    public static function verify(Context $context, string $template): void
    {
        $template = SafeString::stripExtendedComments($template);
        $context->level = 0;
        Token::setDelimiter($context);

        while (preg_match($context->tokenSearch, $template, $matches)) {
            // Skip a token when it is slash escaped
            if ($matches[Token::POS_LSPACE] === '' && preg_match('/^(.*?)(\\\\+)$/s', $matches[Token::POS_LOTHER], $escmatch)) {
                if (strlen($escmatch[2]) % 4) {
                    static::pushToken($context, substr($matches[Token::POS_LOTHER], 0, -2) . $context->startChar);
                    $matches[Token::POS_BEGINTAG] = substr($matches[Token::POS_BEGINTAG], 1);
                    $template = implode('', array_slice($matches, Token::POS_BEGINTAG));
                    continue;
                } else {
                    $matches[Token::POS_LOTHER] = $escmatch[1] . str_repeat('\\', strlen($escmatch[2]) / 2);
                }
            }

            $V = static::token($matches, $context);
            static::pushLeft($context);
            if ($V) {
                if (is_array($V)) {
                    array_push($V, $matches, $context->partialIndent);
                }
                static::pushToken($context, $V);
            }
            // set template to remainder after pushed token
            $template = "{$matches[Token::POS_RSPACE]}{$matches[Token::POS_ROTHER]}";
        }
        static::pushToken($context, $template);

        if ($context->level > 0) {
            array_pop($context->stack);
            array_pop($context->stack);
            $token = array_pop($context->stack);
            $context->error[] = 'Unclosed token ' . ($context->rawBlock ? "{{{{{$token}}}}}" : ($context->partialBlock ? "{{#>{$token}}}" : "{{#{$token}}}")) . ' !!';
        }
    }

    /**
     * push left string of current token and clear it
     */
    protected static function pushLeft(Context $context): void
    {
        $L = $context->currentToken[Token::POS_LOTHER] . $context->currentToken[Token::POS_LSPACE];
        static::pushToken($context, $L);
        $context->currentToken[Token::POS_LOTHER] = $context->currentToken[Token::POS_LSPACE] = '';
    }

    /**
     * push a string into the partial stacks
     *
     * @param string $append a string to be appended int partial stacks
     */
    protected static function pushPartial(Context $context, string $append): void
    {
        $appender = function (&$p) use ($append) {
            $p .= $append;
        };
        array_walk($context->inlinePartial, $appender);
        array_walk($context->partialBlock, $appender);
    }

    /**
     * push a token into the stack when it is not empty string
     *
     * @param array<mixed>|string $token a parsed token or a string
     */
    protected static function pushToken(Context $context, array|string $token): void
    {
        if ($token === '') {
            return;
        }
        if (is_string($token)) {
            static::pushPartial($context, $token);
        } else {
            static::pushPartial($context, Token::toString($context->currentToken));
            switch ($context->currentToken[Token::POS_OP]) {
                case '#*':
                    array_unshift($context->inlinePartial, '');
                    break;
                case '#>':
                    array_unshift($context->partialBlock, '');
                    break;
            }
        }
        $context->parsed[0][] = $token;
    }

    /**
     * push current token into the section stack
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     */
    protected static function pushStack(Context $context, string $operation, array $vars): void
    {
        [$levels, $spvar, $var] = Expression::analyze($vars[0]);
        $context->stack[] = $context->currentToken[Token::POS_INNERTAG];
        $context->stack[] = Expression::toString($levels, $spvar, $var);
        $context->stack[] = $operation;
        $context->level++;
    }

    /**
     * Verify delimiters and operators
     *
     * @param string[] $token detected handlebars {{ }} token
     *
     * @return bool Return true when invalid
     */
    protected static function delimiter(array $token, Context $context): bool
    {
        // {{ }}} or {{{ }} are invalid
        if (strlen($token[Token::POS_BEGINRAW]) !== strlen($token[Token::POS_ENDRAW])) {
            $context->error[] = 'Bad token ' . Token::toString($token) . ' ! Do you mean ' . Token::toString($token, [Token::POS_BEGINRAW => '', Token::POS_ENDRAW => '']) . ' or ' . Token::toString($token, [Token::POS_BEGINRAW => '{', Token::POS_ENDRAW => '}']) . '?';
            return true;
        }
        // {{{# }}} or {{{! }}} or {{{/ }}} or {{{^ }}} are invalid.
        if ((strlen($token[Token::POS_BEGINRAW]) == 1) && $token[Token::POS_OP] && ($token[Token::POS_OP] !== '&')) {
            $context->error[] = 'Bad token ' . Token::toString($token) . ' ! Do you mean ' . Token::toString($token, [Token::POS_BEGINRAW => '', Token::POS_ENDRAW => '']) . ' ?';
            return true;
        }

        return false;
    }

    /**
     * Verify operators
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     */
    protected static function operator(string $operator, Context $context, array &$vars): bool|int|string|null
    {
        switch ($operator) {
            case '#*':
                if (!$context->compile) {
                    $context->stack[] = count($context->parsed[0]) + ($context->currentToken[Token::POS_LOTHER] . $context->currentToken[Token::POS_LSPACE] === '' ? 0 : 1);
                    static::pushStack($context, '#*', $vars);
                }
                return static::inline($context, $vars);

            case '#>':
                if (!$context->compile) {
                    $context->stack[] = count($context->parsed[0]) + ($context->currentToken[Token::POS_LOTHER] . $context->currentToken[Token::POS_LSPACE] === '' ? 0 : 1);
                    $vars[Parser::PARTIALBLOCK] = ++$context->usedPBlock;
                    static::pushStack($context, '#>', $vars);
                }
                // no break
            case '>':
                return static::partial($context, $vars);

            case '^':
                if (!isset($vars[0][0])) {
                    return static::doElse($context, $vars);
                }

                static::doElseChain($context);

                if (static::isBlockHelper($context, $vars)) {
                    static::pushStack($context, '#', $vars);
                    return static::blockCustomHelper($context, $vars, true);
                }

                static::pushStack($context, '^', $vars);
                return static::invertedSection($context, $vars);

            case '/':
                $r = static::blockEnd($context, $vars);
                if ($r !== Token::POS_BACKFILL) {
                    array_pop($context->stack);
                    array_pop($context->stack);
                    array_pop($context->stack);
                }
                return $r;

            case '#':
                static::doElseChain($context);
                static::pushStack($context, '#', $vars);

                if (static::isBlockHelper($context, $vars)) {
                    return static::blockCustomHelper($context, $vars);
                }

                return static::blockBegin($context, $vars);
        }

        return null;
    }

    /**
     * validate inline partial begin token
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     *
     * @return bool Return true when inline partial ends
     */
    protected static function inlinePartial(Context $context, array $vars): bool
    {
        $ended = false;
        if ($context->currentToken[Token::POS_OP] === '/') {
            if (static::blockEnd($context, $vars, '#*')) {
                $tmpl = array_shift($context->inlinePartial) . $context->currentToken[Token::POS_LOTHER] . $context->currentToken[Token::POS_LSPACE];
                $c = self::getPartialTokensIndex($context);
                $P = &$context->parsed[0][$c];
                if (isset($P[1][1][0])) {
                    $context->usedPartial[$P[1][1][0]] = $tmpl;
                    $P[1][0][0] = Partial::compileDynamic($context, $P[1][1][0]);
                }
                $ended = true;
            }
        }
        return $ended;
    }

    /**
     * validate partial block token
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     *
     * @return bool Return true when partial block ends
     */
    protected static function partialBlock(Context $context, array $vars): bool
    {
        $ended = false;
        if ($context->currentToken[Token::POS_OP] === '/') {
            if (static::blockEnd($context, $vars, '#>')) {
                $tmpl = array_shift($context->partialBlock) . $context->currentToken[Token::POS_LOTHER] . $context->currentToken[Token::POS_LSPACE];
                $c = self::getPartialTokensIndex($context);
                $P = &$context->parsed[0][$c];
                $found = Partial::resolve($context, $vars[0][0]) !== null;
                $v = $found ? "@partial-block{$P[1][Parser::PARTIALBLOCK]}" : $vars[0][0];

                if (!$context->partialBlock) {
                    $context->usedPartial[$v] = $tmpl;
                    Partial::compileDynamic($context, $v);
                    if ($found) {
                        Partial::read($context, $vars[0][0]);
                    }
                }
                $ended = true;
            }
        }
        return $ended;
    }

    private static function getPartialTokensIndex(Context $context): int
    {
        $c = $context->stack[count($context->stack) - 4];
        $context->parsed[0] = array_slice($context->parsed[0], 0, $c + 1);
        return $c;
    }

    /**
     * handle else chain
     */
    protected static function doElseChain(Context $context): void
    {
        if ($context->elseChain) {
            $context->elseChain = false;
        } else {
            array_unshift($context->elseLvl, []);
        }
    }

    /**
     * validate block begin token
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     *
     * @return bool Return true always
     */
    protected static function blockBegin(Context $context, array $vars)
    {
        switch ((isset($vars[0][0]) && is_string($vars[0][0])) ? $vars[0][0] : null) {
            case 'with':
                return static::with($context, $vars);
            case 'each':
                return static::section($context, $vars, true);
            case 'unless':
            case 'if':
                return static::requireOneArgument($context, $vars);
            default:
                return static::section($context, $vars);
        }
    }

    /**
     * validate builtin helpers
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     */
    protected static function builtin(Context $context, array $vars): void
    {
        if (count($vars) < 2) {
            $context->error[] = "No argument after {{#{$vars[0][0]}}} !";
        }
    }

    /**
     * validate section token
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     * @param bool $isEach the section is #each
     *
     * @return bool Return true always
     */
    protected static function section(Context $context, array $vars, bool $isEach = false)
    {
        if ($isEach) {
            static::builtin($context, $vars);
        } else {
            if (count($vars) > 1) {
                $context->error[] = "Custom helper not found: {$vars[0][0]} in " . Token::toString($context->currentToken) . ' !';
            }
        }
        return true;
    }

    /**
     * validate with token
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     *
     * @return bool Return true always
     */
    protected static function with(Context $context, array $vars)
    {
        if (isset($vars[Parser::BLOCKPARAM])) {
            unset($vars[Parser::BLOCKPARAM]);
        }
        if (count($vars) !== 2) {
            $context->error[] = "#{$vars[0][0]} requires exactly one argument";
        }
        return true;
    }

    /**
     * validate if, unless, or with token
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     */
    protected static function requireOneArgument(Context $context, array $vars): true
    {
        // don't count non-int keys (hash arguments)
        $intKeys = 0;
        foreach ($vars as $key => $var) {
            if (is_int($key)) {
                $intKeys++;
            }
        }
        if ($intKeys !== 2) {
            $context->error[] = "#{$vars[0][0]} requires exactly one argument";
        }
        return true;
    }

    /**
     * validate block custom helper token
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     * @param bool $inverted the logic will be inverted
     * @return bool Return true always
     */
    protected static function blockCustomHelper(Context $context, array $vars, bool $inverted = false)
    {
        if (is_string($vars[0][0])) {
            static::resolveHelper($context, $vars);
        }
        return true;
    }

    /**
     * validate inverted section
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     * @return bool Return true always
     */
    protected static function invertedSection(Context $context, array $vars)
    {
        return true;
    }

    /**
     * Return compiled PHP code for a handlebars block end token
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     * @param string|null $match should also match to this operator
     *
     * @return bool|int Return true when required block ended, or Token::POS_BACKFILL when backfill happened.
     */
    protected static function blockEnd(Context $context, array $vars, ?string $match = null)
    {
        $c = count($context->stack) - 2;
        $pop = $c >= 0 ? $context->stack[$c + 1] : '';
        if ($match !== null && $match !== $pop) {
            return false;
        }
        // if we didn't match our $pop, we didn't actually do a level, so only subtract a level here
        $context->level--;
        $pop2 = $c >= 0 ? $context->stack[$c] : '';
        switch ($context->currentToken[Token::POS_INNERTAG]) {
            case 'with':
                if ($pop2 !== '[with]') {
                    $context->error[] = 'Unexpect token: {{/with}} !';
                    return false;
                }
                return true;
        }

        switch ($pop) {
            case '#':
            case '^':
                $elseChain = array_shift($context->elseLvl);
                if (isset($elseChain[0])) {
                    // we need to repeat a level due to else chains: {{else if}}
                    $context->level++;
                    $context->currentToken[Token::POS_RSPACE] = $context->currentToken[Token::POS_BACKFILL] = '{{/' . implode('}}{{/', $elseChain) . '}}' . Token::toString($context->currentToken) . $context->currentToken[Token::POS_RSPACE];
                    return Token::POS_BACKFILL;
                }
                // no break
            case '#>':
            case '#*':
                [$levels, $spvar, $var] = Expression::analyze($vars[0]);
                $v = Expression::toString($levels, $spvar, $var);
                if ($pop2 !== $v) {
                    $context->error[] = 'Unexpect token ' . Token::toString($context->currentToken) . " ! Previous token {{{$pop}$pop2}} is not closed";
                    return false;
                }
                return true;
            default:
                $context->error[] = 'Unexpect token: ' . Token::toString($context->currentToken) . ' !';
                return false;
        }
    }

    /**
     * handle raw block
     *
     * @param string[] $token detected handlebars {{ }} token
     *
     * @return bool Return true when in rawblock mode
     */
    protected static function rawblock(array &$token, Context $context): bool
    {
        $inner = $token[Token::POS_INNERTAG];

        // skip parse when inside raw block
        if ($context->rawBlock && !($token[Token::POS_BEGINRAW] === '{{' && $token[Token::POS_OP] === '/' && $context->rawBlock === $inner)) {
            return true;
        }

        // Handle raw block
        if ($token[Token::POS_BEGINRAW] === '{{') {
            if ($token[Token::POS_ENDRAW] !== '}}') {
                $context->error[] = 'Bad token ' . Token::toString($token) . ' ! Do you mean ' . Token::toString($token, [Token::POS_ENDRAW => '}}']) . ' ?';
            }
            if ($context->rawBlock) {
                $context->rawBlock = false;
                Token::setDelimiter($context);
            } else {
                if ($token[Token::POS_OP]) {
                    $context->error[] = "Wrong raw block begin with " . Token::toString($token) . ' ! Remove "' . $token[Token::POS_OP] . '" to fix this issue.';
                }
                // store name (before arguments) in order to find the block end
                $context->rawBlock = preg_split('/\s+/', $token[Token::POS_INNERTAG], 2)[0];
                Token::setDelimiter($context);
                $token[Token::POS_OP] = '#'; // parse as custom block
            }
            $token[Token::POS_ENDRAW] = '}}';
        }

        return false;
    }

    /**
     * handle comment
     *
     * @param string[] $token detected handlebars {{ }} token
     *
     * @return bool Return true when is comment
     */
    protected static function comment(array $token): bool
    {
        return $token[Token::POS_OP] === '!';
    }

    /**
     * Collect handlebars usage information, detect template error.
     *
     * @param string[] $token detected handlebars {{ }} token
     *
     * @return string|array{bool,array<mixed>}|null Token string when rawblock, array when valid token to be compiled, null to skip the token.
     */
    protected static function token(array &$token, Context $context): string|array|null
    {
        $context->currentToken = &$token;

        if (static::rawblock($token, $context)) {
            return Token::toString($token);
        }

        if (static::delimiter($token, $context)) {
            return null;
        }

        if (static::comment($token)) {
            static::spacing($token, $context);
            return null;
        }

        [$raw, $vars] = Parser::parse($token, $context);

        // Handle spacing (standalone tags, partial indent)
        static::spacing($token, $context, ($token[Token::POS_OP] === '' || $token[Token::POS_OP] === '&') && (!isset($vars[0][0]) || $vars[0][0] !== 'else') || $context->options->ignoreStandalone);

        $inlinePartial = static::inlinePartial($context, $vars);
        $partialBlock = static::partialBlock($context, $vars);

        if ($partialBlock || $inlinePartial) {
            $context->stack = array_slice($context->stack, 0, -4);
            static::pushPartial($context, $context->currentToken[Token::POS_LOTHER] . $context->currentToken[Token::POS_LSPACE] . Token::toString($context->currentToken));
            $context->currentToken[Token::POS_LOTHER] = '';
            $context->currentToken[Token::POS_LSPACE] = '';
            return null;
        }

        if (static::operator($token[Token::POS_OP], $context, $vars)) {
            return isset($token[Token::POS_BACKFILL]) ? null : [$raw, $vars];
        }

        if (count($vars) == 0) {
            return $context->error[] = 'Wrong variable naming in ' . Token::toString($token);
        }

        if (!isset($vars[0])) {
            return $context->error[] = 'Do not support name=value in ' . Token::toString($token) . ', you should use it after a custom helper.';
        }

        if (!isset($vars[0][0])) {
            return [$raw, $vars];
        }

        if ($vars[0][0] === 'else') {
            static::doElse($context, $vars);
            return [$raw, $vars];
        }

        if (!static::helper($context, $vars)) {
            static::lookup($context, $vars);
            static::log($context, $vars);
        }

        return [$raw, $vars];
    }

    /**
     * Return 1 or larger number when else token detected
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     * @return bool Return true always
     */
    protected static function doElse(Context $context, array $vars)
    {
        if ($context->level == 0) {
            $context->error[] = '{{else}} only valid in if, unless, each, and #section context';
        }

        if (isset($vars[1][0])) {
            $if = $vars[1][0];
            $innerTag = $context->currentToken[Token::POS_INNERTAG];
            $condition = preg_replace("/^\\s*else\\s+{$if}\\s*/", '', $innerTag);
            $context->currentToken[Token::POS_INNERTAG] = 'else';
            $context->currentToken[Token::POS_RSPACE] = "{{#$if $condition}}" . $context->currentToken[Token::POS_RSPACE];
            if (!$context->elseLvl) {
                array_unshift($context->elseLvl, []);
            }
            array_unshift($context->elseLvl[0], $if);
            $context->elseChain = true;
        }

        return true;
    }

    /**
     * Validate {{log ...}}
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     */
    public static function log(Context $context, array $vars): void
    {
        if (isset($vars[0][0]) && $vars[0][0] === 'log') {
            if (count($vars) < 2) {
                $context->error[] = "No argument after {{log}} !";
            }
        }
    }

    /**
     * Validate {{lookup ...}}
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     */
    public static function lookup(Context $context, array $vars): void
    {
        if (isset($vars[0][0]) && $vars[0][0] === 'lookup') {
            if (count($vars) < 2) {
                $context->error[] = "No argument after {{lookup}} !";
            } elseif (count($vars) < 3) {
                $context->error[] = "{{lookup}} requires 2 arguments !";
            }
        }
    }

    /**
     * Return true when the name is listed in helper table
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     * @param bool $checkSubexp true when check for subexpression
     *
     * @return bool Return true when it is custom helper
     */
    public static function helper(Context $context, array $vars, bool $checkSubexp = false): bool
    {
        if (static::resolveHelper($context, $vars)) {
            return true;
        }

        if ($checkSubexp) {
            switch ($vars[0][0]) {
                case 'if':
                case 'unless':
                case 'with':
                case 'each':
                case 'lookup':
                    return true;
            }
        }

        return false;
    }

    /**
     * use to resolve helper, return true when helper found
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     */
    public static function resolveHelper(Context $context, array $vars): bool
    {
        if (count($vars[0]) !== 1) {
            return false;
        }

        $name = $vars[0][0];
        if (isset($context->helpers[$name])) {
            return true;
        }

        if ($context->helperResolver) {
            $helper = ($context->helperResolver)($context, $name);
            if ($helper) {
                $context->helpers[$name] = $helper;
                return true;
            }
        }

        return false;
    }

    /**
     * Return true when this token is block custom helper
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     */
    protected static function isBlockHelper(Context $context, array $vars): bool
    {
        if (!isset($vars[0][0])) {
            return false;
        }

        return static::resolveHelper($context, $vars);
    }

    /**
     * validate inline partial
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     * @return bool Return true always
     */
    protected static function inline(Context $context, array $vars)
    {
        if (!isset($vars[0][0]) || $vars[0][0] !== 'inline') {
            $context->error[] = "Do not support {{#*{$context->currentToken[Token::POS_INNERTAG]}}}, now we only support {{#*inline \"partialName\"}}template...{{/inline}}";
        }
        if (!isset($vars[1][0])) {
            $context->error[] = "Error in {{#*{$context->currentToken[Token::POS_INNERTAG]}}}: inline require 1 argument for partial name!";
        }
        return true;
    }

    /**
     * validate partial
     *
     * @param array<bool|int|string|array<mixed>> $vars parsed arguments list
     *
     * @return int|bool Return 1 or larger number for runtime partial, return true for other case
     */
    protected static function partial(Context $context, array $vars)
    {
        if (Parser::isSubExp($vars[0])) {
            return $context->usedDynPartial++;
        } else {
            if ($context->currentToken[Token::POS_OP] !== '#>') {
                Partial::read($context, $vars[0][0]);
            }
        }

        return true;
    }

    /**
     * Modify $token when spacing rules matched.
     *
     * @param array<string> $token detected handlebars {{ }} token
     * @param bool $nost do not do standalone logic
     */
    protected static function spacing(array &$token, Context $context, bool $nost = false): ?string
    {
        // left line change detection
        $lsp = preg_match('/^(.*)(\\r?\\n)([ \\t]*?)$/s', $token[Token::POS_LSPACE], $lmatch);
        $ind = $lsp ? $lmatch[3] : $token[Token::POS_LSPACE];
        // right line change detection
        $rsp = preg_match('/^([ \\t]*?)(\\r?\\n)(.*)$/s', $token[Token::POS_RSPACE], $rmatch);
        $st = true;
        // setup ahead flag
        $ahead = $context->tokenAhead;
        $context->tokenAhead = preg_match('/^[^\n]*{{/s', $token[Token::POS_RSPACE] . $token[Token::POS_ROTHER]);
        // reset partial indent
        $context->partialIndent = '';
        // same tags in the same line , not standalone
        if (!$lsp && $ahead) {
            $st = false;
        }
        if ($nost) {
            $st = false;
        }
        // not standalone because other things in the same line ahead
        if ($token[Token::POS_LOTHER] && !$token[Token::POS_LSPACE]) {
            $st = false;
        }
        // not standalone because other things in the same line behind
        if ($token[Token::POS_ROTHER] && !$token[Token::POS_RSPACE]) {
            $st = false;
        }
        if ($st && (
            ($lsp && $rsp) // both side cr
                || ($rsp && !$token[Token::POS_LOTHER]) // first line without left
                || ($lsp && !$token[Token::POS_ROTHER]) // final line
        )) {
            // handle partial
            $leftSpace = isset($lmatch[2]) ? ($lmatch[1] . $lmatch[2]) : '';
            if ($token[Token::POS_OP] === '>') {
                if (!$context->options->preventIndent) {
                    $context->partialIndent = $token[Token::POS_LSPACECTL] ? '' : $ind;
                    $token[Token::POS_LSPACE] = $leftSpace;
                }
            } else {
                $token[Token::POS_LSPACE] = $leftSpace;
            }
            $token[Token::POS_RSPACE] = $rmatch[3] ?? '';
        }

        // Handle space control.
        if ($token[Token::POS_LSPACECTL]) {
            $token[Token::POS_LSPACE] = '';
        }
        if ($token[Token::POS_RSPACECTL]) {
            $token[Token::POS_RSPACE] = '';
        }

        return null;
    }
}
