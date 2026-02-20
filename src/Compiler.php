<?php

namespace DevTheorem\Handlebars;

use DevTheorem\HandlebarsParser\Ast\BlockStatement;
use DevTheorem\HandlebarsParser\Ast\BooleanLiteral;
use DevTheorem\HandlebarsParser\Ast\CommentStatement;
use DevTheorem\HandlebarsParser\Ast\ContentStatement;
use DevTheorem\HandlebarsParser\Ast\Decorator;
use DevTheorem\HandlebarsParser\Ast\Expression as AstExpression;
use DevTheorem\HandlebarsParser\Ast\Hash;
use DevTheorem\HandlebarsParser\Ast\Literal;
use DevTheorem\HandlebarsParser\Ast\MustacheStatement;
use DevTheorem\HandlebarsParser\Ast\Node;
use DevTheorem\HandlebarsParser\Ast\NullLiteral;
use DevTheorem\HandlebarsParser\Ast\NumberLiteral;
use DevTheorem\HandlebarsParser\Ast\PartialBlockStatement;
use DevTheorem\HandlebarsParser\Ast\PartialStatement;
use DevTheorem\HandlebarsParser\Ast\PathExpression;
use DevTheorem\HandlebarsParser\Ast\Program;
use DevTheorem\HandlebarsParser\Ast\StringLiteral;
use DevTheorem\HandlebarsParser\Ast\SubExpression;
use DevTheorem\HandlebarsParser\Ast\UndefinedLiteral;
use DevTheorem\HandlebarsParser\Parser;

/**
 * @internal
 */
final class Compiler
{
    private Context $context;

    /**
     * Compile-time stack of block param name arrays, innermost first.
     * Only populated for constructs that push to $cx->blParam at runtime (currently #each).
     * @var list<list<string>>
     */
    private array $blockParamValues = [];

    public function __construct(
        private readonly Parser $parser,
    ) {}

    public function compile(Program $program, Context $context): string
    {
        $this->context = $context;
        $this->blockParamValues = [];
        $code = '';

        foreach ($program->body as $statement) {
            $code .= $this->accept($statement);
        }

        return $code;
    }

