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

/** @internal */
enum SexprType
{
    case Helper;
    case Ambiguous;
    case Simple;
}

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

    private int $nextProgramId = 0;
    /** @var string[] */
    private array $programDefs = [];
    /**
     * Stack of dep arrays — one per active compilation level. Each entry tracks
     * the $pN variables directly referenced at that level (direct children only;
     * transitive deps are captured via the use() chains of inner closures).
     * @var string[][]
     */
    private array $programDepStack = [];
    /**
     * Deps for the top-level render closure — set by compile() after compileProgram() returns.
     * @var string[]
     */
    private array $renderDeps = [];

    public function __construct(
        private readonly Parser $parser,
    ) {}

    public function compile(Program $program, Context $context): string
    {
        $this->context = $context;
        $this->blockParamValues = [];
        $this->nextProgramId = 0;
        $this->programDefs = [];
        $this->programDepStack = [[]];
        $code = $this->compileProgram($program);
        $this->renderDeps = array_pop($this->programDepStack);
        return $code;
    }

    /**
     * Compile Handlebars template to PHP function.
     *
     * @param string $code generated PHP code
     */
    public function composePHPRender(string $code): string
    {
        $partials = implode(",\n", $this->context->partialCode);
        $useVars = implode(', ', $this->renderDeps);
        $closure = self::templateClosure($code, $partials, "\n \$in = &\$cx->data['root'];", $useVars);
        $defs = $this->programDefs ? implode("\n", $this->programDefs) . "\n" : '';
        return "use " . Runtime::class . " as LR;\n{$defs}return $closure;";
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
             \$cx = LR::createContext(\$in, \$options, [$partials]);$stmts
             return $code;
            }
            PHP;
    }

    private function compileProgram(Program $program): string
    {
        $parts = [];
        foreach ($program->body as $statement) {
            $part = $this->accept($statement);
            if ($part !== '' && $part !== "''") {
                $parts[] = $part;
            }
        }
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
            $node instanceof ContentStatement => self::quote($node->value),
            $node instanceof CommentStatement => '',
            $node instanceof SubExpression => $this->SubExpression($node),
            default => throw new \Exception('Unknown type: ' . (new \ReflectionClass($node))->getShortName()),
        };
    }

    private function compileExpression(Expression $expr): string
    {
        return match (true) {
            $expr instanceof SubExpression => $this->SubExpression($expr),
            $expr instanceof PathExpression => $this->PathExpression($expr),
            $expr instanceof StringLiteral => self::quote($expr->value),
            $expr instanceof NumberLiteral => (string) $expr->value,
            $expr instanceof BooleanLiteral => $expr->value ? 'true' : 'false',
            $expr instanceof NullLiteral, $expr instanceof UndefinedLiteral => 'null',
            default => throw new \Exception('Unknown expression type: ' . (new \ReflectionClass($expr))->getShortName()),
        };
    }

    // ── Statements ──────────────────────────────────────────────────

    private function BlockStatement(BlockStatement $block): string
    {
        // getSimpleHelperName returns the key name for both Literal paths and simple PathExpressions,
        // null for complex paths (multi-segment, scoped, data, depth). This mirrors HBS.js
        // transformLiteralToPath: literals are treated as single-part path lookups throughout.
        $helperName = $this->getSimpleHelperName($block->path);
        $type = $this->classifySexpr($helperName, $block->params, $block->hash);
        // Logical name for runtime dispatch: literal-normalized (strips source quoting).
        // e.g. {{#"foo bar"}} → 'foo bar', not '"foo bar"'. Falls back to path->original for complex paths.
        $name = $helperName ?? (string) $block->path->original;

        if ($type === SexprType::Helper) {
            if ($helperName !== null && $this->isKnownHelper($helperName)) {
                return $this->compileBlockHelper($block, $helperName);
            }
            if ($this->context->options->knownHelpersOnly) {
                $this->throwKnownHelpersOnly($name);
            }
        }

        // Simple/Literal path: look up the key in context. Complex path: compile the full expression.
        $var = $helperName !== null
            ? $this->compileModeAwareLookup('$in', [$helperName], $helperName)
            : $this->compileExpression($block->path);

        if ($type === SexprType::Helper) {
            return $this->compileDynamicBlockHelper($block, $name, $var);
        }

        $escapedName = self::quote($name);
        $inverted = $block->program === null;
        $fnProgram = $block->program ?? $block->inverse ?? throw new \LogicException('Inverted section must have an inverse program');
        $blockFn = $this->compileProgramWithBlockParams($fnProgram);
        $fn = $inverted ? 'null' : $blockFn;
        $else = $inverted ? $blockFn : $this->compileProgramOrNull($block->inverse);

        if (!$inverted && !$this->context->options->knownHelpersOnly) {
            if ($block->hash !== null || $block->program->blockParams) {
                $helperExpr = self::getRuntimeFunc('resolveHelper', "\$cx, $escapedName, $var, true");
                return $this->buildBlockHelperCall($helperExpr, $escapedName, $block, $blockFn, $else);
            }
        }

        $nameArg = !$this->context->options->knownHelpersOnly && (!$inverted || $type === SexprType::Ambiguous) ? ", $escapedName" : ', null';
        $outerBpArg = $this->blockParamValues ? ', $blockParams' : '';
        return self::getRuntimeFunc('sec', "\$cx, $var, \$in, $fn, $else$nameArg$outerBpArg");
    }

    private function isKnownHelper(string $helperName): bool
    {
        return $this->context->options->knownHelpers[$helperName] ?? false;
    }

    /**
     * Classify a sexpr like HBS.js classifySexpr(), given the pre-computed simple name.
     * - Helper: definitely a helper call (has params/hash, or is a known helper)
     * - Ambiguous: bare simple name that could be a helper or context value at runtime
     * - Simple: complex/scoped/data/depth path, or block param; always a context lookup
     * @param Expression[] $params
     */
    private function classifySexpr(?string $simpleName, array $params, ?Hash $hash): SexprType
    {
        if ($simpleName !== null) {
            if ($this->lookupBlockParam($simpleName) !== null) {
                return SexprType::Simple;
            } elseif ($params || $hash || $this->isKnownHelper($simpleName)) {
                return SexprType::Helper;
            }
            return $this->context->options->knownHelpersOnly ? SexprType::Simple : SexprType::Ambiguous;
        }
        return ($params || $hash) ? SexprType::Helper : SexprType::Simple;
    }

    /**
     * Build the use() clause string for an inline partial body closure.
     * Prepends $blockParams when inside a block-param scope,
     * then appends any hoisted program deps added since $depsBefore.
     */
    private function buildInlineUseClause(int $depsBefore): string
    {
        $bodyDeps = array_slice($this->programDepStack[array_key_last($this->programDepStack)], $depsBefore);
        $vars = [];
        if ($this->blockParamValues) {
            $vars[] = '$blockParams';
        }
        return implode(', ', array_merge($vars, $bodyDeps));
    }

    /**
     * Compile a block program, pushing/popping its block params around the compilation.
     * Returns a $pN variable name referencing a hoisted closure. The signature uses
     * array $blockParams = [] when the program declares or inherits block params.
     */
    private function compileProgramWithBlockParams(Program $program): string
    {
        $bp = $program->blockParams;
        if ($bp) {
            array_unshift($this->blockParamValues, $bp);
        }
        $this->programDepStack[] = [];
        $body = $this->compileProgram($program);
        if ($bp) {
            array_shift($this->blockParamValues);
        }

        // Include $blockParams in the signature when this program declares block params OR when it
        // is nested inside a block-param scope. In the latter case the body may reference
        // $blockParams (e.g. via buildBlockHelperCall passing it as the outer-bp arg), and callers
        // always pass the current bp stack as the third argument anyway.
        $usesBp = $bp || $this->blockParamValues;
        $preamble = '';
        if (str_contains($body, '$cx->depths[count($cx->depths)-')) {
            $preamble = '$sc=count($cx->depths);';
            $body = str_replace('$cx->depths[count($cx->depths)-', '$cx->depths[$sc-', $body);
        }
        $sig = $usesBp
            ? "function(\$cx, \$in, array \$blockParams = [])"
            : "function(\$cx, \$in)";

        $deps = array_pop($this->programDepStack);
        if ($deps) {
            $sig .= ' use (' . implode(', ', $deps) . ')';
        }

        $id = $this->nextProgramId++;
        $var = "\$p{$id}";
        $this->programDefs[] = "{$var} = {$sig} {{$preamble}return {$body};};";
        // Propagate this var to the parent dep level so callers can capture it.
        $this->programDepStack[array_key_last($this->programDepStack)][] = $var;
        return $var;
    }

    private function compileBlockHelper(BlockStatement $block, string $name): string
    {
        $inverted = $block->program === null;
        // For inverted blocks the fn body comes from the inverse program; for normal blocks, the program.
        $fnProgram = $block->program ?? $block->inverse;
        assert($fnProgram !== null);

        // Inline if/unless as ternary — eliminates helper dispatch and HelperOptions allocation.
        // Safe because if/unless don't change scope, so $cx and $in are already correct.
        // Negate for 'unless' in a normal block, or 'if' in an inverted block (swapped semantics).
        if ($this->canInlineConditional($block, $name, $fnProgram->blockParams)) {
            $cond = $this->compileConditionalExpr($block->params[0], $name === ($inverted ? 'if' : 'unless'));
            $body = $this->compileProgram($fnProgram);
            $elseBody = $this->compileProgramOrEmpty($inverted ? null : $block->inverse);
            return "($cond ? $body : $elseBody)";
        }

        $blockFn = $this->compileProgramWithBlockParams($fnProgram);
        if ($inverted) {
            // no {{else}} clause, so there is nothing to compile for fn
            $fn = 'null';
            $else = $blockFn;
        } else {
            $fn = $blockFn;
            $else = $this->compileProgramOrNull($block->inverse);
        }

        $helperName = self::quote($name);

        return $this->buildBlockHelperCall("\$cx->helpers[$helperName]", $helperName, $block, $fn, $else);
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

    private function compileDynamicBlockHelper(BlockStatement $block, string $name, string $varPath): string
    {
        $blockFn = $block->program !== null
            ? $this->compileProgramWithBlockParams($block->program)
            : 'null';
        $else = $this->compileProgramOrNull($block->inverse);
        $helperName = self::quote($name);
        $helperExpr = self::getRuntimeFunc('resolveHelper', "\$cx, $helperName, $varPath, true");
        return $this->buildBlockHelperCall($helperExpr, $helperName, $block, $blockFn, $else);
    }

    private function DecoratorBlock(BlockStatement $block): string
    {
        $helperName = $this->getSimpleHelperName($block->path);

        if ($helperName !== 'inline') {
            throw new \Exception('Unknown decorator: "' . $helperName . '"');
        } elseif (!$block->params) {
            $partialName = 'undefined'; // match JS for {{#*inline}} without a name (params[0] on empty array is undefined)
        } else {
            $firstArg = $block->params[0];
            if (!$firstArg instanceof Literal) {
                throw new \Exception("Unexpected inline partial argument type: {$firstArg->type}");
            }
            $partialName = $this->getLiteralKeyName($firstArg);
        }

        $depsBefore = count($this->programDepStack[array_key_last($this->programDepStack)]);
        $body = $this->compileProgramOrEmpty($block->program);

        // Register in usedPartial so {{> partialName}} can compile without error.
        // Do NOT add to partialCode - `in()` handles runtime registration, keeping inline partials block-scoped.
        $this->context->usedPartial[$partialName] = '';

        // Capture $blockParams and any hoisted program vars so the inline partial body can access them.
        $useVars = $this->buildInlineUseClause($depsBefore);
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
            $prepend = $indent . '.';
            $indent = "''";
        } else {
            $prepend = '';
        }

        // In compat mode, pass the caller's $in so the partial inherits the full context chain.
        $compatArg = $this->context->options->compat ? ', null, $in' : '';

        return $prepend . self::getRuntimeFunc('p', "\$cx, $p, $vars, $indent$compatArg");
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
        $depsBefore = count($this->programDepStack[array_key_last($this->programDepStack)]);
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

        // Capture $blockParams and any hoisted program vars so the partial block body can access them.
        $useVars = $this->buildInlineUseClause($depsBefore);
        $bodyClosure = self::templateClosure($body, useVars: $useVars);

        if ($partialName !== null && !$found) {
            // Register the block body as a fallback partial only if no runtime partial with this name exists yet.
            $parts[] = "(isset(\$cx->inlinePartials[$p]) || isset(\$cx->partials[$p]) ? '' : "
                . self::getRuntimeFunc('in', "\$cx, $p, $bodyClosure") . ')';
        }
        $compatArg = $this->context->options->compat ? ', $in' : '';
        $parts[] = self::getRuntimeFunc('p', "\$cx, $p, $vars, '', $bodyClosure$compatArg");
        return implode('.', $parts);
    }

    private function MustacheStatement(MustacheStatement $mustache): string
    {
        $fn = (!$mustache->escaped || $this->context->options->noEscape) ? 'raw' : 'encq';
        $path = $mustache->path;

        // SubExpression path: {{(path args)}} — always a direct helper call result
        if ($path instanceof SubExpression) {
            return self::getRuntimeFunc($fn, $this->SubExpression($path));
        }

        // PathExpression or Literal: getSimpleHelperName returns the key for both,
        // null only for complex paths (multi-segment, scoped, data, depth).
        $helperName = $this->getSimpleHelperName($path);
        $type = $this->classifySexpr($helperName, $mustache->params, $mustache->hash);

        if ($type === SexprType::Helper) {
            $call = $this->compileHelperCall($helperName, $path, $mustache->params, $mustache->hash);
            return self::getRuntimeFunc($fn, $call);
        }

        if ($type === SexprType::Ambiguous) {
            assert($helperName !== null);
            $escapedKey = self::quote($helperName);
            $isData = $path instanceof PathExpression && $path->data;
            $scope = $isData ? '$cx->data' : '$in';
            $assumeObjects = $this->context->options->assumeObjects ? 'true' : 'false';
            $strict = $this->context->options->strict ? 'true' : 'false';
            // @data vars are never depth-walked in compat mode.
            $compat = ($this->context->options->compat && !$isData) ? 'true' : 'false';
            return self::getRuntimeFunc($fn, self::getRuntimeFunc('hv', "\$cx, $escapedKey, $scope, $assumeObjects, $strict, $compat"));
        }

        // Simple: direct path lookup with lambda resolution.
        // For knownHelpersOnly bare identifiers (single-segment, non-data): use cv() to pass the
        // current context to any Closure, mirroring JS fn.call(context) where context is `this`.
        // For all other simple paths (multi-segment, scoped, depth, data): use dv() with zero args,
        // matching HBS.js container.lambda which also passes no positional arguments.
        if ($path instanceof PathExpression) {
            if ($helperName !== null && !$path->data && $this->context->options->knownHelpersOnly && !$this->context->options->compat) {
                $cvArgs = '$in, ' . self::quote($helperName) . ($this->context->options->strict ? ', true' : '');
                return self::getRuntimeFunc($fn, self::getRuntimeFunc('cv', $cvArgs));
            }
            $expression = $this->PathExpression($path);
        } else {
            // Literal in simple position: same lambda resolution as PathExpression above.
            $literalKey = $this->getLiteralKeyName($path);
            $expression = $this->compileModeAwareLookup('$in', [$literalKey], $literalKey);
        }

        return self::getRuntimeFunc($fn, self::getRuntimeFunc('dv', $expression));
    }

    // ── Expressions ─────────────────────────────────────────────────

    private function SubExpression(SubExpression $expression): string
    {
        $path = $expression->path;
        $helperName = ($path instanceof PathExpression || $path instanceof Literal)
            ? $this->getSimpleHelperName($path)
            : null;

        return $this->compileHelperCall($helperName, $path, $expression->params, $expression->hash);
    }

    private function PathExpression(PathExpression $path): string
    {
        $data = $path->data;
        $depth = $path->depth;

        // When the path head is a SubExpression (e.g. (helper).foo.bar), compile the
        // sub-expression as the base and use the string tail as the remaining key accesses.
        $hasSubExprHead = $path->head instanceof SubExpression;
        if ($hasSubExprHead) {
            $base = '(' . $this->SubExpression($path->head) . ')';
            $stringParts = $path->tail;
        } else {
            $base = $this->buildBasePath($data, $depth);
            /** @var string[] $stringParts */
            $stringParts = $path->parts;
        }

        if (!$stringParts) {
            return $base;
        }

        // @partial-block as variable: truthy when an active partial block exists
        if ($data && $depth === 0 && count($stringParts) === 1 && $stringParts[0] === 'partial-block') {
            return "\$cx->partialBlock !== null ? true : null";
        }

        $isLength = end($stringParts) === 'length';
        $isCurrentContextPath = !$hasSubExprHead && !$data && $depth === 0;
        $scoped = $isCurrentContextPath && self::scopedId($path);

        // Check block params (depth-0, non-data, non-scoped paths only, not SubExpression-headed)
        if ($isCurrentContextPath && !$scoped) {
            $bp = $this->lookupBlockParam($path->head);
            if ($bp !== null) {
                [$bpDepth, $bpIndex] = $bp;
                $bpBase = "\$blockParams[$bpDepth][$bpIndex]";
                // Skip the block param name since it has been resolved to a $blockParams index.
                $keys = $isLength ? array_slice($path->tail, 0, -1) : $path->tail;
                $lookup = $this->compileModeAwareLookup($bpBase, $keys, $path->original);
                return $isLength ? $this->buildLookupLength($lookup) : $lookup;
            }
        }

        // Handle .length: compile parent path through the normal mode-aware logic, then wrap in
        // lookupLength() at runtime. This mirrors HBS.js, where .length is a normal property
        // access with no compile-time special casing.
        if ($isLength) {
            $partsExceptLength = array_slice($stringParts, 0, -1);
            return $this->buildLookupLength(
                $this->compileModeAwareLookup($base, $partsExceptLength, $path->original, $scoped),
            );
        }

        return $this->compileModeAwareLookup($base, $stringParts, $path->original, $scoped);
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
        if (isset($this->context->options->partials[$name])) {
            return $this->context->options->partials[$name];
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

        $program = $this->parser->parse($template, $this->context->options->ignoreStandalone);
        $partialCompiler = new self($this->parser);
        $code = $partialCompiler->compile($program, $this->context);
        $closureExpr = self::templateClosure($code, useVars: implode(', ', $partialCompiler->renderDeps));

        if ($partialCompiler->programDefs) {
            $defs = implode("\n", $partialCompiler->programDefs) . "\n";
            $closureExpr = "(static function() {\n{$defs}return {$closureExpr};\n})()";
        }

        $this->context->partialCode[$name] = self::quote($name) . ' => ' . $closureExpr;
    }

    public function handleDynamicPartials(): void
    {
        if ($this->context->usedDynPartial === 0) {
            return;
        }

        foreach ($this->context->options->partials as $name => $code) {
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
     * Extract simple helper name from a path.
     * For Literal paths (e.g. {{#"foo bar"}}, {{#12}}), returns the stringified key name.
     * For PathExpression, returns the single part only if depth-0, non-data, non-scoped, single-segment.
     * Returns null for complex paths (multi-segment, scoped, data, depth > 0).
     */
    private function getSimpleHelperName(PathExpression|Literal $path): ?string
    {
        if ($path instanceof Literal) {
            return $this->getLiteralKeyName($path);
        }
        if ($path->depth > 0
            || self::scopedId($path)
            || count($path->parts) !== 1
            || !is_string($path->parts[0])
        ) {
            return null;
        }

        return $path->parts[0];
    }

    /**
     * Build the base path expression for a given data flag and depth.
     */
    private function buildBasePath(bool $data, int $depth): string
    {
        if ($data) {
            return '$cx->data' . str_repeat("['_parent']", $depth);
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
    private static function buildCallChain(string $fn, string $base, array $parts, ?string $extraArg = null): string
    {
        $extra = $extraArg !== null ? ", $extraArg" : '';
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

    private function buildBlockHelperCall(string $helperExpr, string $escapedName, BlockStatement $block, string $fn, string $else): string
    {
        $outerBp = $this->blockParamValues ? '$blockParams' : '[]';
        $bpCount = count(($block->program ?? $block->inverse)->blockParams ?? []);
        $params = $this->compileParams($block->params, $block->hash);

        // omit trailing bpCount/outerBp args when both are zero/empty
        $trailingArgs = ($bpCount > 0 || $outerBp !== '[]') ? ", $outerBp, $bpCount" : '';
        $args = "\$cx, $helperExpr, $escapedName, $params, \$in, $fn, $else";
        return self::getRuntimeFunc('hbbch', $args . $trailingArgs);
    }

    /**
     * Compile a helper call for a MustacheStatement (Helper type) or SubExpression.
     * @param Expression[] $params
     */
    private function compileHelperCall(?string $helperName, Expression $path, array $params, ?Hash $hash): string
    {
        $compiledParams = $this->compileParams($params, $hash);

        if ($helperName !== null) {
            $escapedName = self::quote($helperName);
            $isData = $path instanceof PathExpression && $path->data;
            if ($this->isKnownHelper($helperName)) {
                return self::getRuntimeFunc('hbch', "\$cx, \$cx->helpers[$escapedName], $escapedName, $compiledParams, \$in");
            }
            if ($this->context->options->knownHelpersOnly) {
                $this->throwKnownHelpersOnly($helperName);
            }
            $varPath = $isData ? "\$cx->data[$escapedName] ?? null" : "\$in[$escapedName] ?? null";
            $checkHelpers = 'true';
        } elseif ($path instanceof PathExpression) {
            $varPath = $this->PathExpression($path);
            $stringParts = array_filter($path->parts, 'is_string');
            if (count($stringParts) === count($path->parts)) {
                // All-string parts (foo.bar, ../fn, ./fn, @fn): scoped/depth/data paths resolve
                // from context only; normal paths check the helpers hash first via resolveHelper.
                $escapedName = self::quote($path->original);
                $checkHelpers = (!$path->data && $path->depth === 0 && !self::scopedId($path)) ? 'true' : 'false';
            } else {
                // SubExpression-headed path (e.g. ((helper).prop args)): context-only resolution.
                $escapedName = self::quote(implode('.', $stringParts));
                $checkHelpers = 'false';
            }
        } else {
            throw new \Exception('Sub-expression must be a helper call');
        }

        $resolved = self::getRuntimeFunc('resolveHelper', "\$cx, $escapedName, $varPath, $checkHelpers");
        return self::getRuntimeFunc('hbch', "\$cx, $resolved, $escapedName, $compiledParams, \$in");
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

    private function compileProgramOrNull(?Program $program): string
    {
        if (!$program) {
            return 'null';
        }
        return $this->compileProgramWithBlockParams($program);
    }

    private function compileProgramOrEmpty(?Program $program): string
    {
        if (!$program) {
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
    private function compileModeAwareLookup(string $base, array $parts, string $original, bool $scoped = false): string
    {
        if (!$parts) {
            return $base;
        }
        // Compat mode: walk the scope chain for parts[0] instead of looking up in $in directly.
        // For single-part strict paths, use compatStrictLookup (throws on miss); all other paths
        // use compatLookup (null falls through to the next depth), matching HBS.js container.lookup.
        if ($this->context->options->compat && $base === '$in' && !$scoped) {
            $escapedName = self::quote($parts[0]);
            array_shift($parts);
            $lookupFn = (!$parts && $this->context->options->strict && !$this->compilingHelperArgs)
                ? 'compatStrictLookup'
                : 'compatLookup';
            $base = self::getRuntimeFunc($lookupFn, '$cx, $in, ' . $escapedName);
            if (!$parts) {
                return $base;
            }
            // Multi-part: $base is now the compat-resolved root; fall through to mode dispatch below.
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
        return $base . self::buildKeyAccess($parts) . ' ?? null';
    }

    private function throwKnownHelpersOnly(string $helperName): never
    {
        throw new \Exception("You specified knownHelpersOnly, but used the unknown helper $helperName");
    }
}
