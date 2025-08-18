<?php

use DevTheorem\Handlebars\{Handlebars, HelperOptions, Options};

require 'vendor/autoload.php';

$file = file_get_contents('template.hbs');
$templateString = $file;

$code = Handlebars::precompile($templateString, new Options(
    helpers: [
        'ifEquals' => function (mixed $a, mixed $b, HelperOptions $options) {
            $jsEquals = function (mixed $a, mixed $b): bool {
                if ($a === null || $b === null) {
                    // in JS, null is not equal to blank string or false or zero
                    return $a === $b;
                }

                return $a == $b;
            };

            return $jsEquals($a, $b) ? $options->fn() : $options->inverse();
        },
    ],
));

echo $code . "\n\n";

$template = Handlebars::template($code);

echo $template([
    'item' => 'buzz',
]);
