<?php

namespace DevTheorem\Handlebars;

use Closure;

readonly class Options
{
    /** @var array<string, bool> */
    public array $knownHelpers;

    /**
     * @param array<string, bool> $knownHelpers
     * @param array<string, string> $partials
     * @param null|Closure(string):(string|null) $partialResolver
     */
    public function __construct(
        array $knownHelpers = [],
        public bool $knownHelpersOnly = false,
        public bool $noEscape = false,
        public bool $strict = false,
        public bool $assumeObjects = false,
        public bool $preventIndent = false,
        public bool $ignoreStandalone = false,
        public bool $explicitPartialContext = false,
        public array $partials = [],
        public ?Closure $partialResolver = null,
    ) {
        $builtIn = ['if' => true, 'unless' => true, 'each' => true, 'with' => true, 'lookup' => true, 'log' => true];
        $this->knownHelpers = array_replace($builtIn, $knownHelpers);
    }
}
