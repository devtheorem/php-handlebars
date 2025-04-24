<?php

namespace DevTheorem\Handlebars;

readonly class Options
{
    /**
     * @param array<string, callable> $helpers
     * @param array<string, string> $partials
     */
    public function __construct(
        public bool $knownHelpersOnly = false,
        public bool $noEscape = false,
        public bool $strict = false,
        public bool $preventIndent = false,
        public bool $ignoreStandalone = false,
        public bool $explicitPartialContext = false,
        public array $helpers = [],
        public array $partials = [],
    ) {}
}
