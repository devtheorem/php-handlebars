<?php

use DevTheorem\Handlebars\{Handlebars, HelperOptions, Options};

require 'vendor/autoload.php';

$templateString = '{{test "\"\"\"" prop="\"\"\""}}';

$template = Handlebars::compile($templateString, new Options(
    helpers: [
        'test' => function ($arg1, HelperOptions $options) {
            return "{$arg1} {$options->hash['prop']}";
        },
    ]
));

echo $template();
