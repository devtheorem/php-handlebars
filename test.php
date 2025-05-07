<?php

use DevTheorem\Handlebars\{Handlebars, Options};

require 'vendor/autoload.php';

$templateString = '{{> StrongPartial text="Use the syntax: {{varName}}."}}';

$template = Handlebars::compile($templateString, new Options(
    partials: [
        'StrongPartial' => '<strong>{{text}}</strong>',
    ]
));

echo $template([
    'varName' => 'Hello',
]);
