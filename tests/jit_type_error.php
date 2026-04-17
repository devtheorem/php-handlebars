<?php

/**
 * Minimal self-contained reproduction for PHP JIT tracing CV corruption bug.
 * https://github.com/php/php-src/issues/21746
 *
 * Run: php -d opcache.enable_cli=1 -d opcache.jit=tracing tests/jit_type_error.php
 */

final class LR {
    public static function p(mixed $cx, string $name, mixed $context, array $hash, string $indent): string {
        try { $result = ''; } finally {}
        $lines = explode("\n", $result);
        foreach ($lines as $i => $_) { if ($i === 0) break; }
        return $result;
    }
    public static function hbbch(array $positional, ?\Closure $cb): string {
        if ($cb && is_array($positional[0] ?? null)) foreach ($positional[0] as $v) $cb($v);
        return '';
    }
}

$renderCode = <<<'PHP'
<?php
return function (mixed $in = null, array $options = []) {
 $a = null;
 $b = '';
 $c = '';
 $d = fn() => '';
 $e = fn() => '';
 $f = fn() => '';
 $g = fn() => '';
 return ''
  .LR::p(null, '', null, [], '')
  .LR::hbbch([null], $d)
  .LR::hbbch([null], $e)
  .LR::hbbch([null], $f)
  .LR::hbbch([null], $g);
};
PHP;

// file_put_contents is required, not just require of an existing file: the fresh mtime
// triggers opcache's file_update_protection, compiling the closure into a transient
// (non-cached) opcode array. The JIT traces this differently, forming the cross-frame
// STOP_LINK path that triggers the CV slot corruption.
$renderFile = __DIR__ . '/jit_crash_code.php';
file_put_contents($renderFile, $renderCode);
$renderer = require $renderFile;

for ($r = 0; $r < 70; $r++) {
    echo "Render $r...";
    $renderer();
    echo "OK\n";
}

echo "--- Completed without crash (JIT may not have been active) ---\n";
