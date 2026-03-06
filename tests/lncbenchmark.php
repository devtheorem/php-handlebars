<?php

/**
 * LightnCandy benchmark script. Default iterations: 1000.
 *
 * Usage: php -d opcache.enable_cli=1 -d opcache.jit=tracing tests/lncbenchmark.php
 */

use LightnCandy\LightnCandy;

require __DIR__ . '/../vendor/autoload.php';

$iterations = (int) ($argv[1] ?? 1000);

// A large, complex template exercising as many syntax features as possible.
$template = loadTemplate('large-page');
$partialNames = ['alert', 'breadcrumbs', 'footer-col', 'nav-item', 'page-header', 'pagination', 'side-panel'];
$partialTemplates = [];

foreach ($partialNames as $name) {
    $partialTemplates[$name] = loadTemplate($name);
}

$helpers = [
    't' => function (string $key, $options) {
        $translations = [
            'nav.profile' => 'Profile',
            'nav.settings' => 'Settings',
            'nav.admin' => 'Admin',
            'nav.logout' => 'Log Out',
            'nav.login' => 'Log In',
            'table.actions' => 'Actions',
            'table.empty' => 'No records found.',
            'pagination.label' => 'Page navigation',
            'pagination.prev' => 'Previous',
            'pagination.next' => 'Next',
            'pagination.showing' => 'Showing {start}–{end} of {total}',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'confirm_delete' => 'Are you sure you want to delete this?',
        ];
        $str = $translations[$key] ?? $key;
        foreach ($options['hash'] as $k => $v) {
            // for pagination.showing
            $str = str_replace('{' . $k . '}', (string) $v, $str);
        }
        return $str;
    },
    'formatDate' => function (mixed $value, string $format) {
        return date($format, strtotime($value));
    },
    'formatCurrency' => function (mixed $value, ?string $format) {
        return ($format ? "$format " : '') . number_format($value, 2);
    },
    'replace' => function (string $subject, string $search, ?string $replace) {
        return str_replace($search, $replace ?? '', $subject);
    },
    'eq' => function (mixed $a, mixed $b) {
        if ($a === null || $b === null) {
            // in JS, null is not equal to blank string or false or zero
            return $a === $b;
        }

        return $a == $b;
    },
    'and' => function (mixed $a, mixed $b) {
        return $a && $b;
    },
    'not' => function (mixed $a) {
        return !$a;
    },
    'gt' => function (mixed $a, mixed $b) {
        return $a > $b;
    },
];

