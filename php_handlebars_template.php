Compiled 1000 times  |  3.15 ms/compile  |    21.1 KB code    |  1.8 MB peak
Executed 1000 times  |  1.39 ms/render   |   139.9 KB output  |  1.9 MB peak
<?php
use DevTheorem\Handlebars\Runtime as LR;
$p0 = function($cx, $in) {return '        <link rel="stylesheet" href="'.LR::encq(LR::cv($in, 'url')).'" '.(LR::ifvar(LR::cv($in, 'media')) ? 'media="'.LR::encq(LR::cv($in, 'media')).'"' : '').'>
';};
$p1 = function($cx, $in) {return LR::p($cx, 'nav-item', $in, [], '                ');};
$p2 = function($cx, $in) {return '            <div class="user-menu">
                <img src="'.LR::encq(LR::cv($in, 'avatar')).'" alt="'.LR::encq(LR::cv($in, 'name')).'" width="32" height="32">
                <span>'.LR::encq(LR::cv($in, 'name')).'</span>
                <ul class="user-dropdown">
                    <li><a href="/profile/'.LR::encq(LR::cv($in, 'id')).'">'.LR::encq(LR::hbch($cx, $cx->helpers['t'], 't', ['nav.profile'], [], $in)).'</a></li>
                    <li><a href="/settings">'.LR::encq(LR::hbch($cx, $cx->helpers['t'], 't', ['nav.settings'], [], $in)).'</a></li>
                    '.(LR::ifvar(LR::cv($in, 'isAdmin')) ? '<li><a href="/admin">'.LR::encq(LR::hbch($cx, $cx->helpers['t'], 't', ['nav.admin'], [], $in)).'</a></li>' : '').'
                    <li class="divider"></li>
                    <li><a href="/logout">'.LR::encq(LR::hbch($cx, $cx->helpers['t'], 't', ['nav.logout'], [], $in)).'</a></li>
                </ul>
            </div>
';};
$p3 = function($cx, $in) {return '            <a class="btn btn-primary" href="/login">'.LR::encq(LR::hbch($cx, $cx->helpers['t'], 't', ['nav.login'], [], $in)).'</a>
';};
$p4 = function($cx, $in) {return LR::p($cx, 'alert', $in, [], '    ');};
$p5 = function($cx, $in) {$sc=count($cx->depths);return '                            <th '.(LR::ifvar(LR::cv($in, 'width')) ? 'style="width:'.LR::encq(LR::cv($in, 'width')).'"' : '').' class="'.(LR::ifvar(LR::cv($in, 'sortable')) ? 'sortable' : '').(LR::ifvar(LR::hbch($cx, $cx->helpers['eq'], 'eq', [$in['currentSort']['key'] ?? null,$in['key'] ?? null], [], $in)) ? ' sort-'.LR::encq(LR::dv($in['currentSort']['dir'] ?? null)) : '').'">
'.(LR::ifvar(LR::cv($in, 'sortable')) ? '                                    <a href="'.LR::encq(LR::dv($cx->depths[$sc-1]['sortBaseUrl'] ?? null)).'?sort='.LR::encq(LR::cv($in, 'key')).'&dir='.(LR::ifvar(LR::hbch($cx, $cx->helpers['and'], 'and', [LR::hbch($cx, $cx->helpers['eq'], 'eq', [$cx->depths[$sc-1]['currentSort']['key'] ?? null,$in['key'] ?? null], [], $in),LR::hbch($cx, $cx->helpers['eq'], 'eq', [$cx->depths[$sc-1]['currentSort']['dir'] ?? null,'asc'], [], $in)], [], $in)) ? 'desc' : 'asc').'">
                                    '.LR::encq(LR::cv($in, 'label')).'
                                    </a>
' : '                                    '.LR::encq(LR::cv($in, 'label')).'
').'                            </th>
';};
$p6 = function($cx, $in, array $blockParams = []) {$sc=count($cx->depths);return '                                <td class="col-'.LR::encq(LR::cv($in, 'key')).(LR::ifvar($blockParams[0][0]['highlighted'] ?? null) ? ' highlighted' : '').'">
'.(LR::ifvar(LR::hbch($cx, $cx->helpers['eq'], 'eq', [$in['type'] ?? null,'boolean'], [], $in)) ? '                                        '.(LR::ifvar(LR::hbch($cx, $cx->helpers['lookup'], 'lookup', [$blockParams[0][0],$in['key'] ?? null], [], $in)) ? '<i class="icon-check text-success"></i>' : '<i class="icon-times text-muted"></i>').'
' : (LR::ifvar(LR::hbch($cx, $cx->helpers['eq'], 'eq', [$in['type'] ?? null,'date'], [], $in)) ? '                                        <time datetime="'.LR::encq(LR::hbch($cx, $cx->helpers['formatDate'], 'formatDate', [LR::hbch($cx, $cx->helpers['lookup'], 'lookup', [$blockParams[0][0],$in['key'] ?? null], [], $in),'Y-m-d'], [], $in)).'">'.LR::encq(LR::hbch($cx, $cx->helpers['formatDate'], 'formatDate', [LR::hbch($cx, $cx->helpers['lookup'], 'lookup', [$blockParams[0][0],$in['key'] ?? null], [], $in),$in['format'] ?? null], [], $in)).'</time>
' : (LR::ifvar(LR::hbch($cx, $cx->helpers['eq'], 'eq', [$in['type'] ?? null,'currency'], [], $in)) ? '                                        '.LR::encq(LR::hbch($cx, $cx->helpers['formatCurrency'], 'formatCurrency', [LR::hbch($cx, $cx->helpers['lookup'], 'lookup', [$blockParams[0][0],$in['key'] ?? null], [], $in),$cx->depths[$sc-1]['currency'] ?? null], [], $in)).'
' : (LR::ifvar(LR::hbch($cx, $cx->helpers['eq'], 'eq', [$in['type'] ?? null,'link'], [], $in)) ? '                                        <a href="'.LR::encq(LR::hbch($cx, $cx->helpers['replace'], 'replace', [$in['linkTemplate'] ?? null,'{id}',$blockParams[0][0]['id'] ?? null], [], $in)).'">'.LR::encq(LR::hbch($cx, $cx->helpers['lookup'], 'lookup', [$blockParams[0][0],$in['key'] ?? null], [], $in)).'</a>
' : '                                        '.LR::encq(LR::hbch($cx, $cx->helpers['lookup'], 'lookup', [$blockParams[0][0],$in['key'] ?? null], [], $in)).'
')))).'                                </td>
';};
$p7 = function($cx, $in, array $blockParams = []) {return (!LR::ifvar(LR::hbch($cx, $cx->helpers['and'], 'and', [$in['requiresAdmin'] ?? null,LR::hbch($cx, $cx->helpers['not'], 'not', [$blockParams[0][0]['isAdmin'] ?? null], [], $in)], [], $in)) ? '                                            <a href="'.LR::encq(LR::hbch($cx, $cx->helpers['replace'], 'replace', [$in['urlTemplate'] ?? null,'{id}',$blockParams[0][0]['id'] ?? null], [], $in)).'"
                                               class="btn btn-sm btn-'.LR::encq(LR::cv($in, 'style')).'"
                                               '.(LR::ifvar(LR::cv($in, 'confirm')) ? 'data-confirm="'.LR::encq(LR::hbch($cx, $cx->helpers['t'], 't', [$in['confirmKey'] ?? null], [], $in)).'"' : '').'
                                               title="'.LR::encq(LR::hbch($cx, $cx->helpers['t'], 't', [$in['labelKey'] ?? null], [], $in)).'">
                                                <i class="icon-'.LR::encq(LR::cv($in, 'icon')).'"></i>
                                            </a>
' : '');};
$p8 = function($cx, $in, array $blockParams = []) use ($p6, $p7) {$sc=count($cx->depths);return '                        <tr class="'.(LR::ifvar($blockParams[0][0]['deleted'] ?? null) ? 'deleted' : '').' '.(LR::ifvar(LR::hbch($cx, $cx->helpers['eq'], 'eq', [$cx->data['index'] ?? null,$cx->depths[$sc-1]['selectedIndex'] ?? null], [], $in)) ? 'selected' : '').'" data-id="'.LR::encq(LR::dv($blockParams[0][0]['id'] ?? null)).'">
'.LR::hbbch($cx, $cx->helpers['each'], 'each', [$cx->depths[$sc-1]['columns'] ?? null], [], $in, $p6, null, $blockParams, 0).(LR::ifvar($cx->depths[$sc-1]['showActions'] ?? null) ? '                                <td class="actions">
'.LR::hbbch($cx, $cx->helpers['each'], 'each', [$cx->depths[$sc-1]['rowActions'] ?? null], [], $in, $p7, null, $blockParams, 0).'                                </td>
' : '').'                        </tr>
';};
$p9 = function($cx, $in) {return '                        <tr><td colspan="'.LR::encq(LR::cv($in, 'columnCount')).'" class="empty-message">'.LR::encq(LR::hbch($cx, $cx->helpers['t'], 't', ['table.empty'], [], $in)).'</td></tr>
';};
$p10 = function($cx, $in) {$sc=count($cx->depths);return '                                <td>'.(LR::ifvar(LR::cv($in, 'showTotal')) ? LR::encq(LR::hbch($cx, $cx->helpers['formatCurrency'], 'formatCurrency', [LR::hbch($cx, $cx->helpers['lookup'], 'lookup', [$cx->depths[$sc-1]['totals'] ?? null,$in['key'] ?? null], [], $in),$cx->depths[$sc-2]['currency'] ?? null], [], $in)) : '').'</td>
';};
$p11 = function($cx, $in) {return LR::p($cx, 'side-panel', $in, [], '            ');};
$p12 = function($cx, $in) {return LR::p($cx, 'footer-col', $in, [], '                ');};
$p13 = function($cx, $in) {return '                        <li><a href="'.LR::encq(LR::cv($in, 'url')).'" aria-label="'.LR::encq(LR::cv($in, 'name')).'"><i class="icon-'.LR::encq(LR::cv($in, 'icon')).'"></i></a></li>
';};
$p14 = function($cx, $in) {return '    <script src="'.LR::encq(LR::cv($in, 'url')).'"'.(LR::ifvar(LR::cv($in, 'defer')) ? ' defer' : '').(LR::ifvar(LR::cv($in, 'async')) ? ' async' : '').(LR::ifvar(LR::cv($in, 'integrity')) ? ' integrity="'.LR::encq(LR::cv($in, 'integrity')).'" crossorigin="anonymous"' : '').'></script>
';};
return function (mixed $in = null, array $options = []) use ($p0, $p1, $p2, $p3, $p4, $p5, $p8, $p9, $p10, $p11, $p12, $p13, $p14) {
 $cx = LR::createContext($in, $options, []);
 $in = &$cx->data['root'];
 return '<!DOCTYPE html>
<html lang="'.LR::encq(LR::cv($in, 'lang')).'">
<head>
    <meta charset="UTF-8">
    <title>'.(LR::ifvar(LR::cv($in, 'pageTitle')) ? LR::encq(LR::cv($in, 'pageTitle')) : LR::encq(LR::cv($in, 'siteName')).' &mdash; Default Title').'</title>
'.LR::hbbch($cx, $cx->helpers['each'], 'each', [$in['stylesheets'] ?? null], [], $in, $p0, null).'</head>
<body class="'.LR::encq(LR::cv($in, 'bodyClass')).' '.(LR::ifvar($in['user']['isAdmin'] ?? null) ? 'is-admin' : '').' '.(!LR::ifvar($in['user']['verified'] ?? null) ? 'unverified' : '').'">

'.'<header id="site-header" '.(LR::ifvar(LR::cv($in, 'sticky')) ? 'data-sticky="true"' : '').'>
    <nav>
        <a class="logo" href="'.LR::encq(LR::cv($in, 'rootUrl')).'">
            '.LR::raw(LR::cv($in, 'logoHtml')).'
            <span>'.LR::encq(LR::cv($in, 'siteName')).'</span>
        </a>

        <ul class="nav-links">
'.LR::hbbch($cx, $cx->helpers['each'], 'each', [$in['navItems'] ?? null], [], $in, $p1, null).'        </ul>

'.LR::hbbch($cx, $cx->helpers['with'], 'with', [$in['user'] ?? null], [], $in, $p2, $p3).'    </nav>
</header>

'.LR::hbbch($cx, $cx->helpers['each'], 'each', [$in['alerts'] ?? null], [], $in, $p4, null).'
'.'<main id="main-content">
    <div class="container'.(LR::ifvar(LR::cv($in, 'fluid')) ? '-fluid' : '').'">

'.LR::p($cx, 'breadcrumbs', $in, [], '        ').'
'.LR::p($cx, 'page-header', $in, [], '        ').'
'.(LR::ifvar(LR::cv($in, 'items')) ? '            <div class="table-responsive">
                <table class="table table-striped'.(LR::ifvar(LR::cv($in, 'hoverable')) ? ' table-hover' : '').(LR::ifvar(LR::cv($in, 'bordered')) ? ' table-bordered' : '').'">
                    <thead>
                    <tr>
'.LR::hbbch($cx, $cx->helpers['each'], 'each', [$in['columns'] ?? null], [], $in, $p5, null).'                        '.(LR::ifvar(LR::cv($in, 'showActions')) ? '<th class="actions-col">'.LR::encq(LR::hbch($cx, $cx->helpers['t'], 't', ['table.actions'], [], $in)).'</th>' : '').'
                    </tr>
                    </thead>
                    <tbody>
'.LR::hbbch($cx, $cx->helpers['each'], 'each', [$in['items'] ?? null], [], $in, $p8, $p9, [], 2).'                    </tbody>
'.(LR::ifvar(LR::cv($in, 'showTotals')) ? '                        <tfoot>
                        <tr class="totals">
'.LR::hbbch($cx, $cx->helpers['each'], 'each', [$in['columns'] ?? null], [], $in, $p10, null).'                            '.(LR::ifvar($cx->depths[count($cx->depths)-1]['showActions'] ?? null) ? '<td></td>' : '').'
                        </tr>
                        </tfoot>
' : '').'                </table>
            </div>

'.LR::p($cx, 'pagination', $in, [], '            ').'
' : '
'.'            <div class="empty-state">
                '.(LR::ifvar($in['emptyState']['illustration'] ?? null) ? LR::raw(LR::dv($in['emptyState']['illustration'] ?? null)) : '').'
                <h3>'.LR::encq(LR::dv($in['emptyState']['heading'] ?? null)).'</h3>
                '.(LR::ifvar($in['emptyState']['message'] ?? null) ? '<p>'.LR::encq(LR::dv($in['emptyState']['message'] ?? null)).'</p>' : '').'
'.(LR::ifvar($in['emptyState']['action'] ?? null) ? '                    <a href="'.LR::encq(LR::dv($in['emptyState']['action']['url'] ?? null)).'" class="btn btn-primary">'.LR::encq(LR::dv($in['emptyState']['action']['label'] ?? null)).'</a>
' : '').'            </div>

').'
'.LR::hbbch($cx, $cx->helpers['each'], 'each', [$in['sidePanels'] ?? null], [], $in, $p11, null).'
    </div>
</main>

'.'<footer id="site-footer">
    <div class="container">
        <div class="footer-cols">
'.LR::hbbch($cx, $cx->helpers['each'], 'each', [$in['footerColumns'] ?? null], [], $in, $p12, null).'        </div>
        <div class="footer-bottom">
            <p>'.LR::encq(LR::cv($in, 'copyright')).' '.(LR::ifvar(LR::cv($in, 'showYear')) ? LR::encq(LR::cv($in, 'currentYear')) : '').' '.LR::encq(LR::cv($in, 'siteName')).'</p>
'.(LR::ifvar(LR::cv($in, 'social')) ? '                <ul class="social-links">
'.LR::hbbch($cx, $cx->helpers['each'], 'each', [$in['social'] ?? null], [], $in, $p13, null).'                </ul>
' : '').'        </div>
    </div>
</footer>

'.LR::hbbch($cx, $cx->helpers['each'], 'each', [$in['scripts'] ?? null], [], $in, $p14, null).(LR::ifvar(LR::cv($in, 'inlineScript')) ? '    <script>
            '.LR::raw(LR::cv($in, 'inlineScript')).'
    </script>
' : '').'
</body>
</html>
';
};
