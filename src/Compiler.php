<?php

namespace DevTheorem\Handlebars;

use DevTheorem\HandlebarsParser\Ast\BlockStatement;
use DevTheorem\HandlebarsParser\Ast\BooleanLiteral;
use DevTheorem\HandlebarsParser\Ast\CommentStatement;
use DevTheorem\HandlebarsParser\Ast\ContentStatement;
use DevTheorem\HandlebarsParser\Ast\Decorator;
use DevTheorem\HandlebarsParser\Ast\Expression;
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
     * Populated for any block that declares block params (e.g. {{#each items as |item i|}}).
     * @var list<string[]>
     */
    private array $blockParamValues = [];

    /**
     * True while compiling helper params/hash values.
     * In strict mode, helper arguments may be undefined without throwing.
     */
    private bool $compilingHelperArgs = false;

    public function __construct(
        private readonly Parser $parser,
    ) {}

    public function compile(Program $program, Context $context): string
    {
        $this->context = $context;
        $this->blockParamValues = [];
        return $this->compileProgram($program);
    }

    /**
     * Compile Handlebars template to PHP function.
     *
     * @param string $code generated PHP code
     */
    public function composePHPRender(string $code): string
    {
        $runtime = Runtime::class;
        $partials = implode(",\n", $this->context->partialCode);
        $closure = self::templateClosure($code, $partials, "\n \$in = &\$cx->data['root'];");
        return "use {$runtime} as LR;\nreturn $closure;";
    }

    /**
     * Build a partial closure string: a Template-format closure that calls createContext
     * with empty compiled partials, inheriting context from $partialContext.
     * @param string $code PHP expression to return (e.g. the result of compileProgram())
     */
    private static function templateClosure(string $code, string $partials = '', string $stmts = ''): string
    {
        return <<<PHP
            function (mixed \$in = null, array \$options = []) {
             \$cx = LR::createContext(\$in, \$options, [$partials]);$stmts
             return $code;
            }
            PHP;
    }

    private function compileProgram(Program $program): string
    {
        $code = "'";
        foreach ($program->body as $statement) {
            $code .= $this->accept($statement);
        }
        return $code . "'";
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

    private function compileExpression(Expression $expr): string
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
            if ($this->isKnownHelper($helperName)) {
                return $this->compileBlockHelper($block, $helperName);
            }

            if ($block->params || $block->hash !== null) {
                if ($this->context->options->knownHelpersOnly) {
                    $this->throwKnownHelpersOnly($helperName);
                }
                return $this->compileDynBlockHelper($block, $helperName);
            }
        }

        // Handle literal path in block position (e.g. {{#"foo"}}, {{#12}}, {{#true}})
        if ($block->path instanceof Literal) {
            $literalKey = $this->getLiteralKeyName($block->path);

            if ($this->isKnownHelper($literalKey)) {
                return $this->compileBlockHelper($block, $literalKey);
            }

            $escapedKey = self::quote($literalKey);
            $miss = $this->missValue($literalKey);
            $var = "\$in[$escapedKey] ?? $miss";

            if ($block->program === null) {
                // Inverted section: {{^"foo"}}...{{/"foo"}}
                $body = $this->compileProgramOrEmpty($block->inverse);
                return "'.(" . self::getRuntimeFunc('isec', $var) . " ? $body : '').'";
            }

            // Regular section: {{#"foo"}}...{{/"foo"}}
            $body = $this->compileProgram($block->program);
            $else = $this->compileElseClause($block);
            $helperArg = !$this->context->options->knownHelpersOnly ? ", $escapedKey" : '';
            $blockFn = self::blockClosure($body);
            return self::concatRuntimeFunc('sec', "\$cx, $var, \$in, $blockFn, $else$helperArg");
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
        if (!$block->program) {
            throw new \Exception('Dynamic block program must not be empty');
        }
        $varPath = $this->compileExpression($block->path);
        $bp = $block->program->blockParams;
        $params = $this->compileParams($block->params, $block->hash, $bp);
        $body = $this->compileProgramWithBlockParams($block->program, $bp);
        $else = $this->compileElseClause($block);
        $name = self::quote((string) $block->path->original);
        $blockFn = self::blockClosure($body);
        return self::concatRuntimeFunc('dynhbbch', "\$cx, $name, $varPath, $params, \$in, $blockFn, $else");
    }

    private function isKnownHelper(string $helperName): bool
    {
        return $this->context->options->knownHelpers[$helperName] ?? false;
    }

    private function compileSection(BlockStatement $block): string
    {
        $var = $this->compileExpression($block->path);
        $escapedName = $block->path instanceof PathExpression ? self::quote($block->path->original) : 'null';

        $bp = $block->program ? $block->program->blockParams : [];
        $body = $bp
            ? $this->compileProgramWithBlockParams($block->program, $bp)
            : $this->compileProgramOrEmpty($block->program);
        $else = $this->compileElseClause($block);
        $blockFn = self::blockClosure($body);

        if ($this->context->options->knownHelpersOnly) {
            return self::concatRuntimeFunc('sec', "\$cx, $var, \$in, $blockFn, $else");
        }

        if ($block->hash !== null || $bp) {
            $params = $this->compileParams([], $block->hash, $bp);
            return self::concatRuntimeFunc('dynhbbch', "\$cx, $escapedName, $var, $params, \$in, $blockFn, $else");
        }

        return self::concatRuntimeFunc('sec', "\$cx, $var, \$in, $blockFn, $else, $escapedName");
    }

    private function compileInvertedSection(BlockStatement $block): string
    {
        $var = $this->compileExpression($block->path);
        $body = $this->compileProgramOrEmpty($block->inverse);

        return "'.(" . self::getRuntimeFunc('isec', $var) . " ? $body : '').'";
    }

    private function compileBlockHelper(BlockStatement $block, string $helperName): string
    {
        $bp = $block->program->blockParams ?? $block->inverse->blockParams ?? [];
        $params = $this->compileParams($block->params, $block->hash, $bp);

        if ($block->program === null) {
            // inverted block: pass null for $fn and the body as $else
            $body = $this->compileProgramOrEmpty($block->inverse);
            $blockFn = self::blockClosure($body);
            return self::concatRuntimeFunc('hbbch', "\$cx, '$helperName', $params, \$in, null, $blockFn");
        }

        $body = $this->compileProgramWithBlockParams($block->program, $bp);
        $else = $this->compileElseClause($block);
        $blockFn = self::blockClosure($body);

        return self::concatRuntimeFunc('hbbch', "\$cx, '$helperName', $params, \$in, $blockFn, $else");
    }

    private function compileDynBlockHelper(BlockStatement $block, string $helperName): string
    {
        $bp = ($block->program ?? $block->inverse)->blockParams ?? [];
        $params = $this->compileParams($block->params, $block->hash, $bp);
        $body = $block->program !== null
            ? $this->compileProgramWithBlockParams($block->program, $bp)
            : $this->compileProgramOrEmpty(null);
        $else = $this->compileElseClause($block);
        $blockFn = self::blockClosure($body);
        return self::concatRuntimeFunc('dynhbbch', "\$cx, '$helperName', null, $params, \$in, $blockFn, $else");
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

        $body = $this->compileProgramOrEmpty($block->program);

        // Register in usedPartial so {{> partialName}} can compile without error.
        // Do NOT add to partialCode - `in()` handles runtime registration, keeping inline partials block-scoped.
        $this->context->usedPartial[$partialName] = '';

        return self::concatRuntimeFunc('in', "\$cx, " . self::quote($partialName) . ", " . self::templateClosure($body));
    }

    private function Decorator(Decorator $decorator): never
    {
        throw new \Exception('Decorator has not been implemented');
    }

    private function PartialStatement(PartialStatement $statement): string
    {
        $name = $statement->name;

        if ($name instanceof PathExpression) {
            $p = self::quote($name->original);
            $this->resolveAndCompilePartial($name->original);
        } elseif ($name instanceof SubExpression) {
            $p = $this->SubExpression($name);
            $this->context->usedDynPartial++;
        } elseif ($name instanceof NumberLiteral || $name instanceof StringLiteral) {
            $literalName = $this->getLiteralKeyName($name);
            $p = self::quote($literalName);
            $this->resolveAndCompilePartial($literalName);
        } else {
            $p = $this->compileExpression($name);
        }

        $vars = $this->compilePartialParams($statement->params, $statement->hash);
        $indent = self::quote($statement->indent);

        // When preventIndent is set, emit the indent as literal content (like handlebars.js
        // appendContent opcode) and invoke the partial with an empty indent so its lines are
        // not additionally indented.
        if ($this->context->options->preventIndent && $statement->indent !== '') {
            return "'.$indent." . self::getRuntimeFunc('p', "\$cx, $p, $vars, 0, ''") . ".'";
        }

        return self::concatRuntimeFunc('p', "\$cx, $p, $vars, 0, $indent");
    }

    private function PartialBlockStatement(PartialBlockStatement $statement): string
    {
        $this->context->partialBlockId++;
        $pid = $this->context->partialBlockId;

        // Hoist inline partial registrations so they run before the partial is called.
        // Without this, inline partials defined in the block would only be registered when
        // {{> @partial-block}} is invoked, too late for partials that call them directly.
        $hoisted = '';
        foreach ($statement->program->body as $stmt) {
            if ($stmt instanceof BlockStatement && $stmt->type === 'DecoratorBlock') {
                $hoisted .= $this->accept($stmt);
            }
        }

        $name = $statement->name;
        $body = $this->compileProgram($statement->program);

        if ($name instanceof PathExpression) {
            $partialName = $name->original;
            $p = self::quote($partialName);
        } elseif ($name instanceof StringLiteral || $name instanceof NumberLiteral) {
            $partialName = $this->getLiteralKeyName($name);
            $p = self::quote($partialName);
        } else {
            $p = $this->compileExpression($name);
            $partialName = null;
        }

        if ($partialName !== null) {
            $found = isset($this->context->usedPartial[$partialName]);

            if (!$found && !str_starts_with($partialName, '@partial-block')) {
                $resolveName = $partialName;
                $cnt = $this->resolvePartial($resolveName);
                if ($cnt !== null) {
                    $this->context->usedPartial[$resolveName] = $cnt;
                    $this->compilePartialTemplate($resolveName, $cnt);
                    $found = true;
                }
            }

            if (!$found) {
                // Register fallback body as the partial (Template format, same as compilePartialTemplate).
                $func = self::templateClosure($body);
                $this->context->usedPartial[$partialName] = '';
                $this->context->partialCode[$partialName] = self::quote($partialName) . " => $func";
            }
        }

        $vars = $this->compilePartialParams($statement->params, $statement->hash);

        return $hoisted
            . "'."
            . self::getRuntimeFunc('in', "\$cx, '@partial-block$pid', " . self::templateClosure($body)) . "."
            . self::getRuntimeFunc('p', "\$cx, $p, $vars, $pid, ''") . ".'";
    }

    private function MustacheStatement(MustacheStatement $mustache): string
    {
        $raw = !$mustache->escaped || $this->context->options->noEscape;
        $fn = $raw ? 'raw' : 'encq';
        $path = $mustache->path;

        if ($path instanceof PathExpression) {
            $helperName = $this->getSimpleHelperName($path);

            if ($helperName !== null && $this->isKnownHelper($helperName)) {
                $call = $this->buildInlineHelperCall('hbch', $helperName, $mustache->params, $mustache->hash);
                return self::concatRuntimeFunc($fn, $call);
            }

            if ($mustache->params || $mustache->hash !== null) {
                // Non-simple path with params (data var or pathed expression): invoke via dv()
                if ($helperName === null) {
                    $varPath = $this->PathExpression($path);
                    $args = array_map(fn($p) => $this->compileExpression($p), $mustache->params);
                    $call = self::getRuntimeFunc('dv', "$varPath, " . implode(', ', $args));
                    return self::concatRuntimeFunc($fn, $call);
                }
                if ($this->context->options->knownHelpersOnly) {
                    $this->throwKnownHelpersOnly($helperName);
                }
                $call = $this->buildInlineHelperCall('dynhbch', $helperName, $mustache->params, $mustache->hash);
                return self::concatRuntimeFunc($fn, $call);
            }

            // When not strict/assumeObjects, check runtime helpers for bare identifiers.
            // This applies even with knownHelpersOnly so that runtime-registered helpers work.
            if ($helperName !== null && !$this->context->options->strict && !$this->context->options->assumeObjects) {
                $bpIdx = $this->lookupBlockParam($helperName);
                if ($bpIdx === null) {
                    $escapedKey = self::quote($helperName);
                    $call = self::getRuntimeFunc('hv', "\$cx, $escapedKey, \$in");
                    return self::concatRuntimeFunc($fn, $call);
                }
            }

            // Plain variable; wrap in dv() to support lambda context values
            $varPath = $this->PathExpression($path);
            return self::concatRuntimeFunc($fn, self::getRuntimeFunc('dv', $varPath));
        }

        // Literal path — treat as named context lookup or helper call
        $literalKey = $this->getLiteralKeyName($path);

        if ($this->isKnownHelper($literalKey)) {
            $call = $this->buildInlineHelperCall('hbch', $literalKey, $mustache->params, $mustache->hash);
            return self::concatRuntimeFunc($fn, $call);
        }

        if ($mustache->params || $mustache->hash !== null) {
            if ($this->context->options->knownHelpersOnly) {
                $this->throwKnownHelpersOnly($literalKey);
            }
            $call = $this->buildInlineHelperCall('dynhbch', $literalKey, $mustache->params, $mustache->hash);
            return self::concatRuntimeFunc($fn, $call);
        }

        $escapedKey = self::quote($literalKey);

        if (!$this->context->options->strict && !$this->context->options->knownHelpersOnly) {
            return self::concatRuntimeFunc($fn, self::getRuntimeFunc('hv', "\$cx, $escapedKey, \$in"));
        }

        $miss = $this->missValue($literalKey);
        return self::concatRuntimeFunc($fn, "\$in[$escapedKey] ?? $miss");
    }

    private function ContentStatement(ContentStatement $statement): string
    {
        return self::escape($statement->value);
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

        if ($helperName === null) {
            throw new \Exception('Sub-expression must be a helper call');
        }

        if ($this->isKnownHelper($helperName)) {
            return $this->buildInlineHelperCall('hbch', $helperName, $expression->params, $expression->hash);
        }

        if ($this->context->options->knownHelpersOnly) {
            $this->throwKnownHelpersOnly($helperName);
        }

        return $this->buildInlineHelperCall('dynhbch', $helperName, $expression->params, $expression->hash);
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
                $escapedName = self::quote($stringParts[0]);
                $bpBase = "\$cx->blParam[$bpIdx][$escapedName]";
                $remaining = self::buildKeyAccess(array_slice($stringParts, 1));
                return "$bpBase$remaining ?? $miss";
            }
        }

        // Build array access string
        $n = self::buildKeyAccess($stringParts);

        // Handle .length special case
        $lastPart = end($stringParts);
        if ($lastPart === 'length') {
            $varParts = array_slice($stringParts, 0, -1);
            $p = self::buildKeyAccess($varParts);

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

        if ($this->context->options->assumeObjects) {
            $missCode = self::getRuntimeFunc('miss', self::quote($expression->original));
            $conditions = ["isset($base)"];
            $intermediateAccess = '';
            foreach (array_slice($stringParts, 0, -1) as $part) {
                $intermediateAccess .= '[' . self::quote($part) . ']';
                $conditions[] = "isset($base$intermediateAccess)";
            }
            $allConds = implode(' && ', $conditions);
            return "($allConds ? ($base$n ?? null) : $missCode)";
        }

        if ($this->context->options->strict && !$this->compilingHelperArgs) {
            $escapedOriginal = self::quote($expression->original);
            $expr = $base;
            foreach ($stringParts as $part) {
                $expr = self::getRuntimeFunc('strictLookup', "$expr, " . self::quote($part) . ", $escapedOriginal");
            }
            return $expr;
        }

        return "$base$n ?? $miss";
    }

    private function StringLiteral(StringLiteral $literal): string
    {
        return self::quote($literal->value);
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
            $value = $this->compileExpression($pair->value);
            $pairs[] = self::quote($pair->key) . "=>$value";
        }
        return implode(',', $pairs);
    }

    // ── Partials ─────────────────────────────────────────────────────

    private function resolveAndCompilePartial(string $name): void
    {
        if (isset($this->context->usedPartial[$name]) || str_starts_with($name, '@partial-block')) {
            // @partial-block is resolved at runtime via in()/p()
            return;
        }

        $cnt = $this->resolvePartial($name);

        if ($cnt !== null) {
            $this->context->usedPartial[$name] = $cnt;
            $this->compilePartialTemplate($name, $cnt);
            return;
        }

        // Partial not found at compile time; will be resolved at runtime.
        $this->context->usedPartial[$name] = '';
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
        if ($this->context->options->partialResolver) {
            return ($this->context->options->partialResolver)($name);
        }
        return null;
    }

    private function compilePartialTemplate(string $name, string $template): void
    {
        if (isset($this->context->partialCode[$name])) {
            return;
        }

        $tmpContext = clone $this->context;
        $tmpContext->inlinePartial = [];
        $tmpContext->partialBlock = [];

        $program = $this->parser->parse($template);
        $code = (new Compiler($this->parser))->compile($program, $tmpContext);
        $this->context->merge($tmpContext);

        $this->context->partialCode[$name] = self::quote($name) . ' => ' . self::templateClosure($code);
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
     * Build the positional and named param components as separate arguments.
     * Returns '[$a,$b], [hash]' for inline helpers (2 args),
     * or '[$a,$b], [hash], null' / '[$a,$b], [hash], ["bp1"]' for block helpers (3 args).
     *
     * @param Expression[] $params
     * @param string[]|null $blockParams null = inline (omit 3rd arg), array = block (always emit 3rd arg)
     */
    private function compileParams(array $params, ?Hash $hash, ?array $blockParams = null): string
    {
        $savedHelperArgs = $this->compilingHelperArgs;
        $this->compilingHelperArgs = true;

        $positional = [];
        foreach ($params as $param) {
            $positional[] = $this->compileExpression($param);
        }

        $named = $hash ? $this->Hash($hash) : '';
        $this->compilingHelperArgs = $savedHelperArgs;

        $result = '[' . implode(',', $positional) . '], [' . $named . ']';
        if ($blockParams !== null) {
            $result .= ', ' . self::listString($blockParams);
        }
        return $result;
    }

    /**
     * Build params for partial calls: [[$context],[named]].
     *
     * @param Expression[] $params
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
        if (!$path instanceof PathExpression
            || $path->data
            || $path->depth > 0
            || self::scopedId($path)
            || count($path->parts) !== 1
            || !is_string($path->parts[0])
        ) {
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
            ? self::blockClosure($this->compileProgram($block->inverse))
            : 'null';
    }

    /**
     * Compile a block program, pushing/popping block params around the compilation.
     * @param string[] $bp
     */
    private function compileProgramWithBlockParams(Program $program, array $bp): string
    {
        if ($bp) {
            array_unshift($this->blockParamValues, $bp);
        }
        $body = $this->compileProgram($program);
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
        $base = $data ? '$cx->data' : '$in';
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
    private static function buildKeyAccess(array $parts): string
    {
        $n = '';
        foreach ($parts as $part) {
            $n .= '[' . self::quote($part) . ']';
        }
        return $n;
    }

    /**
     * Build runtime function call.
     */
    private static function getRuntimeFunc(string $name, string $args): string
    {
        return "LR::$name($args)";
    }

    private static function concatRuntimeFunc(string $name, string $args): string
    {
        return "'." . self::getRuntimeFunc($name, $args) . ".'";
    }

    private static function blockClosure(string $body): string
    {
        return "function(\$cx, \$in) {return $body;}";
    }

    private static function escape(string $string): string
    {
        return addcslashes($string, "'\\");
    }

    private static function quote(string $string): string
    {
        return "'" . self::escape($string) . "'";
    }

    /**
     * Get string presentation for a string list
     * @param string[] $list
     */
    private static function listString(array $list): string
    {
        return '[' . implode(',', array_map(self::quote(...), $list)) . ']';
    }

    private function missValue(string $key): string
    {
        return ($this->context->options->strict && !$this->compilingHelperArgs)
            ? self::getRuntimeFunc('miss', self::quote($key))
            : 'null';
    }

    private function compileProgramOrEmpty(?Program $program): string
    {
        return $program ? $this->compileProgram($program) : "''";
    }

    private function throwKnownHelpersOnly(string $helperName): never
    {
        throw new \Exception("You specified knownHelpersOnly, but used the unknown helper $helperName");
    }

    /**
     * Build an hbch or dynhbch inline helper call string.
     * @param Expression[] $params
     */
    private function buildInlineHelperCall(string $helperFunc, string $helperName, array $params, ?Hash $hash): string
    {
        $compiledParams = $this->compileParams($params, $hash);
        $escapedName = self::quote($helperName);
        return self::getRuntimeFunc($helperFunc, "\$cx, $escapedName, $compiledParams, \$in");
    }

    /**
     * Return only the string parts of a mixed parts array, re-indexed.
     * @param array<string|SubExpression> $parts
     * @return list<string>
     */
    private static function stringPartsOf(array $parts): array
    {
        return array_values(array_filter($parts, fn($p) => is_string($p)));
    }
}