    /**
     * Compile Handlebars template to PHP function.
     *
     * @param string $code generated PHP code
     */
    public function composePHPRender(string $code): string
    {
        $runtime = Runtime::class;
        $helperOptions = HelperOptions::class;
        $safeStringClass = SafeString::class;
        $runtimeContext = RuntimeContext::class;
        $helpers = Exporter::helpers($this->context);
        $partials = implode(",\n", $this->context->partialCode);

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
                return '$code';
            };
            VAREND;
    }

    private function compileProgram(Program $program, bool $withSp = false): string
    {
        $code = '';

        foreach ($program->body as $statement) {
            $code .= $this->accept($statement);
        }

        $quoted = "'" . $code . "'";

        return $withSp ? "\$sp.$quoted" : $quoted;
    }

    private function accept(Node $node): string
    {
        return match (true) {
            $node instanceof BlockStatement && $node->type === 'DecoratorBlock' => $this->DecoratorBlock($node),
            $node instanceof BlockStatement => $this->BlockStatement($node),
            $node instanceof PartialStatement => $this->PartialStatement($node),
            $node instanceof PartialBlockStatement => $this->PartialBlockStatement($node),
            $node instanceof Decorator => $this->Decorator($node),
            $node instanceof MustacheStatement => $this->MustacheStatement($node),
            $node instanceof ContentStatement => $this->ContentStatement($node),
            $node instanceof CommentStatement => $this->CommentStatement($node),
            $node instanceof SubExpression => $this->SubExpression($node),
            default => throw new \Exception('Unknown type: ' . (new \ReflectionClass($node))->getShortName()),
        };
    }

    private function compileExpression(AstExpression $expr): string
    {
        return match (true) {
            $expr instanceof SubExpression => $this->SubExpression($expr),
            $expr instanceof PathExpression => $this->PathExpression($expr),
            $expr instanceof StringLiteral => $this->StringLiteral($expr),
            $expr instanceof NumberLiteral => $this->NumberLiteral($expr),
            $expr instanceof BooleanLiteral => $this->BooleanLiteral($expr),
            $expr instanceof NullLiteral => $this->NullLiteral($expr),
            $expr instanceof UndefinedLiteral => $this->UndefinedLiteral($expr),
            default => throw new \Exception('Unknown expression type: ' . (new \ReflectionClass($expr))->getShortName()),
        };
    }

    // ── Statements ──────────────────────────────────────────────────

    private function BlockStatement(BlockStatement $block): string
    {
        $helperName = $this->getSimpleHelperName($block->path);

        if ($helperName !== null) {
            // Custom block helper takes priority
            if ($this->resolveHelper($helperName)) {
                return $this->compileBlockHelper($block, $helperName);
            }

            // Built-in block helpers
            switch ($helperName) {
                case 'if':
                    return $this->compileIf($block, false);
                case 'unless':
                    return $this->compileIf($block, true);
                case 'each':
                    return $this->compileEach($block);
                case 'with':
                    return $this->compileWith($block);
            }

            if ($block->params) {
                throw new \Exception('Missing helper: "' . $helperName . '"');
            }
        }

        // Handle literal path in block position (e.g. {{#"foo"}}, {{#12}}, {{#true}})
        if ($block->path instanceof Literal) {
            $literalKey = $this->getLiteralKeyName($block->path);

            if ($this->resolveHelper($literalKey)) {
                return $this->compileBlockHelper($block, $literalKey);
            }

            $escapedKey = $this->escape($literalKey);
            $miss = $this->missValue($literalKey);
            $var = "\$in['$escapedKey'] ?? $miss";

            if ($block->program === null) {
                // Inverted section: {{^"foo"}}...{{/"foo"}}
                $body = $this->compileProgramOrEmpty($block->inverse);
                return "'.((" . $this->getFuncName('isec', $var) . ")) ? $body : '').'";
            }

            // Regular section: {{#"foo"}}...{{/"foo"}}
            $body = $this->compileProgram($block->program, true);
            $else = $this->compileElseClause($block);
            return "'." . $this->getFuncName('sec', "\$cx, $var, null, \$in, false, function(\$cx, \$in) use (&\$sp) {return $body;}$else") . ").'";
        }

        // Inverted section: {{^var}}...{{/var}}
        if ($block->program === null) {
            return $this->compileInvertedSection($block);
        }

        // Non-simple path with params: invoke as a dynamic block helper call
        if ($block->params) {
            return $this->compileDynamicBlockHelper($block);
        }

        // Regular section: {{#var}}...{{/var}}
        return $this->compileSection($block);
    }

    private function compileDynamicBlockHelper(BlockStatement $block): string
    {
        $varPath = $this->compileExpression($block->path);
        $bp = $block->program->blockParams;
        $params = $this->compileParams($block->params, $block->hash, $bp ?: null);
        $body = $this->compileProgramWithBlockParams($block->program, $bp);
        $else = $this->compileElseClause($block);

        return "'." . $this->getFuncName('dynhbbch', "\$cx, $varPath, $params, \$in, function(\$cx, \$in) {return $body;}$else") . ").'";
    }

    private function resolveHelper(string $helperName): bool
    {
        if (isset($this->context->helpers[$helperName])) {
            $this->context->usedHelpers[$helperName] = true;
            return true;
        } elseif ($this->context->helperResolver) {
            $helper = ($this->context->helperResolver)($this->context, $helperName);
            if ($helper) {
                $this->context->helpers[$helperName] = $helper;
                $this->context->usedHelpers[$helperName] = true;
                return true;
            }
        }

        return false;
    }

    private function compileIf(BlockStatement $block, bool $unless): string
    {
        if (count($block->params) !== 1) {
            $helper = $unless ? '#unless' : '#if';
            throw new \Exception("$helper requires exactly one argument");
        }

        $var = $this->compileExpression($block->params[0]);
        $includeZero = $this->getIncludeZero($block->hash);

        $then = $this->compileProgramOrEmpty($block->program);

        if ($block->inverse && $block->inverse->chained) {
            // {{else if ...}} chain — compile the inner block directly
            $elseCode = '';
            foreach ($block->inverse->body as $stmt) {
                $elseCode .= $this->accept($stmt);
            }
            $else = "'" . $elseCode . "'";
        } else {
            $else = $this->compileProgramOrEmpty($block->inverse);
        }

        $negate = $unless ? '!' : '';
        return "'.(({$negate}" . $this->getFuncName('ifvar', "LR::dv($var, \$in)") . ", $includeZero)) ? $then : $else).'";
    }

    private function compileEach(BlockStatement $block): string
    {
        if (count($block->params) !== 1) {
            throw new \Exception('Must pass iterator to #each');
        }

        $var = $this->compileExpression($block->params[0]);
        [$bp, $bs] = $this->getProgramBlockParams($block->program);

        $body = $block->program ? $this->compileProgramWithBlockParams($block->program, $bp, true) : "''";
        $else = $this->compileElseClause($block);

        return "'." . $this->getFuncName('sec', "\$cx, LR::dv($var, \$in), $bs, \$in, true, function(\$cx, \$in) use (&\$sp) {return $body;}$else") . ").'";
    }

    private function compileWith(BlockStatement $block): string
    {
        if (count($block->params) !== 1) {
            throw new \Exception('#with requires exactly one argument');
        }

        $var = $this->compileExpression($block->params[0]);
        [$bp, $bs] = $this->getProgramBlockParams($block->program);

        $body = $this->compileProgramOrEmpty($block->program);
        $else = $this->compileElseClause($block);

        return "'." . $this->getFuncName('wi', "\$cx, LR::dv($var, \$in), $bs, \$in, function(\$cx, \$in) {return $body;}$else") . ").'";
    }

    private function compileSection(BlockStatement $block): string
    {
        $var = $this->compileExpression($block->path);

        $body = $this->compileProgramOrEmpty($block->program, true);
        $else = $this->compileElseClause($block);

        return "'." . $this->getFuncName('sec', "\$cx, $var, null, \$in, false, function(\$cx, \$in) use (&\$sp) {return $body;}$else") . ").'";
    }

    private function compileInvertedSection(BlockStatement $block): string
    {
        $var = $this->compileExpression($block->path);
        $body = $this->compileProgramOrEmpty($block->inverse);

        return "'.((" . $this->getFuncName('isec', $var) . ")) ? $body : '').'";
    }

    private function compileBlockHelper(BlockStatement $block, string $helperName): string
    {
        $inverted = $block->program === null;

        $bp = [];
        if ($block->program) {
            $bp = $block->program->blockParams;
        } elseif ($block->inverse) {
            $bp = $block->inverse->blockParams;
        }

        $params = $this->compileParams($block->params, $block->hash, $bp ?: null);

        if ($inverted) {
            $body = $this->compileProgramOrEmpty($block->inverse);
            return "'." . $this->getFuncName('hbbch', "\$cx, '$helperName', $params, \$in, true, function(\$cx, \$in) {return $body;}") . ").'";
        }

        $body = $this->compileProgramWithBlockParams($block->program, $bp);
        $else = $this->compileElseClause($block);

        return "'." . $this->getFuncName('hbbch', "\$cx, '$helperName', $params, \$in, false, function(\$cx, \$in) {return $body;}$else") . ").'";
    }

    private function DecoratorBlock(BlockStatement $block): string
    {
        $helperName = $this->getSimpleHelperName($block->path);

        if ($helperName !== 'inline') {
            throw new \Exception('Unknown decorator: "' . $helperName . '"');
        } elseif (!$block->params) {
            $partialName = 'undefined';
        } else {
            $firstArg = $block->params[0];
            if (!$firstArg instanceof Literal) {
                throw new \Exception("Unexpected inline partial argument type: {$firstArg->type}");
            }
            $partialName = $this->getLiteralKeyName($firstArg);
        }

        $body = $this->compileProgramOrEmpty($block->program, true);

        // Register in usedPartial so {{> partialName}} can compile without error.
        // Do NOT add to partialCode - `in()` handles runtime registration, keeping inline partials block-scoped.
        $this->context->usedPartial[$partialName] = '';

        return "'." . $this->getFuncName('in', "\$cx, '" . $this->escape($partialName) . "', function(\$cx, \$in, \$sp) {return $body;}") . ").'";
    }

    private function Decorator(Decorator $decorator): never
    {
        throw new \Exception('Decorator has not been implemented');
    }

    private function PartialStatement(PartialStatement $statement): string
    {
        $name = $statement->name;

        if ($name instanceof PathExpression) {
            $p = "'" . $this->escape($name->original) . "'";
            $this->resolveAndCompilePartial($name->original);
        } elseif ($name instanceof SubExpression) {
            $p = $this->SubExpression($name);
            $this->context->usedDynPartial++;
        } elseif ($name instanceof NumberLiteral || $name instanceof StringLiteral) {
            $literalName = $this->getLiteralKeyName($name);
            $p = "'" . $this->escape($literalName) . "'";
            $this->resolveAndCompilePartial($literalName);
        } else {
            $p = $this->compileExpression($name);
        }

        $vars = $this->compilePartialParams($statement->params, $statement->hash);
        $indent = $this->escape($statement->indent);

        // When preventIndent is set, emit the indent as literal content (like handlebars.js
        // appendContent opcode) and invoke the partial with an empty indent so its lines are
        // not additionally indented.
        if ($this->context->options->preventIndent && $indent !== '') {
            return "'.'$indent'." . $this->getFuncName('p', "\$cx, $p, $vars, 0, ''") . ").'";
        }

        return "'." . $this->getFuncName('p', "\$cx, $p, $vars, 0, '$indent'") . ").'";
    }

    private function PartialBlockStatement(PartialBlockStatement $statement): string
    {
        $this->context->partialBlockId++;
        $pid = $this->context->partialBlockId;

        $name = $statement->name;

        // Hoist inline partial registrations so they run before the partial is called.
        // Without this, inline partials defined in the block would only be registered when
        // {{> @partial-block}} is invoked, too late for partials that call them directly.
        $hoisted = '';
        foreach ($statement->program->body as $stmt) {
            if ($stmt instanceof BlockStatement && $stmt->type === 'DecoratorBlock') {
                $hoisted .= $this->accept($stmt);
            }
        }

        $body = $this->compileProgram($statement->program, true);
        $found = false;

        if ($name instanceof PathExpression) {
            $partialName = $name->original;
            $p = "'" . $this->escape($partialName) . "'";
        } elseif ($name instanceof StringLiteral || $name instanceof NumberLiteral) {
            $partialName = $this->getLiteralKeyName($name);
            $p = "'" . $this->escape($partialName) . "'";
        } else {
            $p = $this->compileExpression($name);
            $partialName = null;
        }

        if ($partialName !== null) {
            if (!isset($this->context->usedPartial[$partialName])
                && !str_starts_with($partialName, '@partial-block')
            ) {
                $resolveName = $partialName;
                $cnt = $this->resolvePartial($resolveName);
                if ($cnt !== null) {
                    $this->context->usedPartial[$resolveName] = $cnt;
                    $this->compilePartialTemplate($resolveName, $cnt);
                    $found = true;
                }
            } else {
                $found = isset($this->context->usedPartial[$partialName]);
            }

            if (!$found) {
                // Register fallback body as the partial
                $func = "function (\$cx, \$in, \$sp) {return $body;}";
                $this->context->usedPartial[$partialName] = '';
                $this->context->partialCode[$partialName] = Expression::quoteString($partialName) . " => $func";
            }
        }

        $vars = $this->compilePartialParams($statement->params, $statement->hash);
        $sp = "''";

        return $hoisted
            . "'."
            . $this->getFuncName('in', "\$cx, '@partial-block$pid', function(\$cx, \$in, \$sp) {return $body;}") . ")."
            . $this->getFuncName('p', "\$cx, $p, $vars, $pid, $sp") . ").'";
    }

    private function MustacheStatement(MustacheStatement $mustache): string
    {
        $raw = !$mustache->escaped || $this->context->options->noEscape;
        $fn = $raw ? 'raw' : 'encq';
        $path = $mustache->path;

        if ($path instanceof PathExpression) {
            $helperName = $this->getSimpleHelperName($path);

            // Registered helper
            if ($helperName !== null && $this->resolveHelper($helperName)) {
                $params = $this->compileParams($mustache->params, $mustache->hash);
                $call = "LR::hbch(\$cx, '$helperName', $params, \$in)";
                return "'." . $this->getFuncName($fn, $call) . ").'";
            }

            // Built-in: lookup
            if ($helperName === 'lookup') {
                return $this->compileLookup($mustache, $raw);
            }

            // Built-in: log
            if ($helperName === 'log') {
                return $this->compileLog($mustache);
            }

            if (count($mustache->params) !== 0) {
                // Non-simple path with params (data var or pathed expression): invoke via dv()
                if ($helperName === null) {
                    $varPath = $this->PathExpression($path);
                    $args = array_map(fn($p) => $this->compileExpression($p), $mustache->params);
                    $call = 'LR::dv(' . $varPath . ', ' . implode(', ', $args) . ')';
                    return "'." . $this->getFuncName($fn, $call) . ").'";
                }
                throw new \Exception('Missing helper: "' . $helperName . '"');
            }

            // Plain variable; wrap data vars in dv() to support callables
            $varPath = $this->PathExpression($path);
            if ($path->data) {
                return "'." . $this->getFuncName($fn, "LR::dv($varPath)") . ").'";
            }
            return "'." . $this->getFuncName($fn, $varPath) . ").'";
        }

        // Literal path — treat as named context lookup or helper call
        $literalKey = $this->getLiteralKeyName($path);

        if ($this->resolveHelper($literalKey)) {
            $params = $this->compileParams($mustache->params, $mustache->hash);
            $escapedKey = $this->escape($literalKey);
            $call = "LR::hbch(\$cx, '$escapedKey', $params, \$in)";
            return "'." . $this->getFuncName($fn, $call) . ").'";
        }

        if (count($mustache->params) !== 0) {
            throw new \Exception('Missing helper: "' . $literalKey . '"');
        }

        $escapedKey = $this->escape($literalKey);
        $miss = $this->missValue($literalKey);
        $val = "\$in['$escapedKey'] ?? $miss";
        return "'." . $this->getFuncName($fn, $val) . ").'";
    }

    private function ContentStatement(ContentStatement $statement): string
    {
        return $this->escape($statement->value);
    }

    private function CommentStatement(CommentStatement $statement): string
    {
        return '';
    }

    // ── Expressions ─────────────────────────────────────────────────

    private function SubExpression(SubExpression $expression): string
    {
        $path = $expression->path;
        $helperName = null;

        if ($path instanceof PathExpression) {
            $helperName = $this->getSimpleHelperName($path);
        } elseif ($path instanceof Literal) {
            $helperName = $this->getLiteralKeyName($path);
        }

        // Registered helper
        if ($helperName !== null && $this->resolveHelper($helperName)) {
            $params = $this->compileParams($expression->params, $expression->hash);
            $escapedName = $this->escape($helperName);
            return "LR::hbch(\$cx, '$escapedName', $params, \$in)";
        }

        // Built-in: lookup (as subexpression)
        if ($helperName === 'lookup') {
            return $this->compileLookupExpr($expression->params);
        }

        if ($helperName !== null) {
            throw new \Exception('Missing helper: "' . $helperName . '"');
        }

        throw new \Exception('Sub-expression must be a helper call');
    }

    private function PathExpression(PathExpression $expression): string
    {
        $data = $expression->data;
        $depth = $expression->depth;
        $parts = $expression->parts;

        $base = $this->buildBasePath($data, $depth);

        // Filter out SubExpression parts for string-only operations
        $stringParts = self::stringPartsOf($parts);

        // `this` with no parts or empty parts
        if (($expression->this_ && !$parts) || !$stringParts) {
            return $base;
        }

        $miss = $this->missValue($expression->original);

        // @partial-block as variable: truthy when an active partial block exists
        if ($data && $depth === 0 && count($stringParts) === 1 && $stringParts[0] === 'partial-block') {
            return "isset(\$cx->partials['@partial-block' . \$cx->partialId]) ? true : null";
        }

        // Check block params (depth-0, non-data, non-scoped paths only)
        if (!$data && $depth === 0 && !self::scopedId($expression)) {
            $bpIdx = $this->lookupBlockParam($stringParts[0]);
            if ($bpIdx !== null) {
                $escapedName = $this->escape($stringParts[0]);
                $bpBase = "\$cx->blParam[$bpIdx]['$escapedName']";
                $remaining = $this->buildKeyAccess(array_slice($stringParts, 1));
                return "$bpBase$remaining ?? $miss";
            }
        }

        // Build array access string
        $n = $this->buildKeyAccess($stringParts);

        // Handle .length special case
        $lastPart = end($stringParts);
        if ($lastPart === 'length') {
            $varParts = array_slice($stringParts, 0, -1);
            $p = $this->buildKeyAccess($varParts);

            $checks = [];
            if ($depth > 0) {
                $checks[] = "isset($base)";
            }
            if ($p !== '' && $depth === 0) {
                $checks[] = "isset($base$p)";
            }
            $baseP = "$base$p";
            $checks[] = $baseP === '$in' ? '$inary' : "is_array($base$p)";

            $cond = implode(' && ', $checks);
            if (count($checks) > 1) {
                $cond = "($cond)";
            }
            $lenStart = "($cond ? count($base$p) : ";
            $lenEnd = ')';

            return "$base$n ?? $lenStart$miss$lenEnd";
        }

        return "$base$n ?? $miss";
    }

    private function StringLiteral(StringLiteral $literal): string
    {
        return "'" . $this->escape($literal->value) . "'";
    }

    private function NumberLiteral(NumberLiteral $literal): string
    {
        return (string) $literal->value;
    }

    private function BooleanLiteral(BooleanLiteral $literal): string
    {
        return $literal->value ? 'true' : 'false';
    }

    private function UndefinedLiteral(UndefinedLiteral $literal): string
    {
        return 'null';
    }

    private function NullLiteral(NullLiteral $literal): string
    {
        return 'null';
    }

    /**
     * Get the string key name for a literal used in path (mustache/block) position.
     * e.g. {{12}} looks up $in['12'], {{"foo bar"}} looks up $in['foo bar'], {{true}} looks up $in['true'].
     */
    private function getLiteralKeyName(Literal $literal): string
    {
        return match (true) {
            $literal instanceof StringLiteral => $literal->value,
            $literal instanceof NumberLiteral => (string) $literal->value,
            $literal instanceof BooleanLiteral => $literal->value ? 'true' : 'false',
            $literal instanceof UndefinedLiteral => 'undefined',
            $literal instanceof NullLiteral => 'null',
            default => throw new \Exception('Unknown literal type: ' . (new \ReflectionClass($literal))->getShortName()),
        };
    }

    /**
     * Find the $cx->blParam index for a block param name, or null if not a block param.
     * Iterates blockParamValues from innermost to outermost; only non-empty levels
     * increment the runtime blParam index.
     */
    private function lookupBlockParam(string $name): ?int
    {
        $blParamIndex = 0;
        foreach ($this->blockParamValues as $levelParams) {
            if (in_array($name, $levelParams, true)) {
                return $blParamIndex;
            }
            if ($levelParams) {
                $blParamIndex++;
            }
        }
        return null;
    }

    private function Hash(Hash $hash): string
    {
        $pairs = [];
        foreach ($hash->pairs as $pair) {
            $key = $this->escape($pair->key);
            $value = $this->compileExpression($pair->value);
            $pairs[] = "'$key'=>$value";
        }
        return implode(',', $pairs);
    }

    // ── Partials ─────────────────────────────────────────────────────

    private function resolveAndCompilePartial(string $name): void
    {
        if (isset($this->context->usedPartial[$name])) {
            return;
        }

        // @partial-block is resolved at runtime via LR::in()/LR::p()
        if (str_starts_with($name, '@partial-block')) {
            return;
        }

        $cnt = $this->resolvePartial($name);

        if ($cnt !== null) {
            $this->context->usedPartial[$name] = $cnt;
            $this->compilePartialTemplate($name, $cnt);
            return;
        }

        throw new \Exception("The partial $name could not be found");
    }

    /**
     * Returns the resolved partial content, or null if it doesn't exist.
     */
    private function resolvePartial(string &$name): ?string
    {
        if ($name === '@partial-block') {
            $name = "@partial-block{$this->context->usedPBlock}";
        }
        if (isset($this->context->partials[$name])) {
            return $this->context->partials[$name];
        }
        if ($this->context->partialResolver) {
            return ($this->context->partialResolver)($this->context, $name);
        }
        return null;
    }

    private function compilePartialTemplate(string $name, string $template): void
    {
        if (isset($this->context->partialCode[$name])) {
            return;
        }

        // Prevent infinite recursion
        if (end($this->context->partialStack) === $name && str_starts_with($name, '@partial-block')) {
            return;
        }

        $tmpContext = clone $this->context;
        $tmpContext->inlinePartial = [];
        $tmpContext->partialBlock = [];
        $tmpContext->partialStack[] = $name;

        $program = $this->parser->parse($template);
        $code = (new Compiler($this->parser))->compile($program, $tmpContext);
        $this->context->merge($tmpContext);

        $func = "function (\$cx, \$in, \$sp) {return '$code';}";
        $this->context->partialCode[$name] = Expression::quoteString($name) . " => $func";
    }

    public function handleDynamicPartials(): void
    {
        if ($this->context->usedDynPartial === 0) {
            return;
        }

        foreach ($this->context->partials as $name => $code) {
            $this->resolveAndCompilePartial($name);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Build the [[positional],[named]] or [[positional],[named],[blockParams]] param format.
     *
     * @param AstExpression[] $params
     * @param string[]|null $blockParams
     */
    private function compileParams(array $params, ?Hash $hash, ?array $blockParams = null): string
    {
        $positional = [];
        foreach ($params as $param) {
            $positional[] = $this->compileExpression($param);
        }

        $named = $hash ? $this->Hash($hash) : '';

        $bp = $blockParams ? ',' . Expression::listString($blockParams) : '';
        return '[[' . implode(',', $positional) . '],[' . $named . "]$bp]";
    }

    /**
     * Build params for partial calls: [[$context],[named]].
     *
     * @param AstExpression[] $params
     */
    private function compilePartialParams(array $params, ?Hash $hash): string
    {
        if (!$params) {
            $contextVar = $this->context->options->explicitPartialContext ? 'null' : '$in';
        } else {
            $contextVar = $this->compileExpression($params[0]);
        }

        $named = $hash ? $this->Hash($hash) : '';

        return "[[$contextVar],[$named]]";
    }

    /**
     * Equivalent to AST.helpers.scopedId in Handlebars.js.
     * A path is scoped when it starts with `.` (e.g. `./value`) or `this`,
     * meaning it is an explicit context lookup that bypasses helpers and block params.
     */
    private static function scopedId(PathExpression $path): bool
    {
        return (bool) preg_match('/^\.|this\b/', $path->original);
    }

    /**
     * Extract simple helper name from a path if it's a single-segment, non-data, depth-0 path.
     */
    private function getSimpleHelperName(PathExpression|Literal $path): ?string
    {
        if (!$path instanceof PathExpression) {
            return null;
        }

        if ($path->data || $path->depth > 0 || self::scopedId($path)) {
            return null;
        }

        if (count($path->parts) !== 1) {
            return null;
        }

        if (!is_string($path->parts[0])) {
            return null;
        }

        return $path->parts[0];
    }

    /**
     * Compile the else/inverse clause of a block as a trailing closure argument, or '' if absent.
     */
    private function compileElseClause(BlockStatement $block): string
    {
        return $block->inverse
            ? ", function(\$cx, \$in) {return " . $this->compileProgram($block->inverse) . ";}"
            : '';
    }

    /**
     * Compile a block program, pushing/popping block params around the compilation.
     * @param string[] $bp
     */
    private function compileProgramWithBlockParams(Program $program, array $bp, bool $withSp = false): string
    {
        if ($bp) {
            array_unshift($this->blockParamValues, $bp);
        }
        $body = $this->compileProgram($program, $withSp);
        if ($bp) {
            array_shift($this->blockParamValues);
        }
        return $body;
    }

    /**
     * Build the base path expression for a given data flag and depth.
     */
    private function buildBasePath(bool $data, int $depth): string
    {
        $base = $data ? '$cx->spVars' : '$in';
        if ($depth > 0) {
            $base = $data
                ? $base . str_repeat("['_parent']", $depth)
                : "\$cx->scopes[count(\$cx->scopes)-$depth]";
        }
        return $base;
    }

    /**
     * Build a chained array-access string for the given path parts.
     * e.g. ['foo', 'bar'] → "['foo']['bar']"
     * @param string[] $parts
     */
    private function buildKeyAccess(array $parts): string
    {
        $n = '';
        foreach ($parts as $part) {
            $n .= "['" . $this->escape($part) . "']";
        }
        return $n;
    }

    /**
     * Build function call with optional strict-mode debug wrapping.
     */
    private function getFuncName(string $name, string $args): string
    {
        if ($this->context->options->strict && $name !== 'miss') {
            $tag = '';
            return "LR::debug('$tag', '$name', $args";
        }

        return "LR::$name($args";
    }

    private function escape(string $s): string
    {
        return addcslashes($s, "'\\");
    }

    private function missValue(string $key): string
    {
        return $this->context->options->strict
            ? "LR::miss('" . $this->escape($key) . "')"
            : 'null';
    }

    /** @return array{list<string>, string} [$bp, $bs] */
    private function getProgramBlockParams(?Program $program): array
    {
        $bp = $program ? $program->blockParams : [];
        $bs = $bp ? Expression::listString($bp) : 'null';
        return [$bp, $bs];
    }

    private function compileProgramOrEmpty(?Program $program, bool $withSp = false): string
    {
        return $program ? $this->compileProgram($program, $withSp) : "''";
    }

    /**
     * Return only the string parts of a mixed parts array, re-indexed.
     * @param list<string|SubExpression> $parts
     * @return list<string>
     */
    private static function stringPartsOf(array $parts): array
    {
        return array_values(array_filter($parts, fn($p) => is_string($p)));
    }

    /**
     * Get includeZero value from hash.
     */
    private function getIncludeZero(?Hash $hash): string
    {
        if ($hash) {
            foreach ($hash->pairs as $pair) {
                if ($pair->key === 'includeZero') {
                    return $this->compileExpression($pair->value);
                }
            }
        }
        return 'false';
    }

    /**
     * Compile {{lookup items idx}} in mustache context.
     */
    private function compileLookup(MustacheStatement $mustache, bool $raw): string
    {
        $fn = $raw ? 'raw' : 'encq';

        if (count($mustache->params) !== 2) {
            throw new \Exception('{{lookup}} requires 2 arguments');
        }

        $itemsExpr = $mustache->params[0];
        $idxExpr = $mustache->params[1];
        $varCode = $this->getWithLookup($itemsExpr, $idxExpr);

        return "'." . $this->getFuncName($fn, $varCode) . ").'";
    }

    /**
     * Compile lookup as a sub-expression argument.
     *
     * @param AstExpression[] $params
     */
    private function compileLookupExpr(array $params): string
    {
        $itemsExpr = $params[0];
        $idxExpr = $params[1];
        $varCode = $this->getWithLookup($itemsExpr, $idxExpr);

        return "LR::raw($varCode, 1)";
    }

    /**
     * Compile a path with an additional dynamic lookup segment.
     */
    private function compilePathWithLookup(PathExpression $path, string $lookupCode): string
    {
        $data = $path->data;
        $depth = $path->depth;
        $parts = self::stringPartsOf($path->parts);

        $base = $this->buildBasePath($data, $depth);
        $n = $this->buildKeyAccess($parts);

        $miss = $this->missValue($path->original);

        return $base . $n . "[$lookupCode] ?? $miss";
    }

    /**
     * Compile {{log ...}} built-in.
     */
    private function compileLog(MustacheStatement $mustache): string
    {
        $params = $this->compileParams($mustache->params, $mustache->hash);
        return "'." . $this->getFuncName('lo', $params) . ").'";
    }

    private function getWithLookup(AstExpression $itemsExpr, AstExpression $idxExpr): string
    {
        $idxCode = $this->compileExpression($idxExpr);

        if ($itemsExpr instanceof PathExpression) {
            $varCode = $this->compilePathWithLookup($itemsExpr, $idxCode);
        } else {
            $itemsCode = $this->compileExpression($itemsExpr);
            $miss = $this->missValue('lookup');
            $varCode = $itemsCode . "[$idxCode] ?? $miss";
        }
        return $varCode;
    }
}
