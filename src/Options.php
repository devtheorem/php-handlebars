<?php

namespace DevTheorem\Handlebars;

final readonly class Options
{
    /** @var array<bool> */
    public array $knownHelpers;

    /**
     * @param array<bool> $knownHelpers
     */
    public function __construct(
        public bool $compat = false,
        array $knownHelpers = [],
        public bool $knownHelpersOnly = false,
        public bool $noEscape = false,
        public bool $strict = false,
        public bool $assumeObjects = false,
        public bool $preventIndent = false,
        public bool $ignoreStandalone = false,
        public bool $explicitPartialContext = false,
    ) {
        $builtIn = ['if' => true, 'unless' => true, 'each' => true, 'with' => true, 'lookup' => true, 'log' => true];
        $this->knownHelpers = $knownHelpers ? array_replace($builtIn, $knownHelpers) : $builtIn;
    }
}
