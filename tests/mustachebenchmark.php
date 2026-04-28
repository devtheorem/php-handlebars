<?php

/**
 * Benchmark comparing PHP Handlebars (compat mode) with Mustache.php render performance.
 * Templates are precompiled once before measurement. Default iterations: 1000.
 *
 * Usage: php -d opcache.enable_cli=1 -d opcache.jit=tracing tests/mustachebenchmark.php
 */

use DevTheorem\Handlebars\Handlebars;
use DevTheorem\Handlebars\Options;
use Mustache\Cache\NoopCache;
use Mustache\Engine;

require __DIR__ . '/../vendor/autoload.php';

$iterations = (int) ($argv[1] ?? 1000);

$template = loadMustacheTemplate('mustache-page');
$partialNames = [
    'mustache-nav-item',
    'mustache-alert',
    'mustache-breadcrumbs',
    'mustache-page-header',
    'mustache-pagination',
    'mustache-side-panel',
    'mustache-footer-col',
];
$partialTemplates = [];

foreach ($partialNames as $name) {
    $partialTemplates[$name] = loadMustacheTemplate($name);
}

$data = buildData();

// ==================== PHP Handlebars (compat mode) ====================

echo "=== PHP Handlebars (compat mode) ===\n";

$options = new Options(compat: true);

$php = Handlebars::precompile($template, $options);
$hbsPartials = [];

foreach ($partialTemplates as $name => $src) {
    $hbsPartials[$name] = Handlebars::compile($src, $options);
}

$hbsRenderer = Handlebars::template($php);

// Warm up: give the JIT a chance to compile hot paths before we measure.
for ($i = 0; $i < 50; $i++) {
    $hbsRenderer($data, ['partials' => $hbsPartials]);
}

memory_reset_peak_usage();
$start = hrtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $hbsRenderer($data, ['partials' => $hbsPartials]);
}

$elapsed = (hrtime(true) - $start) / 1e9;
$renderPeakMB = memory_get_peak_usage() / 1024 / 1024;
$perRender = $elapsed / $iterations * 1000;
$outputBytes = strlen($hbsRenderer($data, ['partials' => $hbsPartials]));

printf(
    "Rendered %d times  |  %.2f ms/render   |  %6.1f KB output  |  %.1f MB peak\n",
    $iterations,
    $perRender,
    $outputBytes / 1024,
    $renderPeakMB,
);

// ==================== Mustache.php ====================

echo "\n=== Mustache.php ===\n";

$mustache = new Engine([
    'cache' => new NoopCache(),
    'partials' => $partialTemplates,
]);

$mustacheTpl = $mustache->loadTemplate($template);

// Warm up
for ($i = 0; $i < 50; $i++) {
    $mustacheTpl->render($data);
}

memory_reset_peak_usage();
$start = hrtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $mustacheTpl->render($data);
}

$elapsed = (hrtime(true) - $start) / 1e9;
$renderPeakMB = memory_get_peak_usage() / 1024 / 1024;
$perRender = $elapsed / $iterations * 1000;
$outputBytes = strlen($mustacheTpl->render($data));

printf(
    "Rendered %d times  |  %.2f ms/render   |  %6.1f KB output  |  %.1f MB peak\n",
    $iterations,
    $perRender,
    $outputBytes / 1024,
    $renderPeakMB,
);

if (isset($argv[2])) {
    file_put_contents(__DIR__ . '/hbs_out.html', $hbsRenderer($data, ['partials' => $hbsPartials]));
    file_put_contents(__DIR__ . '/mus_out.html', $mustacheTpl->render($data));
}

/**
 * @return array<mixed>
 */
