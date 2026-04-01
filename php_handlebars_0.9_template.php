Compiled 1000 times  |  5.42 ms/compile  |    22.1 KB code    |  5.1 MB peak
Executed 1000 times  |  2.53 ms/render   |   140.4 KB output  |  3.6 MB peak
<?php
use DevTheorem\Handlebars\Runtime as LR;
use DevTheorem\Handlebars\SafeString;
use DevTheorem\Handlebars\HelperOptions;
use DevTheorem\Handlebars\RuntimeContext;
return function (mixed $in = null, array $options = []) {
    $helpers = [            't' => function (string $key, HelperOptions $options) {
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
        foreach ($options->hash as $k => $v) {
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
            'and' => fn(mixed $a, mixed $b) => $a && $b,
            'not' => fn(mixed $a) => !$a,
            'gt' => fn(mixed $a, mixed $b) => $a > $b,
];
    $partials = ['nav-item' => function ($cx, $in, $sp) {return ''.$sp.'<li class="'.((LR::ifvar($in['active'] ?? null, false)) ? 'active' : '').' '.((LR::ifvar($in['disabled'] ?? null, false)) ? 'disabled' : '').'">
'.$sp.'    <a href="'.LR::encq($in['url'] ?? null).'" '.((LR::ifvar($in['newTab'] ?? null, false)) ? 'target="_blank" rel="noopener"' : '').'>
'.$sp.'        '.((LR::ifvar($in['icon'] ?? null, false)) ? '<i class="icon-'.LR::encq($in['icon'] ?? null).'"></i>' : '').'
'.$sp.'        '.LR::encq($in['label'] ?? null).'
'.$sp.'        '.((LR::ifvar($in['badge'] ?? null, false)) ? '<span class="badge">'.LR::encq($in['badge'] ?? null).'</span>' : '').'
'.$sp.'    </a>
'.$sp.''.((LR::ifvar($in['children'] ?? null, false)) ? '        <ul class="dropdown">
'.LR::sec($cx, $in['children'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.'                <li><a href="'.LR::encq($in['url'] ?? null).'">'.LR::encq($in['label'] ?? null).'</a></li>
';}).'        </ul>
'.$sp.'' : '').'</li>
';},
'alert' => function ($cx, $in, $sp) {return ''.$sp.'<div class="alert alert-'.LR::encq($in['type'] ?? null).' '.((LR::ifvar($in['dismissible'] ?? null, false)) ? 'alert-dismissible' : '').'" role="alert">
'.$sp.'    '.((LR::ifvar($in['icon'] ?? null, false)) ? '<i class="icon-'.LR::encq($in['icon'] ?? null).'"></i>' : '').'
'.$sp.'    '.LR::raw($in['message'] ?? null).'
'.$sp.'    '.((LR::ifvar($in['dismissible'] ?? null, false)) ? '<button type="button" class="close" data-dismiss="alert">&times;</button>' : '').'
'.$sp.'</div>
';},
'breadcrumbs' => function ($cx, $in, $sp) {return ''.$sp.''.((LR::ifvar($in['breadcrumbs'] ?? null, false)) ? '    <nav aria-label="breadcrumb">
'.$sp.'        <ol class="breadcrumb">
'.LR::sec($cx, $in['breadcrumbs'] ?? null, ['crumb','idx'], $in, true, function($cx, $in) use (&$sp) {return $sp.'                <li class="breadcrumb-item'.((LR::ifvar($cx->spVars['last'] ?? null, false)) ? ' active' : '').'">
'.$sp.''.((LR::ifvar($cx->spVars['last'] ?? null, false)) ? '                        '.LR::encq($in['crumb']['label'] ?? null).'
'.$sp.'' : '                        <a href="'.LR::encq($in['crumb']['url'] ?? null).'">'.LR::encq($in['crumb']['label'] ?? null).'</a>
'.$sp.'').'                </li>
';}).'        </ol>
'.$sp.'    </nav>
'.$sp.'' : '').'';},
'page-header' => function ($cx, $in, $sp) {return ''.$sp.'<div class="page-header">
'.$sp.'    <h1>
'.$sp.'        '.LR::raw($in['heading'] ?? null).'
'.$sp.'        '.LR::wi($cx, $in['headingBadge'] ?? null, null, $in, function($cx, $in) {return '<span class="badge badge-'.LR::encq($in['type'] ?? null).'">'.LR::encq($in['text'] ?? null).'</span>';}).'
'.$sp.'    </h1>
'.$sp.'    '.((LR::ifvar($in['subheading'] ?? null, false)) ? '<p class="lead">'.LR::encq($in['subheading'] ?? null).'</p>' : '').'
'.$sp.''.((LR::ifvar($in['actions'] ?? null, false)) ? '        <div class="page-actions">
'.LR::sec($cx, $in['actions'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.'                <a href="'.LR::encq($in['url'] ?? null).'" class="btn btn-'.((LR::ifvar($in['primary'] ?? null, false)) ? 'primary' : 'secondary').''.((LR::ifvar($in['size'] ?? null, false)) ? ' btn-'.LR::encq($in['size'] ?? null).'' : '').'">
'.$sp.'                    '.((LR::ifvar($in['icon'] ?? null, false)) ? '<i class="icon-'.LR::encq($in['icon'] ?? null).'"></i> ' : '').''.LR::encq($in['label'] ?? null).'
'.$sp.'                </a>
';}).'        </div>
'.$sp.'' : '').'</div>
';},
'pagination' => function ($cx, $in, $sp) {return ''.$sp.''.((LR::ifvar($in['pagination'] ?? null, false)) ? '    <nav aria-label="'.LR::encq(LR::hbch($cx, 't', [['pagination.label'],[]], $in)).'">
'.$sp.'        <ul class="pagination '.((LR::ifvar($in['pagination']['small'] ?? null, false)) ? 'pagination-sm' : '').'">
'.$sp.'            <li class="page-item'.((!LR::ifvar($in['pagination']['hasPrev'] ?? null, false)) ? ' disabled' : '').'">
'.$sp.'                <a class="page-link" href="'.LR::encq($in['pagination']['prevUrl'] ?? null).'">&laquo; '.LR::encq(LR::hbch($cx, 't', [['pagination.prev'],[]], $in)).'</a>
'.$sp.'            </li>
'.LR::sec($cx, $in['pagination']['pages'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.'                <li class="page-item'.((LR::ifvar($in['active'] ?? null, false)) ? ' active' : '').''.((LR::ifvar($in['ellipsis'] ?? null, false)) ? ' disabled' : '').'">
'.$sp.''.((LR::ifvar($in['ellipsis'] ?? null, false)) ? '                        <span class="page-link">&hellip;</span>
'.$sp.'' : '                        <a class="page-link" href="'.LR::encq($in['url'] ?? null).'">'.LR::encq($in['number'] ?? null).'</a>
'.$sp.'').'                </li>
';}).'            <li class="page-item'.((!LR::ifvar($in['pagination']['hasNext'] ?? null, false)) ? ' disabled' : '').'">
'.$sp.'                <a class="page-link" href="'.LR::encq($in['pagination']['nextUrl'] ?? null).'">'.LR::encq(LR::hbch($cx, 't', [['pagination.next'],[]], $in)).' &raquo;</a>
'.$sp.'            </li>
'.$sp.'        </ul>
'.$sp.'        <p class="pagination-summary">
'.$sp.'            '.LR::encq(LR::hbch($cx, 't', [['pagination.showing'],['start'=>$in['pagination']['start'] ?? null,'end'=>$in['pagination']['end'] ?? null,'total'=>$in['pagination']['total'] ?? null]], $in)).'
'.$sp.'        </p>
'.$sp.'    </nav>
'.$sp.'' : '').'';},
'side-panel' => function ($cx, $in, $sp) {return ''.$sp.'<aside class="side-panel'.((LR::ifvar($in['collapsible'] ?? null, false)) ? ' collapsible'.((LR::ifvar($in['collapsed'] ?? null, false)) ? ' collapsed' : '').'' : '').'" id="panel-'.LR::encq($in['id'] ?? null).'">
'.$sp.'    <div class="panel-header">
'.$sp.'        <h4>'.LR::encq($in['title'] ?? null).'</h4>
'.$sp.'        '.((LR::ifvar($in['collapsible'] ?? null, false)) ? '<button class="toggle" aria-expanded="'.((LR::ifvar($in['collapsed'] ?? null, false)) ? 'false' : 'true').'"></button>' : '').'
'.$sp.'    </div>
'.$sp.'    <div class="panel-body">
'.$sp.''.((LR::ifvar(LR::hbch($cx, 'eq', [[$in['type'] ?? null,'list'],[]], $in), false)) ? '            <ul>
'.$sp.'                '.LR::sec($cx, $in['items'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.'<li>'.((LR::ifvar($in['url'] ?? null, false)) ? '<a href="'.LR::encq($in['url'] ?? null).'">' : '').''.LR::encq($in['label'] ?? null).''.((LR::ifvar($in['url'] ?? null, false)) ? '</a>' : '').''.((LR::ifvar($in['count'] ?? null, false)) ? ' <span class="count">('.LR::encq($in['count'] ?? null).')</span>' : '').'</li>';}).'
'.$sp.'            </ul>
'.$sp.'' : ''.((LR::ifvar(LR::hbch($cx, 'eq', [[$in['type'] ?? null,'stats'],[]], $in), false)) ? '            <dl class="stats">
'.LR::sec($cx, $in['stats'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.'                    <dt>'.LR::encq($in['label'] ?? null).'</dt>
'.$sp.'                    <dd class="'.((LR::ifvar($in['trend'] ?? null, false)) ? 'trend-'.LR::encq($in['trend'] ?? null).'' : '').'">
'.$sp.'                        '.LR::encq($in['value'] ?? null).''.((LR::ifvar($in['unit'] ?? null, false)) ? ' '.LR::encq($in['unit'] ?? null).'' : '').'
'.$sp.'                        '.((LR::ifvar($in['delta'] ?? null, false)) ? '<small class="delta">'.((LR::ifvar(LR::hbch($cx, 'gt', [[$in['delta'] ?? null,0],[]], $in), false)) ? '+' : '').''.LR::encq($in['delta'] ?? null).'</small>' : '').'
'.$sp.'                    </dd>
';}).'            </dl>
'.$sp.'' : ''.((LR::ifvar(LR::hbch($cx, 'eq', [[$in['type'] ?? null,'html'],[]], $in), false)) ? '            '.LR::raw($in['content'] ?? null).'
'.$sp.'' : '').'').'').'    </div>
'.$sp.'</aside>
';},
'footer-col' => function ($cx, $in, $sp) {return ''.$sp.'<div class="footer-col">
'.$sp.'    '.((LR::ifvar($in['heading'] ?? null, false)) ? '<h5>'.LR::encq($in['heading'] ?? null).'</h5>' : '').'
'.$sp.'    <ul>
'.LR::sec($cx, $in['links'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.'            <li><a href="'.LR::encq($in['url'] ?? null).'"'.((LR::ifvar($in['external'] ?? null, false)) ? ' target="_blank" rel="noopener noreferrer"' : '').'>'.LR::encq($in['label'] ?? null).'</a></li>
';}).'    </ul>
'.$sp.'</div>
';}];
    $cx = new RuntimeContext(
        helpers: isset($options['helpers']) ? array_merge($helpers, $options['helpers']) : $helpers,
        partials: isset($options['partials']) ? array_merge($partials, $options['partials']) : $partials,
        scopes: [],
        spVars: isset($options['data']) ? array_merge(['root' => $in], $options['data']) : ['root' => $in],
        blParam: [],
        partialId: 0,
    );
    return '<!DOCTYPE html>
<html lang="'.LR::encq($in['lang'] ?? null).'">
<head>
    <meta charset="UTF-8">
    <title>'.((LR::ifvar($in['pageTitle'] ?? null, false)) ? ''.LR::encq($in['pageTitle'] ?? null).'' : ''.LR::encq($in['siteName'] ?? null).' &mdash; Default Title').'</title>
'.LR::sec($cx, $in['stylesheets'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.'        <link rel="stylesheet" href="'.LR::encq($in['url'] ?? null).'" '.((LR::ifvar($in['media'] ?? null, false)) ? 'media="'.LR::encq($in['media'] ?? null).'"' : '').'>
';}).'</head>
<body class="'.LR::encq($in['bodyClass'] ?? null).' '.((LR::ifvar($in['user']['isAdmin'] ?? null, false)) ? 'is-admin' : '').' '.((!LR::ifvar($in['user']['verified'] ?? null, false)) ? 'unverified' : '').'">

<header id="site-header" '.((LR::ifvar($in['sticky'] ?? null, false)) ? 'data-sticky="true"' : '').'>
    <nav>
        <a class="logo" href="'.LR::encq($in['rootUrl'] ?? null).'">
            '.LR::raw($in['logoHtml'] ?? null).'
            <span>'.LR::encq($in['siteName'] ?? null).'</span>
        </a>

        <ul class="nav-links">
'.LR::sec($cx, $in['navItems'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.''.LR::p($cx, 'nav-item', [[$in],[]], 0, ($sp ?? '') . '                ').'';}).'        </ul>

'.LR::wi($cx, $in['user'] ?? null, null, $in, function($cx, $in) {return '            <div class="user-menu">
                <img src="'.LR::encq($in['avatar'] ?? null).'" alt="'.LR::encq($in['name'] ?? null).'" width="32" height="32">
                <span>'.LR::encq($in['name'] ?? null).'</span>
                <ul class="user-dropdown">
                    <li><a href="/profile/'.LR::encq($in['id'] ?? null).'">'.LR::encq(LR::hbch($cx, 't', [['nav.profile'],[]], $in)).'</a></li>
                    <li><a href="/settings">'.LR::encq(LR::hbch($cx, 't', [['nav.settings'],[]], $in)).'</a></li>
                    '.((LR::ifvar($in['isAdmin'] ?? null, false)) ? '<li><a href="/admin">'.LR::encq(LR::hbch($cx, 't', [['nav.admin'],[]], $in)).'</a></li>' : '').'
                    <li class="divider"></li>
                    <li><a href="/logout">'.LR::encq(LR::hbch($cx, 't', [['nav.logout'],[]], $in)).'</a></li>
                </ul>
            </div>
';}, function($cx, $in) {return '            <a class="btn btn-primary" href="/login">'.LR::encq(LR::hbch($cx, 't', [['nav.login'],[]], $in)).'</a>
';}).'    </nav>
</header>

'.LR::sec($cx, $in['alerts'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.''.LR::p($cx, 'alert', [[$in],[]], 0, ($sp ?? '') . '    ').'';}).'
<main id="main-content">
    <div class="container'.((LR::ifvar($in['fluid'] ?? null, false)) ? '-fluid' : '').'">

'.LR::p($cx, 'breadcrumbs', [[$in],[]], 0, ($sp ?? '') . '        ').'
'.LR::p($cx, 'page-header', [[$in],[]], 0, ($sp ?? '') . '        ').'
'.((LR::ifvar($in['items'] ?? null, false)) ? '            <div class="table-responsive">
                <table class="table table-striped'.((LR::ifvar($in['hoverable'] ?? null, false)) ? ' table-hover' : '').''.((LR::ifvar($in['bordered'] ?? null, false)) ? ' table-bordered' : '').'">
                    <thead>
                    <tr>
'.LR::sec($cx, $in['columns'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.'                            <th '.((LR::ifvar($in['width'] ?? null, false)) ? 'style="width:'.LR::encq($in['width'] ?? null).'"' : '').' class="'.((LR::ifvar($in['sortable'] ?? null, false)) ? 'sortable' : '').''.((LR::ifvar(LR::hbch($cx, 'eq', [[$in['currentSort']['key'] ?? null,$in['key'] ?? null],[]], $in), false)) ? ' sort-'.LR::encq($in['currentSort']['dir'] ?? null).'' : '').'">
'.((LR::ifvar($in['sortable'] ?? null, false)) ? '                                    <a href="'.LR::encq($cx->scopes[count($cx->scopes)-1]['sortBaseUrl'] ?? null).'?sort='.LR::encq($in['key'] ?? null).'&dir='.((LR::ifvar(LR::hbch($cx, 'and', [[LR::hbch($cx, 'eq', [[$cx->scopes[count($cx->scopes)-1]['currentSort']['key'] ?? null,$in['key'] ?? null],[]], $in),LR::hbch($cx, 'eq', [[$cx->scopes[count($cx->scopes)-1]['currentSort']['dir'] ?? null,'asc'],[]], $in)],[]], $in), false)) ? 'desc' : 'asc').'">
                                    '.LR::encq($in['label'] ?? null).'
                                    </a>
' : '                                    '.LR::encq($in['label'] ?? null).'
').'                            </th>
';}).'                        '.((LR::ifvar($in['showActions'] ?? null, false)) ? '<th class="actions-col">'.LR::encq(LR::hbch($cx, 't', [['table.actions'],[]], $in)).'</th>' : '').'
                    </tr>
                    </thead>
                    <tbody>
'.LR::sec($cx, $in['items'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.'                        <tr class="'.((LR::ifvar($in['deleted'] ?? null, false)) ? 'deleted' : '').' '.((LR::ifvar(LR::hbch($cx, 'eq', [[$cx->spVars['index'] ?? null,$cx->scopes[count($cx->scopes)-1]['selectedIndex'] ?? null],[]], $in), false)) ? 'selected' : '').'" data-id="'.LR::encq($in['id'] ?? null).'">
'.LR::sec($cx, $cx->scopes[count($cx->scopes)-1]['columns'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.'                                <td class="col-'.LR::encq($in['key'] ?? null).''.((LR::ifvar($cx->scopes[count($cx->scopes)-1]['highlighted'] ?? null, false)) ? ' highlighted' : '').'">
'.((LR::ifvar(LR::hbch($cx, 'eq', [[$in['type'] ?? null,'boolean'],[]], $in), false)) ? '                                        '.((LR::ifvar(LR::raw($cx->scopes[count($cx->scopes)-1][$in['key'] ?? null] ?? null, 1), false)) ? '<i class="icon-check text-success"></i>' : '<i class="icon-times text-muted"></i>').'
' : ''.((LR::ifvar(LR::hbch($cx, 'eq', [[$in['type'] ?? null,'date'],[]], $in), false)) ? '                                        <time datetime="'.LR::encq(LR::hbch($cx, 'formatDate', [[LR::raw($cx->scopes[count($cx->scopes)-1][$in['key'] ?? null] ?? null, 1),'Y-m-d'],[]], $in)).'">'.LR::encq(LR::hbch($cx, 'formatDate', [[LR::raw($cx->scopes[count($cx->scopes)-1][$in['key'] ?? null] ?? null, 1),$in['format'] ?? null],[]], $in)).'</time>
' : ''.((LR::ifvar(LR::hbch($cx, 'eq', [[$in['type'] ?? null,'currency'],[]], $in), false)) ? '                                        '.LR::encq(LR::hbch($cx, 'formatCurrency', [[LR::raw($cx->scopes[count($cx->scopes)-1][$in['key'] ?? null] ?? null, 1),$cx->scopes[count($cx->scopes)-2]['currency'] ?? null],[]], $in)).'
' : ''.((LR::ifvar(LR::hbch($cx, 'eq', [[$in['type'] ?? null,'link'],[]], $in), false)) ? '                                        <a href="'.LR::encq(LR::hbch($cx, 'replace', [[$in['linkTemplate'] ?? null,'{id}',$cx->scopes[count($cx->scopes)-1]['id'] ?? null],[]], $in)).'">'.LR::encq(LR::encq($cx->scopes[count($cx->scopes)-1][$in['key'] ?? null] ?? null, 1)).'</a>
' : '                                        '.LR::encq(LR::encq($cx->scopes[count($cx->scopes)-1][$in['key'] ?? null] ?? null, 1)).'
').'').'').'').'
                                </td>
';}).''.((LR::ifvar($cx->scopes[count($cx->scopes)-1]['showActions'] ?? null, false)) ? '                                <td class="actions">
'.LR::sec($cx, $cx->scopes[count($cx->scopes)-1]['rowActions'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.''.((!LR::ifvar(LR::hbch($cx, 'and', [[$in['requiresAdmin'] ?? null,LR::hbch($cx, 'not', [[$cx->scopes[count($cx->scopes)-1]['isAdmin'] ?? null],[]], $in)],[]], $in), false)) ? '                                            <a href="'.LR::encq(LR::hbch($cx, 'replace', [[$in['urlTemplate'] ?? null,'{id}',$cx->scopes[count($cx->scopes)-1]['id'] ?? null],[]], $in)).'"
                                               class="btn btn-sm btn-'.LR::encq($in['style'] ?? null).'"
                                               '.((LR::ifvar($in['confirm'] ?? null, false)) ? 'data-confirm="'.LR::encq(LR::hbch($cx, 't', [[$in['confirmKey'] ?? null],[]], $in)).'"' : '').'
                                               title="'.LR::encq(LR::hbch($cx, 't', [[$in['labelKey'] ?? null],[]], $in)).'">
                                                <i class="icon-'.LR::encq($in['icon'] ?? null).'"></i>
                                            </a>
' : '').'';}).'                                </td>
' : '').'                        </tr>
';}, function($cx, $in) {return '                        <tr><td colspan="'.LR::encq($in['columnCount'] ?? null).'" class="empty-message">'.LR::encq(LR::hbch($cx, 't', [['table.empty'],[]], $in)).'</td></tr>
';}).'                    </tbody>
'.((LR::ifvar($in['showTotals'] ?? null, false)) ? '                        <tfoot>
                        <tr class="totals">
'.LR::sec($cx, $in['columns'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.'                                <td>'.((LR::ifvar($in['showTotal'] ?? null, false)) ? ''.LR::encq(LR::hbch($cx, 'formatCurrency', [[LR::raw($cx->scopes[count($cx->scopes)-1]['totals'][$in['key'] ?? null] ?? null, 1),$cx->scopes[count($cx->scopes)-2]['currency'] ?? null],[]], $in)).'' : '').'</td>
';}).'                            '.((LR::ifvar($cx->scopes[count($cx->scopes)-1]['showActions'] ?? null, false)) ? '<td></td>' : '').'
                        </tr>
                        </tfoot>
' : '').'                </table>
            </div>

'.LR::p($cx, 'pagination', [[$in],[]], 0, ($sp ?? '') . '            ').'
' : '
            <div class="empty-state">
                '.((LR::ifvar($in['emptyState']['illustration'] ?? null, false)) ? ''.LR::raw($in['emptyState']['illustration'] ?? null).'' : '').'
                <h3>'.LR::encq($in['emptyState']['heading'] ?? null).'</h3>
                '.((LR::ifvar($in['emptyState']['message'] ?? null, false)) ? '<p>'.LR::encq($in['emptyState']['message'] ?? null).'</p>' : '').'
'.((LR::ifvar($in['emptyState']['action'] ?? null, false)) ? '                    <a href="'.LR::encq($in['emptyState']['action']['url'] ?? null).'" class="btn btn-primary">'.LR::encq($in['emptyState']['action']['label'] ?? null).'</a>
' : '').'            </div>

').'
'.LR::sec($cx, $in['sidePanels'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.''.LR::p($cx, 'side-panel', [[$in],[]], 0, ($sp ?? '') . '            ').'';}).'
    </div>
</main>

<footer id="site-footer">
    <div class="container">
        <div class="footer-cols">
'.LR::sec($cx, $in['footerColumns'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.''.LR::p($cx, 'footer-col', [[$in],[]], 0, ($sp ?? '') . '                ').'';}).'        </div>
        <div class="footer-bottom">
            <p>'.LR::encq($in['copyright'] ?? null).' '.((LR::ifvar($in['showYear'] ?? null, false)) ? ''.LR::encq($in['currentYear'] ?? null).'' : '').' '.LR::encq($in['siteName'] ?? null).'</p>
'.((LR::ifvar($in['social'] ?? null, false)) ? '                <ul class="social-links">
'.LR::sec($cx, $in['social'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.'                        <li><a href="'.LR::encq($in['url'] ?? null).'" aria-label="'.LR::encq($in['name'] ?? null).'"><i class="icon-'.LR::encq($in['icon'] ?? null).'"></i></a></li>
';}).'                </ul>
' : '').'        </div>
    </div>
</footer>

'.LR::sec($cx, $in['scripts'] ?? null, null, $in, true, function($cx, $in) use (&$sp) {return $sp.'    <script src="'.LR::encq($in['url'] ?? null).'"'.((LR::ifvar($in['defer'] ?? null, false)) ? ' defer' : '').''.((LR::ifvar($in['async'] ?? null, false)) ? ' async' : '').''.((LR::ifvar($in['integrity'] ?? null, false)) ? ' integrity="'.LR::encq($in['integrity'] ?? null).'" crossorigin="anonymous"' : '').'></script>
';}).''.((LR::ifvar($in['inlineScript'] ?? null, false)) ? '    <script>
            '.LR::raw($in['inlineScript'] ?? null).'
    </script>
' : '').'
</body>
</html>
';
};
