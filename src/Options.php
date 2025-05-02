<?php

namespace DevTheorem\Handlebars;

use Closure;

readonly class Options
{
    /**
     * @param array<string, callable> $helpers
     * @param null|Closure(Context, string):(Closure|null) $helperResolver
     * @param array<string, string> $partials
     * @param null|Closure(Context, string):(string|null) $partialResolver
     */
    public function __construct(
        public bool $knownHelpersOnly = false,
        public bool $noEscape = false,
        public bool $strict = false,
        public bool $preventIndent = false,
        public bool $ignoreStandalone = false,
        public bool $explicitPartialContext = false,
        public array $helpers = [],
        public ?Closure $helperResolver = null,
        public array $partials = [],
        public ?Closure $partialResolver = null,
    ) {}
}
