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
     * Stack of booleans, one per active compileProgram() call.
     * Each entry is set to true if that invocation directly emitted a $blockParams reference.
     * Used to distinguish direct references from nested closure declarations that merely contain
     * '$blockParams' as a parameter name in the generated string.
     * @var bool[]
     */
    private array $bpRefStack = [];

    /**
     * Set when compiling a program to reflect whether that compilation directly
     * referenced $blockParams (as opposed to nesting a closure that does).
     */
    private bool $lastCompileProgramHadDirectBpRef = false;

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
        $this->bpRefStack = [];
        $this->lastCompileProgramHadDirectBpRef = false;
        return $this->compileProgram($program);
    }

    /**
     * Compile Handlebars template to PHP function.
     *
     * @param string $code generated PHP code
     */
    public function composePHPRender(string $code): string
    {
        $partials = implode(",\n", $this->context->partialCode);
        $closure = self::templateClosure($code, $partials, "\n \$in = &\$cx->data['root'];");
        return "use " . Runtime::class . " as LR;\nreturn $closure;";
    }

    /**
     * Build a partial closure string: a Template-format closure that calls createContext
     * with empty compiled partials, inheriting context from $partialContext.
     * @param string $code PHP expression to return (e.g. the result of compileProgram())
     * @param string $useVars comma-separated variables to capture (e.g. '$blockParams'), or '' for none
     */
    private static function templateClosure(string $code, string $partials = '', string $stmts = '', string $useVars = ''): string
    {
        $use = $useVars !== '' ? " use ($useVars)" : '';
        return <<<PHP
            function (mixed \$in = null, array \$options = [])$use {
             \$cx = LR::createContext(\$in, \$options, [$partials]);
             \$cx->frame['root'] = &\$cx->data['root'];$stmts
             return $code;
            }
            PHP;
    }

    private function compileProgram(Program $program): string
    {
        $this->bpRefStack[] = false;
        $parts = [];
        foreach ($program->body as $statement) {
            $part = $this->accept($statement);
            if ($part !== '' && $part !== "''") {
                $parts[] = $part;
            }
        }
        $this->lastCompileProgramHadDirectBpRef = array_pop($this->bpRefStack);
        return $parts ? implode('.', $parts) : "''";
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
                return $this->compileDynamicBlockHelper($block, $helperName);
            }
        }

        // Handle literal path in block position (e.g. {{#"foo"}}, {{#12}}, {{#true}})
        if ($block->path instanceof Literal) {
            $literalKey = $this->getLiteralKeyName($block->path);

            if ($this->isKnownHelper($literalKey)) {
                return $this->compileBlockHelper($block, $literalKey);
            }

            $escapedKey = self::quote($literalKey);
            $var = "\$in[$escapedKey] ?? " . $this->missValue($literalKey);

            if ($block->program === null) {
                return $this->compileInvertedSection($block, $var, $escapedKey);
            }

            return $this->compileSection($block, $var, $escapedKey);
        }

        $var = $this->compileExpression($block->path);

        // Inverted section: {{^var}}...{{/var}}
        if ($block->program === null) {
            $escapedName = $helperName !== null ? self::quote($helperName) : null;
            return $this->compileInvertedSection($block, $var, $escapedName);
        }

        // Non-simple path with params: invoke as a dynamic block helper call
        if ($block->params) {
            return $this->compileDynamicBlockHelper($block, (string) $block->path->original, $var);
        }

        // Regular section: {{#var}}...{{/var}}
        return $this->compileSection($block, $var, self::quote($block->path->original));
    }

    private function isKnownHelper(string $helperName): bool
    {
        return $this->context->options->knownHelpers[$helperName] ?? false;
    }

    private function compileSection(BlockStatement $block, string $var, string $escapedName): string
    {
        assert($block->program !== null);

        $blockFn = $this->compileProgramWithBlockParams($block->program);
        $else = $this->compileElseClause($block);

        if ($this->context->options->knownHelpersOnly) {
            return self::getRuntimeFunc('sec', "\$cx, $var, \$in, $blockFn, $else");
        }

        $bp = $block->program->blockParams;
        if ($block->hash !== null || $bp) {
            $params = $this->compileParams([], $block->hash);
            $outerBp = $this->outerBlockParamsExpr();
            return self::getRuntimeFunc('dynhbbch', "\$cx, $escapedName, $var, $params, \$in, $blockFn, $else, " . count($bp) . ", $outerBp");
        }

        return self::getRuntimeFunc('sec', "\$cx, $var, \$in, $blockFn, $else, $escapedName");
    }

    private function compileInvertedSection(BlockStatement $block, string $var, ?string $escapedName): string
    {
        assert($block->inverse !== null);

        $blockFn = $this->compileProgramWithBlockParams($block->inverse);
        $name = ($escapedName !== null && !$this->context->options->knownHelpersOnly) ? ", $escapedName" : '';
        return self::getRuntimeFunc('sec', "\$cx, $var, \$in, null, $blockFn$name");
    }

    /** Returns '$blockParams' when inside a block-param scope (for use() capture), '' otherwise. */
    private function blockParamsUseVars(): string
    {
        return $this->blockParamValues ? '$blockParams' : '';
    }

    /**
     * Returns the PHP expression for the outer block param stack at the current compile-time scope.
     * '$blockParams' when inside a bp-declaring block; '[]' otherwise (top-level for this each/helper).
     * When returning '$blockParams', marks the current bpRefStack frame so the enclosing closure
     * captures $blockParams via use(), even if it doesn't directly access block param values.
     */
    private function outerBlockParamsExpr(): string
    {
        if (!$this->blockParamValues) {
            return '[]';
        }
        if ($this->bpRefStack) {
            $this->bpRefStack[array_key_last($this->bpRefStack)] = true;
        }
        return '$blockParams';
    }

    /**
     * Compile a block program, pushing/popping its block params around the compilation.
     * Returns a PHP closure string: the signature varies based on whether the program declares or
     * inherits block params, and a $sc preamble is added when depths are accessed multiple times.
     */
    private function compileProgramWithBlockParams(Program $program): string
    {
        $bp = $program->blockParams;
        if ($bp) {
            array_unshift($this->blockParamValues, $bp);
        }
        $body = $this->compileProgram($program);
        if ($bp) {
            array_shift($this->blockParamValues);
        }

        $declaresBp = (bool) $bp;
        $inheritsBp = $this->lastCompileProgramHadDirectBpRef;
        $preamble = '';
        if (str_contains($body, '$cx->depths[count($cx->depths)-')) {
            $preamble = '$sc=count($cx->depths);';
            $body = str_replace('$cx->depths[count($cx->depths)-', '$cx->depths[$sc-', $body);
        }
        $sig = match (true) {
            $declaresBp => "function(\$cx, \$in, array \$blockParams = [])",
            $inheritsBp => "function(\$cx, \$in) use (\$blockParams)",
            default => "function(\$cx, \$in)",
        };
        return "$sig {{$preamble}return $body;}";
    }

    private function compileBlockHelper(BlockStatement $block, string $name): string
    {
        $inverted = $block->program === null;
        if ($inverted) {
            assert($block->inverse !== null);
        }
        // For inverted blocks the fn body comes from the inverse program; for normal blocks, the program.
        $fnProgram = $inverted ? $block->inverse : $block->program;

        // Inline if/unless as ternary — eliminates hbbch dispatch and HelperOptions allocation.
        // Safe because if/unless don't change scope, so $cx and $in are already correct.
        // Negate for 'unless' in a normal block, or 'if' in an inverted block (swapped semantics).
        if ($this->canInlineConditional($block, $name, $fnProgram->blockParams)) {
            $cond = $this->compileConditionalExpr($block->params[0], $name === ($inverted ? 'if' : 'unless'));
            $body = $this->compileProgram($fnProgram);
            $elseBody = $inverted ? "''" : $this->compileProgramOrEmpty($block->inverse);
            return "($cond ? $body : $elseBody)";
        }

        $blockFn = $this->compileProgramWithBlockParams($fnProgram);
        [$fn, $else] = $inverted
            ? ['null', $blockFn]
            : [$blockFn, $this->compileElseClause($block)];

        $outerBp = $this->outerBlockParamsExpr();
        $params = $this->compileParams($block->params, $block->hash);
        $helperName = self::quote($name);
        $bpCount = count($fnProgram->blockParams);

        $trailingArgs = ($bpCount > 0 || $outerBp !== '[]') ? ", $bpCount, $outerBp" : '';
        return self::getRuntimeFunc('hbbch', "\$cx, \$cx->helpers[$helperName], $helperName, $params, \$in, $fn, $else$trailingArgs");
    }

    /**
     * Returns true when an if/unless block can be safely inlined as a ternary expression.
     * Requires: no hash options (e.g. includeZero), no block params, exactly one condition param.
     * @param string[] $bp
     */
    private function canInlineConditional(BlockStatement $block, string $helperName, array $bp): bool
    {
        return $this->isKnownHelper($helperName)
            && ($helperName === 'if' || $helperName === 'unless')
            && count($block->params) === 1
            && $block->hash === null
            && !$bp;
    }

    /**
     * Compile the condition expression for an inlined if/unless ternary.
     * Single-segment plain context paths (e.g. {{#if foo}}) use cv() so that closures are
     * invoked before being tested. All other expressions (multi-segment paths, data variables,
     * block params, sub-expressions) use compileExpression() as a helper argument.
     * Closures at nested path segments are not invoked.
     * @param bool $negate true for `unless` or inverted `{{^if}}`
     */
    private function compileConditionalExpr(Expression $condExpr, bool $negate): string
    {
        $part = $condExpr instanceof PathExpression ? ($condExpr->parts[0] ?? null) : null;
        if ($condExpr instanceof PathExpression
            && !$condExpr->data
            && $condExpr->depth === 0
            && is_string($part)
            && count($condExpr->parts) === 1
            && !self::scopedId($condExpr)
            && $this->lookupBlockParam($part) === null
        ) {
            $val = self::getRuntimeFunc('cv', '$in, ' . self::quote($part));
        } else {
            $savedHelperArgs = $this->compilingHelperArgs;
            $this->compilingHelperArgs = true;
            $val = $this->compileExpression($condExpr);
            $this->compilingHelperArgs = $savedHelperArgs;
        }
        $cond = self::getRuntimeFunc('ifvar', $val);
        return $negate ? "!$cond" : $cond;
    }

    private function compileDynamicBlockHelper(BlockStatement $block, string $name, string $varPath = 'null'): string
    {
        $bp = $block->program->blockParams ?? [];
        $params = $this->compileParams($block->params, $block->hash);
        $blockFn = $block->program !== null
            ? $this->compileProgramWithBlockParams($block->program)
            : 'null';
        $else = $this->compileElseClause($block);
        $outerBp = $this->outerBlockParamsExpr();
        $helperName = self::quote($name);
        return self::getRuntimeFunc('dynhbbch', "\$cx, $helperName, $varPath, $params, \$in, $blockFn, $else, " . count($bp) . ", $outerBp");
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

        // Capture $blockParams if we're inside a block-param scope so the inline partial body can access them.
        $useVars = $this->blockParamsUseVars();
        $escapedName = self::quote($partialName);
        return self::getRuntimeFunc('in', "\$cx, $escapedName, " . self::templateClosure($body, useVars: $useVars));
    }

    private function Decorator(Decorator $decorator): never
    {
        throw new \Exception('Decorator has not been implemented');
    }

    private function PartialStatement(PartialStatement $statement): string
    {
        $name = $statement->name;

        if ($name instanceof SubExpression) {
            $p = $this->SubExpression($name);
            $this->context->usedDynPartial++;
        } elseif ($name instanceof PathExpression || $name instanceof StringLiteral || $name instanceof NumberLiteral) {
            $partialName = $this->resolvePartialName($name);
            $p = self::quote($partialName);
            $this->resolveAndCompilePartial($partialName);
        } else {
            $p = $this->compileExpression($name);
        }

        $vars = $this->compilePartialParams($statement->params, $statement->hash);
        $indent = self::quote($statement->indent);

        // When preventIndent is set, emit the indent as literal content (like handlebars.js
        // appendContent opcode) and invoke the partial with an empty indent so its lines are
        // not additionally indented.
        if ($this->context->options->preventIndent && $statement->indent !== '') {
            return "$indent." . self::getRuntimeFunc('p', "\$cx, $p, $vars, ''");
        }

        return self::getRuntimeFunc('p', "\$cx, $p, $vars, $indent");
    }

    private function PartialBlockStatement(PartialBlockStatement $statement): string
    {
        // Hoist inline partial registrations so they run before the partial is called.
        // Without this, inline partials defined in the block would only be registered when
        // {{> @partial-block}} is invoked, too late for partials that call them directly.
        $parts = [];
        foreach ($statement->program->body as $stmt) {
            if ($stmt instanceof BlockStatement && $stmt->type === 'DecoratorBlock') {
                $parts[] = $this->accept($stmt);
            }
        }

        $name = $statement->name;
        $body = $this->compileProgram($statement->program);
        $partialName = null;
        $found = false;

        if ($name instanceof PathExpression || $name instanceof StringLiteral || $name instanceof NumberLiteral) {
            $partialName = $this->resolvePartialName($name);
            $p = self::quote($partialName);
            $found = ($this->context->usedPartial[$partialName] ?? '') !== '';

            if (!$found && !str_starts_with($partialName, '@partial-block')) {
                $cnt = $this->resolvePartial($partialName);
                if ($cnt !== null) {
                    $this->context->usedPartial[$partialName] = $cnt;
                    $this->compilePartialTemplate($partialName, $cnt);
                    $found = true;
                }
            }

            // Mark as known for runtime resolution; not added to partialCode so $blockParams scope is preserved.
            $this->context->usedPartial[$partialName] ??= '';
        } else {
            $p = $this->compileExpression($name);
        }

        $vars = $this->compilePartialParams($statement->params, $statement->hash);

        // Capture $blockParams if we're inside a block-param scope so the partial block body can access them.
        $useVars = $this->blockParamsUseVars();
        $bodyClosure = self::templateClosure($body, useVars: $useVars);

        if ($partialName !== null && !$found) {
            // Register the block body as a fallback partial only if no runtime partial with this name exists yet.
            $parts[] = "(isset(\$cx->inlinePartials[$p]) || isset(\$cx->partials[$p]) ? '' : " . self::getRuntimeFunc('in', "\$cx, $p, $bodyClosure") . ')';
        }
        $parts[] = self::getRuntimeFunc('p', "\$cx, $p, $vars, '', $bodyClosure");
        return implode('.', $parts);
    }

    private function MustacheStatement(MustacheStatement $mustache): string
    {
        $raw = !$mustache->escaped || $this->context->options->noEscape;
        $fn = $raw ? 'raw' : 'encq';
        $path = $mustache->path;

        if ($path instanceof PathExpression) {
            $helperName = $this->getSimpleHelperName($path);

            if ($helperName !== null && ($this->isKnownHelper($helperName) || $mustache->params || $mustache->hash !== null)) {
                $call = $this->buildInlineHelperCall($helperName, $mustache->params, $mustache->hash);
                return self::getRuntimeFunc($fn, $call);
            }

            if ($mustache->params || $mustache->hash !== null) {
                // Non-simple path with params (data var or pathed expression): invoke via dv()
                $varPath = $this->PathExpression($path);
                $args = array_map(fn($p) => $this->compileExpression($p), $mustache->params);
                $call = self::getRuntimeFunc('dv', "$varPath, " . implode(', ', $args));
                return self::getRuntimeFunc($fn, $call);
            }

            // When not strict/assumeObjects, check runtime helpers for bare identifiers.
            if ($helperName !== null && !$this->context->options->strict && !$this->context->options->assumeObjects
                && $this->lookupBlockParam($helperName) === null) {
                $escapedKey = self::quote($helperName);
                if ($this->context->options->knownHelpersOnly) {
                    return self::getRuntimeFunc($fn, self::getRuntimeFunc('cv', "\$in, $escapedKey"));
                }
                return self::getRuntimeFunc($fn, self::getRuntimeFunc('hv', "\$cx, $escapedKey, \$in"));
            }

            // Plain variable. Data variables (@foo) may be closures, so wrap in dv() to invoke
            // them. Context variables (e.g. user.name) are never closures per the spec — all
            // lambda tests use single-segment identifiers which go through hv()/cv() — and
            // dv() doesn't pass context to them anyway, so skip it.
            $varPath = $this->PathExpression($path);
            if (!$path->data) {
                return self::getRuntimeFunc($fn, $varPath);
            }
            return self::getRuntimeFunc($fn, self::getRuntimeFunc('dv', $varPath));
        }

        // SubExpression path: {{(path args)}} — compile and render the sub-expression result
        if ($path instanceof SubExpression) {
            return self::getRuntimeFunc($fn, $this->SubExpression($path));
        }

        // Literal path — treat as named context lookup or helper call
        $literalKey = $this->getLiteralKeyName($path);

        if ($this->isKnownHelper($literalKey) || $mustache->params || $mustache->hash !== null) {
            $call = $this->buildInlineHelperCall($literalKey, $mustache->params, $mustache->hash);
            return self::getRuntimeFunc($fn, $call);
        }

        $escapedKey = self::quote($literalKey);

        if (!$this->context->options->strict && !$this->context->options->knownHelpersOnly) {
            return self::getRuntimeFunc($fn, self::getRuntimeFunc('hv', "\$cx, $escapedKey, \$in"));
        }

        $miss = $this->missValue($literalKey);
        return self::getRuntimeFunc($fn, "\$in[$escapedKey] ?? $miss");
    }

    private function ContentStatement(ContentStatement $statement): string
    {
        return self::quote($statement->value);
    }

    private function CommentStatement(CommentStatement $statement): string
    {
        return '';
    }

    // ── Expressions ─────────────────────────────────────────────────

    private function SubExpression(SubExpression $expression): string
    {
        $path = $expression->path;
        $helperName = match (true) {
            $path instanceof Literal => $this->getLiteralKeyName($path),
            $path instanceof PathExpression => $this->getSimpleHelperName($path),
            default => null,
        };

        if ($helperName === null) {
            // Dynamic callable: path rooted at a sub-expression, e.g. ((helper).prop args)
            if ($path instanceof PathExpression) {
                $varPath = $this->PathExpression($path);
                $args = array_map(fn($p) => $this->compileExpression($p), $expression->params);
                return self::getRuntimeFunc('dv', implode(', ', [$varPath, ...$args]));
            }
            throw new \Exception('Sub-expression must be a helper call');
        }

        return $this->buildInlineHelperCall($helperName, $expression->params, $expression->hash);
    }

    private function PathExpression(PathExpression $expression): string
    {
        $data = $expression->data;
        $depth = $expression->depth;
        $parts = $expression->parts;

        // When the path head is a SubExpression (e.g. (helper).foo.bar), compile the
        // sub-expression as the base and use the string tail as the remaining key accesses.
        $hasSubExprHead = $expression->head instanceof SubExpression;
        if ($hasSubExprHead) {
            $base = '(' . $this->SubExpression($expression->head) . ')';
            $stringParts = $expression->tail;
        } else {
            $base = $this->buildBasePath($data, $depth);
            $stringParts = self::stringPartsOf($parts);
        }

        // `this` with no parts or empty parts
        if (($expression->this_ && !$parts) || !$stringParts) {
            return $base;
        }

        // @partial-block as variable: truthy when an active partial block exists
        if ($data && $depth === 0 && count($stringParts) === 1 && $stringParts[0] === 'partial-block') {
            return "\$cx->partialBlock !== null ? true : null";
        }

        $isLength = end($stringParts) === 'length';
        $varParts = $isLength ? array_slice($stringParts, 0, -1) : $stringParts;
        $miss = $this->missValue($expression->original);

        // Check block params (depth-0, non-data, non-scoped paths only, not SubExpression-headed)
        if (!$hasSubExprHead && !$data && $depth === 0 && !self::scopedId($expression)) {
            $bp = $this->lookupBlockParam($stringParts[0]);
            if ($bp !== null) {
                [$bpDepth, $bpIndex] = $bp;
                $bpBase = "\$blockParams[$bpDepth][$bpIndex]";
                // Mark the current compileProgram() level as having a direct $blockParams reference.
                if ($this->bpRefStack) {
                    $this->bpRefStack[array_key_last($this->bpRefStack)] = true;
                }
                // Skip the block param name ($varParts[0]) since it has been resolved to a $blockParams index.
                $parent = $bpBase . self::buildKeyAccess(array_slice($varParts, 1));
                if ($isLength) {
                    return $this->buildLookupLength($parent);
                }
                return "$parent ?? $miss";
            }
        }

        // Handle .length: compile parent path through the normal mode-aware logic, then wrap in
        // lookupLength() at runtime. This mirrors HBS.js, where .length is a normal property
        // access with no compile-time special casing.
        if ($isLength) {
            return $this->buildLookupLength(
                $this->compileModeAwareLookup($base, $varParts, $expression->original, 'null'),
            );
        }

        return $this->compileModeAwareLookup($base, $stringParts, $expression->original, $miss);
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
     * Return [$depth, $index] if $name is a block param in any enclosing scope, null otherwise.
     * $depth=0 is the innermost scope; each outer scope increments $depth.
     * @return array{int,int}|null
     */
    private function lookupBlockParam(string $name): ?array
    {
        foreach ($this->blockParamValues as $depth => $levelParams) {
            $index = array_search($name, $levelParams, true);
            if ($index !== false) {
                assert(is_int($index));
                return [$depth, $index];
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
    private function resolvePartial(string $name): ?string
    {
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

        $program = $this->parser->parse($template);
        $code = (new Compiler($this->parser))->compile($program, $this->context);

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
     * Returns '[$a,$b], [hash]'.
     *
     * @param Expression[] $params
     */
    private function compileParams(array $params, ?Hash $hash): string
    {
        $savedHelperArgs = $this->compilingHelperArgs;
        $this->compilingHelperArgs = true;

        $positional = [];
        foreach ($params as $param) {
            $positional[] = $this->compileExpression($param);
        }

        $named = $hash ? $this->Hash($hash) : '';
        $this->compilingHelperArgs = $savedHelperArgs;

        return '[' . implode(',', $positional) . '], [' . $named . ']';
    }

    /**
     * Build context and hash arguments for partial calls: "$context, [named]".
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

        return "$contextVar, [$named]";
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
     * Compile the else/inverse clause of a block as a trailing closure argument, or 'null' if absent.
     */
    private function compileElseClause(BlockStatement $block): string
    {
        if (!$block->inverse) {
            $this->lastCompileProgramHadDirectBpRef = false;
            return 'null';
        }
        return $this->compileProgramWithBlockParams($block->inverse);
    }

    /**
     * Build the base path expression for a given data flag and depth.
     */
    private function buildBasePath(bool $data, int $depth): string
    {
        if ($data) {
            return '$cx->frame' . str_repeat("['_parent']", $depth);
        }
        return $depth > 0 ? "\$cx->depths[count(\$cx->depths)-$depth]" : '$in';
    }

    /**
     * Resolve the name of a non-SubExpression partial reference.
     */
    private function resolvePartialName(PathExpression|StringLiteral|NumberLiteral $name): string
    {
        return $name instanceof PathExpression ? $name->original : $this->getLiteralKeyName($name);
    }

    /**
     * Build a left-associative chain of runtime function calls over the given parts.
     * e.g. buildCallChain('f', '$in', ['a','b']) → "LR::f(LR::f($in, 'a'), 'b')"
     * An optional $extraArg is appended to every call's argument list.
     * @param string[] $parts
     */
    private static function buildCallChain(string $fn, string $base, array $parts, string $extraArg = ''): string
    {
        $extra = $extraArg !== '' ? ", $extraArg" : '';
        $expr = $base;
        foreach ($parts as $part) {
            $expr = self::getRuntimeFunc($fn, "$expr, " . self::quote($part) . $extra);
        }
        return $expr;
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

    private static function quote(string $string): string
    {
        return "'" . addcslashes($string, "'\\") . "'";
    }

    private function missValue(string $key): string
    {
        return ($this->context->options->strict && !$this->compilingHelperArgs)
            ? self::getRuntimeFunc('miss', self::quote($key))
            : 'null';
    }

    private function compileProgramOrEmpty(?Program $program): string
    {
        if (!$program) {
            $this->lastCompileProgramHadDirectBpRef = false;
            return "''";
        }
        return $this->compileProgram($program);
    }

    private function buildLookupLength(string $parent): string
    {
        $strict = $this->context->options->strict || $this->context->options->assumeObjects;
        return self::getRuntimeFunc('lookupLength', $strict ? "$parent, true" : $parent);
    }

    /**
     * Compile a mode-aware path access expression for the given base and parts.
     * @param string[] $parts
     */
    private function compileModeAwareLookup(string $base, array $parts, string $original, string $miss): string
    {
        if (!$parts) {
            return $base;
        }
        if ($this->context->options->assumeObjects || ($this->context->options->strict && $this->compilingHelperArgs)) {
            // Use nullCheck chain for assumeObjects and helper arguments in strict mode.
            // This mirrors HBS.js: both paths use bare nameLookup, so only a null intermediate throws
            // (JS TypeError), while a missing key on a valid object returns null silently (JS undefined).
            return self::buildCallChain('nullCheck', $base, $parts);
        }
        if ($this->context->options->strict) {
            return self::buildCallChain('strictLookup', $base, $parts, self::quote($original));
        }
        return $base . self::buildKeyAccess($parts) . " ?? $miss";
    }

    private function throwKnownHelpersOnly(string $helperName): never
    {
        throw new \Exception("You specified knownHelpersOnly, but used the unknown helper $helperName");
    }

    /**
     * Build an hbch (known) or dynhbch (unknown) inline helper call string.
     * @param Expression[] $params
     */
    private function buildInlineHelperCall(string $name, array $params, ?Hash $hash): string
    {
        $compiledParams = $this->compileParams($params, $hash);
        $helperName = self::quote($name);
        if ($this->isKnownHelper($name)) {
            return self::getRuntimeFunc('hbch', "\$cx, \$cx->helpers[$helperName], $helperName, $compiledParams, \$in");
        }
        if ($this->context->options->knownHelpersOnly) {
            $this->throwKnownHelpersOnly($name);
        }
        return self::getRuntimeFunc('dynhbch', "\$cx, $helperName, $compiledParams, \$in");
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