function buildData(): array
{
    $columns = [
        ['key' => 'id', 'label' => '#', 'sortable' => true, 'thClass' => 'sortable', 'sortUrl' => '/orders?sort=id&dir=asc'],
        ['key' => 'name', 'label' => 'Customer', 'sortable' => false, 'thClass' => ''],
        ['key' => 'created', 'label' => 'Date', 'sortable' => true, 'thClass' => 'sortable sort-asc', 'sortUrl' => '/orders?sort=created&dir=desc'],
        ['key' => 'total', 'label' => 'Total', 'sortable' => false, 'thClass' => ''],
        ['key' => 'active', 'label' => 'Active', 'sortable' => false, 'thClass' => ''],
    ];

    $rawItems = array_map(fn($i) => [
        'id' => (string) $i,
        'name' => "Customer $i",
        'created' => date('Y-m-d', mktime(0, 0, 0, (int) ceil($i / 28), (($i - 1) % 28) + 1, 2024) ?: 0),
        'total' => 100.0 * $i,
        'active' => (bool) ($i % 2),
    ], range(1, 100));

    $rows = array_map(function (array $item, int $idx) use ($columns): array {
        $cells = array_map(fn(array $col) => [
            'cellClass' => 'col-' . $col['key'],
            'content' => match ($col['key']) {
                'name' => '<a href="/c/' . htmlspecialchars($item['id']) . '">' . htmlspecialchars($item['name']) . '</a>',
                'created' => '<time datetime="' . htmlspecialchars($item['created']) . '">' . date('M j, Y', strtotime($item['created']) ?: null) . '</time>',
                'total' => 'USD ' . number_format($item['total'], 2),
                'active' => $item['active'] ? '<i class="icon-check text-success"></i>' : '<i class="icon-times text-muted"></i>',
                default => htmlspecialchars((string) $item[$col['key']]),
            },
        ], $columns);

        return [
            'id' => $item['id'],
            'rowClass' => $idx === 2 ? 'selected' : '',
            'hasActions' => true,
            'cells' => $cells,
            'actions' => [
                [
                    'url' => '/orders/' . $item['id'] . '/edit',
                    'style' => 'secondary',
                    'label' => 'Edit',
                    'icon' => 'edit',
                    'confirm' => false,
                ],
                [
                    'url' => '/orders/' . $item['id'],
                    'style' => 'danger',
                    'label' => 'Delete',
                    'icon' => 'trash',
                    'confirm' => true,
                    'confirmMessage' => 'Are you sure you want to delete this?',
                ],
            ],
        ];
    }, $rawItems, array_keys($rawItems));

    return [
        'lang' => 'en',
        'pageTitle' => 'Dashboard | MyApp',
        'siteName' => 'MyApp',
        'stylesheets' => [
            ['url' => '/css/app.css'],
            ['url' => '/css/print.css', 'media' => 'print'],
        ],
        'bodyClass' => 'page-dashboard is-admin',
        'sticky' => true,
        'rootUrl' => '/',
        'logoHtml' => '<img src="/logo.svg" alt="">',
        // Labels at root — accessed via compat/stack-lookup from within {{#user}}
        'loginLabel' => 'Log In',
        'profileLabel' => 'Profile',
        'settingsLabel' => 'Settings',
        'adminLabel' => 'Admin',
        'logoutLabel' => 'Log Out',
        'user' => [
            'id' => 1,
            'name' => 'Alice',
            'avatar' => '/avatars/alice.jpg',
            'isAdmin' => true,
        ],
        'navItems' => [
            ['label' => 'Home', 'url' => '/', 'active' => true, 'hasChildren' => false],
            ['label' => 'Reports', 'url' => '/reports', 'active' => false, 'badge' => '3', 'hasChildren' => false],
            ['label' => 'More', 'url' => '#', 'active' => false, 'icon' => 'chevron', 'hasChildren' => true, 'children' => [
                ['label' => 'Sub A', 'url' => '/a'],
                ['label' => 'Sub B', 'url' => '/b'],
            ]],
        ],
        'alerts' => [
            ['type' => 'success', 'message' => 'Saved successfully!', 'dismissible' => true, 'icon' => 'check'],
        ],
        'hasBreadcrumbs' => true,
        'breadcrumbs' => [
            ['label' => 'Home', 'url' => '/', 'isLink' => true, 'active' => false],
            ['label' => 'Orders', 'url' => '/orders', 'isLink' => true, 'active' => false],
            ['label' => 'List', 'url' => '', 'isLink' => false, 'active' => true],
        ],
        'heading' => 'Orders',
        'headingBadge' => ['type' => 'primary', 'text' => 'Live'],
        'subheading' => 'All orders',
        'hasPageActions' => true,
        'pageActions' => [
            ['label' => 'New Order', 'url' => '/orders/new', 'style' => 'primary', 'icon' => 'plus'],
        ],
        'hasItems' => true,
        'tableClass' => 'table table-striped table-hover',
        'columns' => $columns,
        'showActions' => true,
        'actionsLabel' => 'Actions',
        'currency' => 'USD',  // accessed via compat/stack-lookup from within {{#rows}}
        'rows' => $rows,
        'showTotals' => true,
        'totalCells' => [
            ['content' => ''],
            ['content' => ''],
            ['content' => ''],
            ['content' => 'USD ' . number_format(5500.00, 2)],
            ['content' => ''],
        ],
        'pagination' => [
            'label' => 'Page navigation',
            'hasPrev' => false,
            'hasNext' => true,
            'prevUrl' => '#',
            'prevLabel' => 'Previous',
            'nextUrl' => '/orders?page=2',
            'nextLabel' => 'Next',
            'showingText' => 'Showing 1–10 of 42',
            'pages' => [
                ['active' => true, 'ellipsis' => false, 'number' => 1, 'url' => '/orders'],
                ['active' => false, 'ellipsis' => false, 'number' => 2, 'url' => '/orders?page=2'],
                ['active' => false, 'ellipsis' => true, 'number' => null, 'url' => ''],
                ['active' => false, 'ellipsis' => false, 'number' => 5, 'url' => '/orders?page=5'],
            ],
        ],
        'sidePanels' => [
            [
                'id' => 'summary',
                'title' => 'Summary',
                'isStats' => true,
                'isLinks' => false,
                'isHtml' => false,
                'collapsible' => true,
                'collapsed' => false,
                'ariaExpanded' => 'true',
                'stats' => [
                    ['label' => 'Total Orders', 'value' => 42, 'trend' => 'up', 'hasDelta' => true, 'delta' => 5],
                    ['label' => 'Revenue', 'value' => '$5,500', 'unit' => 'USD', 'hasDelta' => false, 'delta' => 0],
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
        'currentYear' => 2024,
        'socialLinks' => [
            'items' => [
                ['name' => 'GitHub', 'url' => 'https://github.com/myapp', 'icon' => 'github'],
                ['name' => 'Twitter', 'url' => 'https://twitter.com/myapp', 'icon' => 'twitter'],
            ],
        ],
        'scripts' => [
            ['url' => '/js/vendor.js'],
            ['url' => '/js/app.js', 'defer' => true],
        ],
        'emptyHeading' => 'No orders found',
        'emptyMessage' => 'Try adjusting your filters.',
    ];
}

function loadMustacheTemplate(string $name): string
{
    $filename = __DIR__ . "/templates/{$name}.mustache";
    $template = file_get_contents($filename);

    if ($template === false) {
        exit("Failed to open {$filename}");
    }

    return $template;
}