$data = [
    'lang' => 'en',
    'pageTitle' => 'Dashboard',
    'siteName' => 'MyApp',
    'stylesheets' => [
        ['url' => '/css/app.css'],
        ['url' => '/css/print.css', 'media' => 'print'],
    ],
    'bodyClass' => 'page-dashboard',
    'sticky' => true,
    'rootUrl' => '/',
    'logoHtml' => '<img src="/logo.svg" alt="">',
    'user' => [
        'id' => 1,
        'name' => 'Alice',
        'avatar' => '/avatars/alice.jpg',
        'isAdmin' => true,
        'verified' => true,
    ],
    'navItems' => [
        ['label' => 'Home', 'url' => '/', 'active' => true],
        ['label' => 'Reports', 'url' => '/reports', 'badge' => '3'],
        ['label' => 'More', 'url' => '#', 'icon' => 'chevron', 'children' => [
            ['label' => 'Sub A', 'url' => '/a'],
            ['label' => 'Sub B', 'url' => '/b'],
        ]],
    ],
    'alerts' => [
        ['type' => 'success', 'message' => 'Saved!', 'dismissible' => true, 'icon' => 'check'],
    ],
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Orders', 'url' => '/orders'],
        ['label' => 'List', 'url' => '/orders/list'],
    ],
    'heading' => 'Orders',
    'headingBadge' => ['type' => 'primary', 'text' => 'Live'],
    'subheading' => 'All orders',
    'actions' => [
        ['label' => 'New', 'url' => '/orders/new', 'primary' => true, 'icon' => 'plus'],
    ],
    'hoverable' => true,
    'bordered' => false,
    'sortBaseUrl' => '/orders',
    'currentSort' => ['key' => 'date', 'dir' => 'asc'],
    'showActions' => true,
    'selectedIndex' => 2,
    'columnCount' => 5,
    'currency' => 'USD',
    'columns' => [
        ['key' => 'id', 'label' => '#', 'sortable' => true, 'type' => 'text'],
        ['key' => 'name', 'label' => 'Customer', 'type' => 'link', 'linkTemplate' => '/c/{id}'],
        ['key' => 'created', 'label' => 'Date', 'sortable' => true, 'type' => 'date', 'format' => 'M j, Y'],
        ['key' => 'total', 'label' => 'Total', 'type' => 'currency', 'showTotal' => true],
        ['key' => 'active', 'label' => 'Active', 'type' => 'boolean'],
    ],
    'items' => array_map(fn($i) => [
        'id' => (string) $i,
        'name' => "Customer $i",
        'created' => date('Y-m-d', mktime(0, 0, 0, (int) ceil($i / 28), (($i - 1) % 28) + 1, 2024) ?: null),
        'total' => 100.0 * $i,
        'active' => (bool) ($i % 2),
        'deleted' => false,
        'currency' => 'USD',
    ], range(1, 100)),
    'rowActions' => [
        ['icon' => 'edit', 'style' => 'secondary', 'labelKey' => 'edit', 'urlTemplate' => '/orders/{id}/edit', 'requiresAdmin' => false],
        ['icon' => 'trash', 'style' => 'danger', 'labelKey' => 'delete', 'urlTemplate' => '/orders/{id}', 'confirm' => true, 'confirmKey' => 'confirm_delete', 'requiresAdmin' => true],
    ],
    'showTotals' => true,
    'totals' => ['total' => 5500.00],
    'pagination' => [
        'hasPrev' => false,
        'hasNext' => true,
        'prevUrl' => '#',
        'nextUrl' => '/orders?page=2',
        'start' => 1,
        'end' => 10,
        'total' => 42,
        'pages' => [
            ['active' => true, 'number' => 1, 'url' => '/orders'],
            ['active' => false, 'number' => 2, 'url' => '/orders?page=2'],
            ['ellipsis' => true, 'number' => null, 'url' => ''],
            ['active' => false, 'number' => 5, 'url' => '/orders?page=5'],
        ],
    ],
    'sidePanels' => [
        [
            'id' => 'summary',
            'title' => 'Summary',
            'type' => 'stats',
            'collapsible' => true,
            'collapsed' => false,
            'stats' => [
                ['label' => 'Total Orders', 'value' => 42, 'trend' => 'up', 'delta' => 5],
                ['label' => 'Revenue', 'value' => '$5,500', 'unit' => 'USD', 'delta' => 0],
            ],
        ],
    ],
    'footerColumns' => [
        ['heading' => 'Product', 'links' => [
            ['label' => 'Features', 'url' => '/features'],
            ['label' => 'Pricing', 'url' => '/pricing'],
        ]],
        ['heading' => 'Legal', 'links' => [
            ['label' => 'Privacy', 'url' => '/privacy'],
            ['label' => 'Terms', 'url' => '/terms'],
        ]],
    ],
    'copyright' => '©',
    'showYear' => true,
    'currentYear' => 2024,
    'social' => [
        ['name' => 'GitHub', 'url' => 'https://github.com/myapp', 'icon' => 'github'],
    ],
    'scripts' => [
        ['url' => '/js/vendor.js'],
        ['url' => '/js/app.js', 'defer' => true],
    ],
];

$options = [
    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
    'helpers' => $helpers,
    'partials' => $partialTemplates,
];

// Warm up: give the JIT a chance to compile hot paths before we measure.
for ($i = 0; $i < 50; $i++) {
    LightnCandy::compile($template, $options);
}

$start = hrtime(true);

for ($i = 0; $i < $iterations; $i++) {
    LightnCandy::compile($template, $options);
}

$elapsed = (hrtime(true) - $start) / 1e9;
$perParse = $elapsed / $iterations * 1000;
$code = LightnCandy::compile($template, $options);
$codeBytes = strlen($code === false ? '' : $code);

foreach ($partialTemplates as $src) {
    $partialCode = LightnCandy::compile($src, $options);
    $codeBytes += strlen($partialCode === false ? '' : $partialCode);
}

printf(
    "Compiled %d times in %.3f s  |  %.3f ms/compile  |  %.1f KB code  (%d partials)\n",
    $iterations,
    $elapsed,
    $perParse,
    $codeBytes / 1024,
    count($partialTemplates),
);

$renderer = LightnCandy::prepare($code === false ? '' : $code);

// Warm up
for ($i = 0; $i < 50; $i++) {
    $renderer($data);
}

$start = hrtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $renderer($data);
}

$elapsed = (hrtime(true) - $start) / 1e9;
$perRun = $elapsed / $iterations * 1000;
$outputBytes = strlen($renderer($data));

printf(
    "Executed %d times in %.3f s  |  %.3f ms/render   |  %.1f KB output\n",
    $iterations,
    $elapsed,
    $perRun,
    $outputBytes / 1024,
);

//echo "\n", $code, "\n";

function loadTemplate(string $name): string
{
    $filename = __DIR__ . "/templates/$name.hbs";
    $template = file_get_contents($filename);

    if ($template === false) {
        exit("Failed to open $filename");
    }

    return $template;
}
