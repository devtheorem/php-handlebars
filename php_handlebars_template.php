Compiled 1000 times in 4.080 s  |  4.080 ms/compile  |  19.2 KB code  (7 partials)
Executed 1000 times in 1.479 s  |  1.479 ms/render   |  139.9 KB output
<?php
use DevTheorem\Handlebars\Runtime as LR;
return function (mixed $in = null, array $options = []) {
 $cx = LR::createContext($in, $options, []);
 $cx->frame['root'] = &$cx->data['root'];
 $in = &$cx->data['root'];
 return '<!DOCTYPE html>
<html lang="'.LR::encq(LR::cv($in, 'lang')).'">
<head>
    <meta charset="UTF-8">
    <title>'.(LR::ifvar(LR::cv($in, 'pageTitle')) ? LR::encq(LR::cv($in, 'pageTitle')) : LR::encq(LR::cv($in, 'siteName')).' &mdash; Default Title').'</title>
'.LR::each($cx, $in['stylesheets'] ?? null, $in, function($cx, $in) {return '        <link rel="stylesheet" href="'.LR::encq(LR::cv($in, 'url')).'" '.(LR::ifvar(LR::cv($in, 'media')) ? 'media="'.LR::encq(LR::cv($in, 'media')).'"' : '').'>
';}, null).'</head>
<body class="'.LR::encq(LR::cv($in, 'bodyClass')).' '.(LR::ifvar(LR::cv($in, 'user')) ? 'is-admin' : '').' '.(!LR::ifvar(LR::cv($in, 'user')) ? 'unverified' : '').'">

'.'<header id="site-header" '.(LR::ifvar(LR::cv($in, 'sticky')) ? 'data-sticky="true"' : '').'>
    <nav>
        <a class="logo" href="'.LR::encq(LR::cv($in, 'rootUrl')).'">
            '.LR::raw(LR::cv($in, 'logoHtml')).'
            <span>'.LR::encq(LR::cv($in, 'siteName')).'</span>
        </a>

        <ul class="nav-links">
'.LR::each($cx, $in['navItems'] ?? null, $in, function($cx, $in) {return LR::p($cx, 'nav-item', [[$in],[]], 0, '                ');}, null).'        </ul>

'.(LR::ifvar(LR::cv($in, 'user')) ? '            <div class="user-menu">
                <img src="'.LR::encq($in['user']['avatar'] ?? null).'" alt="'.LR::encq($in['user']['name'] ?? null).'" width="32" height="32">
                <span>'.LR::encq($in['user']['name'] ?? null).'</span>
                <ul class="user-dropdown">
                    <li><a href="/profile/'.LR::encq($in['user']['id'] ?? null).'">'.LR::encq(LR::hbch($cx, 't', ['nav.profile'], [], $in)).'</a></li>
                    <li><a href="/settings">'.LR::encq(LR::hbch($cx, 't', ['nav.settings'], [], $in)).'</a></li>
                    '.(LR::ifvar(LR::cv($in, 'user')) ? '<li><a href="/admin">'.LR::encq(LR::hbch($cx, 't', ['nav.admin'], [], $in)).'</a></li>' : '').'
                    <li class="divider"></li>
                    <li><a href="/logout">'.LR::encq(LR::hbch($cx, 't', ['nav.logout'], [], $in)).'</a></li>
                </ul>
            </div>
' : '            <a class="btn btn-primary" href="/login">'.LR::encq(LR::hbch($cx, 't', ['nav.login'], [], $in)).'</a>
').'    </nav>
</header>

'.LR::each($cx, $in['alerts'] ?? null, $in, function($cx, $in) {return LR::p($cx, 'alert', [[$in],[]], 0, '    ');}, null).'
'.'<main id="main-content">
    <div class="container'.(LR::ifvar(LR::cv($in, 'fluid')) ? '-fluid' : '').'">

'.LR::p($cx, 'breadcrumbs', [[$in],[]], 0, '        ').'
'.LR::p($cx, 'page-header', [[$in],[]], 0, '        ').'
'.(LR::ifvar(LR::cv($in, 'items')) ? '            <div class="table-responsive">
                <table class="table table-striped'.(LR::ifvar(LR::cv($in, 'hoverable')) ? ' table-hover' : '').(LR::ifvar(LR::cv($in, 'bordered')) ? ' table-bordered' : '').'">
                    <thead>
                    <tr>
'.LR::each($cx, $in['columns'] ?? null, $in, function($cx, $in) {$sc=count($cx->depths);return '                            <th '.(LR::ifvar(LR::cv($in, 'width')) ? 'style="width:'.LR::encq(LR::cv($in, 'width')).'"' : '').' class="'.(LR::ifvar(LR::cv($in, 'sortable')) ? 'sortable' : '').(LR::ifvar(LR::hbch($cx, 'eq', [$in['currentSort']['key'] ?? null,$in['key'] ?? null], [], $in)) ? ' sort-'.LR::encq($in['currentSort']['dir'] ?? null) : '').'">
'.(LR::ifvar(LR::cv($in, 'sortable')) ? '                                    <a href="'.LR::encq($cx->depths[$sc-1]['sortBaseUrl'] ?? null).'?sort='.LR::encq(LR::cv($in, 'key')).'&dir='.(LR::ifvar(LR::hbch($cx, 'and', [LR::hbch($cx, 'eq', [$cx->depths[$sc-1]['currentSort']['key'] ?? null,$in['key'] ?? null], [], $in),LR::hbch($cx, 'eq', [$cx->depths[$sc-1]['currentSort']['dir'] ?? null,'asc'], [], $in)], [], $in)) ? 'desc' : 'asc').'">
                                    '.LR::encq(LR::cv($in, 'label')).'
                                    </a>
' : '                                    '.LR::encq(LR::cv($in, 'label')).'
').'                            </th>
';}, null).'                        '.(LR::ifvar(LR::cv($in, 'showActions')) ? '<th class="actions-col">'.LR::encq(LR::hbch($cx, 't', ['table.actions'], [], $in)).'</th>' : '').'
                    </tr>
                    </thead>
                    <tbody>
'.LR::each($cx, $in['items'] ?? null, $in, function($cx, $in, array $blockParams = []) {$sc=count($cx->depths);return '                        <tr class="'.(LR::ifvar($blockParams[0][0]['deleted'] ?? null) ? 'deleted' : '').' '.(LR::ifvar(LR::hbch($cx, 'eq', [$cx->frame['index'] ?? null,$cx->depths[$sc-1]['selectedIndex'] ?? null], [], $in)) ? 'selected' : '').'" data-id="'.LR::encq($blockParams[0][0]['id'] ?? null).'">
'.LR::each($cx, $cx->depths[$sc-1]['columns'] ?? null, $in, function($cx, $in) use ($blockParams) {$sc=count($cx->depths);return '                                <td class="col-'.LR::encq(LR::cv($in, 'key')).(LR::ifvar($blockParams[0][0]['highlighted'] ?? null) ? ' highlighted' : '').'">
'.(LR::ifvar(LR::hbch($cx, 'eq', [$in['type'] ?? null,'boolean'], [], $in)) ? '                                        '.(LR::ifvar(LR::hbch($cx, 'lookup', [$blockParams[0][0] ?? null,$in['key'] ?? null], [], $in)) ? '<i class="icon-check text-success"></i>' : '<i class="icon-times text-muted"></i>').'
' : (LR::ifvar(LR::hbch($cx, 'eq', [$in['type'] ?? null,'date'], [], $in)) ? '                                        <time datetime="'.LR::encq(LR::hbch($cx, 'formatDate', [LR::hbch($cx, 'lookup', [$blockParams[0][0] ?? null,$in['key'] ?? null], [], $in),'Y-m-d'], [], $in)).'">'.LR::encq(LR::hbch($cx, 'formatDate', [LR::hbch($cx, 'lookup', [$blockParams[0][0] ?? null,$in['key'] ?? null], [], $in),$in['format'] ?? null], [], $in)).'</time>
' : (LR::ifvar(LR::hbch($cx, 'eq', [$in['type'] ?? null,'currency'], [], $in)) ? '                                        '.LR::encq(LR::hbch($cx, 'formatCurrency', [LR::hbch($cx, 'lookup', [$blockParams[0][0] ?? null,$in['key'] ?? null], [], $in),$cx->depths[$sc-1]['currency'] ?? null], [], $in)).'
' : (LR::ifvar(LR::hbch($cx, 'eq', [$in['type'] ?? null,'link'], [], $in)) ? '                                        <a href="'.LR::encq(LR::hbch($cx, 'replace', [$in['linkTemplate'] ?? null,'{id}',$blockParams[0][0]['id'] ?? null], [], $in)).'">'.LR::encq(LR::hbch($cx, 'lookup', [$blockParams[0][0] ?? null,$in['key'] ?? null], [], $in)).'</a>
' : '                                        '.LR::encq(LR::hbch($cx, 'lookup', [$blockParams[0][0] ?? null,$in['key'] ?? null], [], $in)).'
')))).'                                </td>
';}, null).(LR::ifvar($cx->depths[$sc-1]['showActions'] ?? null) ? '                                <td class="actions">
'.LR::each($cx, $cx->depths[$sc-1]['rowActions'] ?? null, $in, function($cx, $in) use ($blockParams) {return (!LR::ifvar(LR::hbch($cx, 'and', [$in['requiresAdmin'] ?? null,LR::hbch($cx, 'not', [$blockParams[0][0]['isAdmin'] ?? null], [], $in)], [], $in)) ? '                                            <a href="'.LR::encq(LR::hbch($cx, 'replace', [$in['urlTemplate'] ?? null,'{id}',$blockParams[0][0]['id'] ?? null], [], $in)).'"
                                               class="btn btn-sm btn-'.LR::encq(LR::cv($in, 'style')).'"
                                               '.(LR::ifvar(LR::cv($in, 'confirm')) ? 'data-confirm="'.LR::encq(LR::hbch($cx, 't', [$in['confirmKey'] ?? null], [], $in)).'"' : '').'
                                               title="'.LR::encq(LR::hbch($cx, 't', [$in['labelKey'] ?? null], [], $in)).'">
                                                <i class="icon-'.LR::encq(LR::cv($in, 'icon')).'"></i>
                                            </a>
' : '');}, null).'                                </td>
' : '').'                        </tr>
';}, function($cx, $in) {return '                        <tr><td colspan="'.LR::encq(LR::cv($in, 'columnCount')).'" class="empty-message">'.LR::encq(LR::hbch($cx, 't', ['table.empty'], [], $in)).'</td></tr>
';}, []).'                    </tbody>
'.(LR::ifvar(LR::cv($in, 'showTotals')) ? '                        <tfoot>
                        <tr class="totals">
'.LR::each($cx, $in['columns'] ?? null, $in, function($cx, $in) {$sc=count($cx->depths);return '                                <td>'.(LR::ifvar(LR::cv($in, 'showTotal')) ? LR::encq(LR::hbch($cx, 'formatCurrency', [LR::hbch($cx, 'lookup', [$cx->depths[$sc-1]['totals'] ?? null,$in['key'] ?? null], [], $in),$cx->depths[$sc-2]['currency'] ?? null], [], $in)) : '').'</td>
';}, null).'                            '.(LR::ifvar($cx->depths[count($cx->depths)-1]['showActions'] ?? null) ? '<td></td>' : '').'
                        </tr>
                        </tfoot>
' : '').'                </table>
            </div>

'.LR::p($cx, 'pagination', [[$in],[]], 0, '            ').'
' : '
'.'            <div class="empty-state">
                '.(LR::ifvar(LR::cv($in, 'emptyState')) ? LR::raw($in['emptyState']['illustration'] ?? null) : '').'
                <h3>'.LR::encq($in['emptyState']['heading'] ?? null).'</h3>
                '.(LR::ifvar(LR::cv($in, 'emptyState')) ? '<p>'.LR::encq($in['emptyState']['message'] ?? null).'</p>' : '').'
'.(LR::ifvar(LR::cv($in, 'emptyState')) ? '                    <a href="'.LR::encq($in['emptyState']['action']['url'] ?? null).'" class="btn btn-primary">'.LR::encq($in['emptyState']['action']['label'] ?? null).'</a>
' : '').'            </div>

').'
'.LR::each($cx, $in['sidePanels'] ?? null, $in, function($cx, $in) {return LR::p($cx, 'side-panel', [[$in],[]], 0, '            ');}, null).'
    </div>
</main>

'.'<footer id="site-footer">
    <div class="container">
        <div class="footer-cols">
'.LR::each($cx, $in['footerColumns'] ?? null, $in, function($cx, $in) {return LR::p($cx, 'footer-col', [[$in],[]], 0, '                ');}, null).'        </div>
        <div class="footer-bottom">
            <p>'.LR::encq(LR::cv($in, 'copyright')).' '.(LR::ifvar(LR::cv($in, 'showYear')) ? LR::encq(LR::cv($in, 'currentYear')) : '').' '.LR::encq(LR::cv($in, 'siteName')).'</p>
'.(LR::ifvar(LR::cv($in, 'social')) ? '                <ul class="social-links">
'.LR::each($cx, $in['social'] ?? null, $in, function($cx, $in) {return '                        <li><a href="'.LR::encq(LR::cv($in, 'url')).'" aria-label="'.LR::encq(LR::cv($in, 'name')).'"><i class="icon-'.LR::encq(LR::cv($in, 'icon')).'"></i></a></li>
';}, null).'                </ul>
' : '').'        </div>
    </div>
</footer>

'.LR::each($cx, $in['scripts'] ?? null, $in, function($cx, $in) {return '    <script src="'.LR::encq(LR::cv($in, 'url')).'"'.(LR::ifvar(LR::cv($in, 'defer')) ? ' defer' : '').(LR::ifvar(LR::cv($in, 'async')) ? ' async' : '').(LR::ifvar(LR::cv($in, 'integrity')) ? ' integrity="'.LR::encq(LR::cv($in, 'integrity')).'" crossorigin="anonymous"' : '').'></script>
';}, null).(LR::ifvar(LR::cv($in, 'inlineScript')) ? '    <script>
            '.LR::raw(LR::cv($in, 'inlineScript')).'
    </script>
' : '').'
</body>
</html>
';
};
