Compiled 1000 times in 3.800 s  |  3.800 ms/compile  |  25.3 KB code  (7 partials)
Executed 1000 times in 3.410 s  |  3.410 ms/render   |  139.9 KB output
<?php

use DevTheorem\Handlebars\Runtime as LR;
return function (mixed $in = null, array $options = []) {
 $cx = LR::createContext($in, $options, []);
 $in = &$cx->data['root'];
 return '<!DOCTYPE html>
<html lang="'.LR::encq(LR::hv($cx, 'lang', $in)).'">
<head>
    <meta charset="UTF-8">
    <title>'.LR::ifblock($cx, $in['pageTitle'] ?? null, $in, function($cx, $in) {return ''.LR::encq(LR::hv($cx, 'pageTitle', $in)).'';}, function($cx, $in) {return ''.LR::encq(LR::hv($cx, 'siteName', $in)).' &mdash; Default Title';}).'</title>
'.LR::hbbch($cx, 'each', [$in['stylesheets'] ?? null], [], [], $in, function($cx, $in) {return '        <link rel="stylesheet" href="'.LR::encq(LR::hv($cx, 'url', $in)).'" '.LR::ifblock($cx, $in['media'] ?? null, $in, function($cx, $in) {return 'media="'.LR::encq(LR::hv($cx, 'media', $in)).'"';}, function($cx, $in) {return '';}).'>
';}, null).'</head>
<body class="'.LR::encq(LR::hv($cx, 'bodyClass', $in)).' '.LR::ifblock($cx, $in['user']['isAdmin'] ?? null, $in, function($cx, $in) {return 'is-admin';}, function($cx, $in) {return '';}).' '.LR::unlessblock($cx, $in['user']['verified'] ?? null, $in, function($cx, $in) {return 'unverified';}, function($cx, $in) {return '';}).'">

<header id="site-header" '.LR::ifblock($cx, $in['sticky'] ?? null, $in, function($cx, $in) {return 'data-sticky="true"';}, function($cx, $in) {return '';}).'>
    <nav>
        <a class="logo" href="'.LR::encq(LR::hv($cx, 'rootUrl', $in)).'">
            '.LR::raw(LR::hv($cx, 'logoHtml', $in)).'
            <span>'.LR::encq(LR::hv($cx, 'siteName', $in)).'</span>
        </a>

        <ul class="nav-links">
'.LR::hbbch($cx, 'each', [$in['navItems'] ?? null], [], [], $in, function($cx, $in) {return ''.LR::p($cx, 'nav-item', [[$in],[]], 0, '                ').'';}, null).'        </ul>

'.LR::ifblock($cx, $in['user'] ?? null, $in, function($cx, $in) {return '            <div class="user-menu">
                <img src="'.LR::encq(LR::dv($in['user']['avatar'] ?? null)).'" alt="'.LR::encq(LR::dv($in['user']['name'] ?? null)).'" width="32" height="32">
                <span>'.LR::encq(LR::dv($in['user']['name'] ?? null)).'</span>
                <ul class="user-dropdown">
                    <li><a href="/profile/'.LR::encq(LR::dv($in['user']['id'] ?? null)).'">'.LR::encq(LR::dynhbch($cx, 't', ['nav.profile'], [], $in)).'</a></li>
                    <li><a href="/settings">'.LR::encq(LR::dynhbch($cx, 't', ['nav.settings'], [], $in)).'</a></li>
                    '.LR::ifblock($cx, $in['user']['isAdmin'] ?? null, $in, function($cx, $in) {return '<li><a href="/admin">'.LR::encq(LR::dynhbch($cx, 't', ['nav.admin'], [], $in)).'</a></li>';}, function($cx, $in) {return '';}).'
                    <li class="divider"></li>
                    <li><a href="/logout">'.LR::encq(LR::dynhbch($cx, 't', ['nav.logout'], [], $in)).'</a></li>
                </ul>
            </div>
';}, function($cx, $in) {return '            <a class="btn btn-primary" href="/login">'.LR::encq(LR::dynhbch($cx, 't', ['nav.login'], [], $in)).'</a>
';}).'    </nav>
</header>

'.LR::hbbch($cx, 'each', [$in['alerts'] ?? null], [], [], $in, function($cx, $in) {return ''.LR::p($cx, 'alert', [[$in],[]], 0, '    ').'';}, null).'
<main id="main-content">
    <div class="container'.LR::ifblock($cx, $in['fluid'] ?? null, $in, function($cx, $in) {return '-fluid';}, function($cx, $in) {return '';}).'">

'.LR::p($cx, 'breadcrumbs', [[$in],[]], 0, '        ').'
'.LR::p($cx, 'page-header', [[$in],[]], 0, '        ').'
'.LR::ifblock($cx, $in['items'] ?? null, $in, function($cx, $in) {return '            <div class="table-responsive">
                <table class="table table-striped'.LR::ifblock($cx, $in['hoverable'] ?? null, $in, function($cx, $in) {return ' table-hover';}, function($cx, $in) {return '';}).''.LR::ifblock($cx, $in['bordered'] ?? null, $in, function($cx, $in) {return ' table-bordered';}, function($cx, $in) {return '';}).'">
                    <thead>
                    <tr>
'.LR::hbbch($cx, 'each', [$in['columns'] ?? null], [], [], $in, function($cx, $in) {return '                            <th '.LR::ifblock($cx, $in['width'] ?? null, $in, function($cx, $in) {return 'style="width:'.LR::encq(LR::hv($cx, 'width', $in)).'"';}, function($cx, $in) {return '';}).' class="'.LR::ifblock($cx, $in['sortable'] ?? null, $in, function($cx, $in) {return 'sortable';}, function($cx, $in) {return '';}).''.LR::ifblock($cx, LR::dynhbch($cx, 'eq', [$in['currentSort']['key'] ?? null,$in['key'] ?? null], [], $in), $in, function($cx, $in) {return ' sort-'.LR::encq(LR::dv($in['currentSort']['dir'] ?? null)).'';}, function($cx, $in) {return '';}).'">
'.LR::ifblock($cx, $in['sortable'] ?? null, $in, function($cx, $in) {return '                                    <a href="'.LR::encq(LR::dv($cx->scopes[count($cx->scopes)-1]['sortBaseUrl'] ?? null)).'?sort='.LR::encq(LR::hv($cx, 'key', $in)).'&dir='.LR::ifblock($cx, LR::dynhbch($cx, 'and', [LR::dynhbch($cx, 'eq', [$cx->scopes[count($cx->scopes)-1]['currentSort']['key'] ?? null,$in['key'] ?? null], [], $in),LR::dynhbch($cx, 'eq', [$cx->scopes[count($cx->scopes)-1]['currentSort']['dir'] ?? null,'asc'], [], $in)], [], $in), $in, function($cx, $in) {return 'desc';}, function($cx, $in) {return 'asc';}).'">
                                    '.LR::encq(LR::hv($cx, 'label', $in)).'
                                    </a>
';}, function($cx, $in) {return '                                    '.LR::encq(LR::hv($cx, 'label', $in)).'
';}).'                            </th>
';}, null).'                        '.LR::ifblock($cx, $in['showActions'] ?? null, $in, function($cx, $in) {return '<th class="actions-col">'.LR::encq(LR::dynhbch($cx, 't', ['table.actions'], [], $in)).'</th>';}, function($cx, $in) {return '';}).'
                    </tr>
                    </thead>
                    <tbody>
'.LR::hbbch($cx, 'each', [$in['items'] ?? null], [], ['item','i'], $in, function($cx, $in) {return '                        <tr class="'.LR::ifblock($cx, $cx->blParam[0]['item']['deleted'] ?? null, $in, function($cx, $in) {return 'deleted';}, function($cx, $in) {return '';}).' '.LR::ifblock($cx, LR::dynhbch($cx, 'eq', [$cx->data['index'] ?? null,$cx->scopes[count($cx->scopes)-1]['selectedIndex'] ?? null], [], $in), $in, function($cx, $in) {return 'selected';}, function($cx, $in) {return '';}).'" data-id="'.LR::encq(LR::dv($cx->blParam[0]['item']['id'] ?? null)).'">
'.LR::hbbch($cx, 'each', [$cx->scopes[count($cx->scopes)-1]['columns'] ?? null], [], [], $in, function($cx, $in) {return '                                <td class="col-'.LR::encq(LR::hv($cx, 'key', $in)).''.LR::ifblock($cx, $cx->blParam[0]['item']['highlighted'] ?? null, $in, function($cx, $in) {return ' highlighted';}, function($cx, $in) {return '';}).'">
'.LR::ifblock($cx, LR::dynhbch($cx, 'eq', [$in['type'] ?? null,'boolean'], [], $in), $in, function($cx, $in) {return '                                        '.LR::ifblock($cx, LR::hbch($cx, 'lookup', [$cx->blParam[0]['item'] ?? null,$in['key'] ?? null], [], $in), $in, function($cx, $in) {return '<i class="icon-check text-success"></i>';}, function($cx, $in) {return '<i class="icon-times text-muted"></i>';}).'
';}, function($cx, $in) {return ''.LR::ifblock($cx, LR::dynhbch($cx, 'eq', [$in['type'] ?? null,'date'], [], $in), $in, function($cx, $in) {return '                                        <time datetime="'.LR::encq(LR::dynhbch($cx, 'formatDate', [LR::hbch($cx, 'lookup', [$cx->blParam[0]['item'] ?? null,$in['key'] ?? null], [], $in),'Y-m-d'], [], $in)).'">'.LR::encq(LR::dynhbch($cx, 'formatDate', [LR::hbch($cx, 'lookup', [$cx->blParam[0]['item'] ?? null,$in['key'] ?? null], [], $in),$in['format'] ?? null], [], $in)).'</time>
';}, function($cx, $in) {return ''.LR::ifblock($cx, LR::dynhbch($cx, 'eq', [$in['type'] ?? null,'currency'], [], $in), $in, function($cx, $in) {return '                                        '.LR::encq(LR::dynhbch($cx, 'formatCurrency', [LR::hbch($cx, 'lookup', [$cx->blParam[0]['item'] ?? null,$in['key'] ?? null], [], $in),$cx->scopes[count($cx->scopes)-1]['currency'] ?? null], [], $in)).'
';}, function($cx, $in) {return ''.LR::ifblock($cx, LR::dynhbch($cx, 'eq', [$in['type'] ?? null,'link'], [], $in), $in, function($cx, $in) {return '                                        <a href="'.LR::encq(LR::dynhbch($cx, 'replace', [$in['linkTemplate'] ?? null,'{id}',$cx->blParam[0]['item']['id'] ?? null], [], $in)).'">'.LR::encq(LR::hbch($cx, 'lookup', [$cx->blParam[0]['item'] ?? null,$in['key'] ?? null], [], $in)).'</a>
';}, function($cx, $in) {return '                                        '.LR::encq(LR::hbch($cx, 'lookup', [$cx->blParam[0]['item'] ?? null,$in['key'] ?? null], [], $in)).'
';}).'';}).'';}).'';}).'                                </td>
';}, null).''.LR::ifblock($cx, $cx->scopes[count($cx->scopes)-1]['showActions'] ?? null, $in, function($cx, $in) {return '                                <td class="actions">
'.LR::hbbch($cx, 'each', [$cx->scopes[count($cx->scopes)-1]['rowActions'] ?? null], [], [], $in, function($cx, $in) {return ''.LR::unlessblock($cx, LR::dynhbch($cx, 'and', [$in['requiresAdmin'] ?? null,LR::dynhbch($cx, 'not', [$cx->blParam[0]['item']['isAdmin'] ?? null], [], $in)], [], $in), $in, function($cx, $in) {return '                                            <a href="'.LR::encq(LR::dynhbch($cx, 'replace', [$in['urlTemplate'] ?? null,'{id}',$cx->blParam[0]['item']['id'] ?? null], [], $in)).'"
                                               class="btn btn-sm btn-'.LR::encq(LR::hv($cx, 'style', $in)).'"
                                               '.LR::ifblock($cx, $in['confirm'] ?? null, $in, function($cx, $in) {return 'data-confirm="'.LR::encq(LR::dynhbch($cx, 't', [$in['confirmKey'] ?? null], [], $in)).'"';}, function($cx, $in) {return '';}).'
                                               title="'.LR::encq(LR::dynhbch($cx, 't', [$in['labelKey'] ?? null], [], $in)).'">
                                                <i class="icon-'.LR::encq(LR::hv($cx, 'icon', $in)).'"></i>
                                            </a>
';}, function($cx, $in) {return '';}).'';}, null).'                                </td>
';}, function($cx, $in) {return '';}).'                        </tr>
';}, function($cx, $in) {return '                        <tr><td colspan="'.LR::encq(LR::hv($cx, 'columnCount', $in)).'" class="empty-message">'.LR::encq(LR::dynhbch($cx, 't', ['table.empty'], [], $in)).'</td></tr>
';}).'                    </tbody>
'.LR::ifblock($cx, $in['showTotals'] ?? null, $in, function($cx, $in) {return '                        <tfoot>
                        <tr class="totals">
'.LR::hbbch($cx, 'each', [$in['columns'] ?? null], [], [], $in, function($cx, $in) {return '                                <td>'.LR::ifblock($cx, $in['showTotal'] ?? null, $in, function($cx, $in) {return ''.LR::encq(LR::dynhbch($cx, 'formatCurrency', [LR::hbch($cx, 'lookup', [$cx->scopes[count($cx->scopes)-1]['totals'] ?? null,$in['key'] ?? null], [], $in),$cx->scopes[count($cx->scopes)-2]['currency'] ?? null], [], $in)).'';}, function($cx, $in) {return '';}).'</td>
';}, null).'                            '.LR::ifblock($cx, $cx->scopes[count($cx->scopes)-1]['showActions'] ?? null, $in, function($cx, $in) {return '<td></td>';}, function($cx, $in) {return '';}).'
                        </tr>
                        </tfoot>
';}, function($cx, $in) {return '';}).'                </table>
            </div>

'.LR::p($cx, 'pagination', [[$in],[]], 0, '            ').'
';}, function($cx, $in) {return '
            <div class="empty-state">
                '.LR::ifblock($cx, $in['emptyState']['illustration'] ?? null, $in, function($cx, $in) {return ''.LR::raw(LR::dv($in['emptyState']['illustration'] ?? null)).'';}, function($cx, $in) {return '';}).'
                <h3>'.LR::encq(LR::dv($in['emptyState']['heading'] ?? null)).'</h3>
                '.LR::ifblock($cx, $in['emptyState']['message'] ?? null, $in, function($cx, $in) {return '<p>'.LR::encq(LR::dv($in['emptyState']['message'] ?? null)).'</p>';}, function($cx, $in) {return '';}).'
'.LR::ifblock($cx, $in['emptyState']['action'] ?? null, $in, function($cx, $in) {return '                    <a href="'.LR::encq(LR::dv($in['emptyState']['action']['url'] ?? null)).'" class="btn btn-primary">'.LR::encq(LR::dv($in['emptyState']['action']['label'] ?? null)).'</a>
';}, function($cx, $in) {return '';}).'            </div>

';}).'
'.LR::hbbch($cx, 'each', [$in['sidePanels'] ?? null], [], [], $in, function($cx, $in) {return ''.LR::p($cx, 'side-panel', [[$in],[]], 0, '            ').'';}, null).'
    </div>
</main>

<footer id="site-footer">
    <div class="container">
        <div class="footer-cols">
'.LR::hbbch($cx, 'each', [$in['footerColumns'] ?? null], [], [], $in, function($cx, $in) {return ''.LR::p($cx, 'footer-col', [[$in],[]], 0, '                ').'';}, null).'        </div>
        <div class="footer-bottom">
            <p>'.LR::encq(LR::hv($cx, 'copyright', $in)).' '.LR::ifblock($cx, $in['showYear'] ?? null, $in, function($cx, $in) {return ''.LR::encq(LR::hv($cx, 'currentYear', $in)).'';}, function($cx, $in) {return '';}).' '.LR::encq(LR::hv($cx, 'siteName', $in)).'</p>
'.LR::ifblock($cx, $in['social'] ?? null, $in, function($cx, $in) {return '                <ul class="social-links">
'.LR::hbbch($cx, 'each', [$in['social'] ?? null], [], [], $in, function($cx, $in) {return '                        <li><a href="'.LR::encq(LR::hv($cx, 'url', $in)).'" aria-label="'.LR::encq(LR::hv($cx, 'name', $in)).'"><i class="icon-'.LR::encq(LR::hv($cx, 'icon', $in)).'"></i></a></li>
';}, null).'                </ul>
';}, function($cx, $in) {return '';}).'        </div>
    </div>
</footer>

'.LR::hbbch($cx, 'each', [$in['scripts'] ?? null], [], [], $in, function($cx, $in) {return '    <script src="'.LR::encq(LR::hv($cx, 'url', $in)).'"'.LR::ifblock($cx, $in['defer'] ?? null, $in, function($cx, $in) {return ' defer';}, function($cx, $in) {return '';}).''.LR::ifblock($cx, $in['async'] ?? null, $in, function($cx, $in) {return ' async';}, function($cx, $in) {return '';}).''.LR::ifblock($cx, $in['integrity'] ?? null, $in, function($cx, $in) {return ' integrity="'.LR::encq(LR::hv($cx, 'integrity', $in)).'" crossorigin="anonymous"';}, function($cx, $in) {return '';}).'></script>
';}, null).''.LR::ifblock($cx, $in['inlineScript'] ?? null, $in, function($cx, $in) {return '    <script>
            '.LR::raw(LR::hv($cx, 'inlineScript', $in)).'
    </script>
';}, function($cx, $in) {return '';}).'
</body>
</html>
';
};
