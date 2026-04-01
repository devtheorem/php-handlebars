Compiled 1000 times  |  2.80 ms/compile  |    24.0 KB code    |  1.7 MB peak
Executed 1000 times  |  1.37 ms/render   |   139.9 KB output  |  1.8 MB peak
<?php
use DevTheorem\Handlebars\Runtime as LR;
return function (mixed $in = null, array $options = []) {
 $cx = LR::createContext($in, $options);
 $p0 = function($cx, $in) {return '        <link rel="stylesheet" href="'.LR::escapeExpression(LR::lookupValue($in, 'url')).'" '.(!LR::isEmpty(LR::lookupValue($in, 'media')) ? 'media="'.LR::escapeExpression(LR::lookupValue($in, 'media')).'"' : '').'>
';};
 $p1 = function($cx, $in) {return LR::invokePartial($cx, 'nav-item', $in, [], '                ');};
 $p2 = function($cx, $in) {return '            <div class="user-menu">
                <img src="'.LR::escapeExpression(LR::lookupValue($in, 'avatar')).'" alt="'.LR::escapeExpression(LR::lookupValue($in, 'name')).'" width="32" height="32">
                <span>'.LR::escapeExpression(LR::lookupValue($in, 'name')).'</span>
                <ul class="user-dropdown">
                    <li><a href="/profile/'.LR::escapeExpression(LR::lookupValue($in, 'id')).'">'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['t'], 't', ['nav.profile'], [], $in)).'</a></li>
                    <li><a href="/settings">'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['t'], 't', ['nav.settings'], [], $in)).'</a></li>
                    '.(!LR::isEmpty(LR::lookupValue($in, 'isAdmin')) ? '<li><a href="/admin">'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['t'], 't', ['nav.admin'], [], $in)).'</a></li>' : '').'
                    <li class="divider"></li>
                    <li><a href="/logout">'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['t'], 't', ['nav.logout'], [], $in)).'</a></li>
                </ul>
            </div>
';};
 $p3 = function($cx, $in) {return '            <a class="btn btn-primary" href="/login">'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['t'], 't', ['nav.login'], [], $in)).'</a>
';};
 $p4 = function($cx, $in) {return LR::invokePartial($cx, 'alert', $in, [], '    ');};
 $p5 = function($cx, $in) {$sc=count($cx->depths);return '                            <th '.(!LR::isEmpty(LR::lookupValue($in, 'width')) ? 'style="width:'.LR::escapeExpression(LR::lookupValue($in, 'width')).'"' : '').' class="'.(!LR::isEmpty(LR::lookupValue($in, 'sortable')) ? 'sortable' : '').(!LR::isEmpty(LR::invokeHelper($cx, $cx->helpers['eq'], 'eq', [$in['currentSort']['key'] ?? null,$in['key'] ?? null], [], $in)) ? ' sort-'.LR::escapeExpression(LR::lambda($in['currentSort']['dir'] ?? null)) : '').'">
'.(!LR::isEmpty(LR::lookupValue($in, 'sortable')) ? '                                    <a href="'.LR::escapeExpression(LR::lambda($cx->depths[$sc-1]['sortBaseUrl'] ?? null)).'?sort='.LR::escapeExpression(LR::lookupValue($in, 'key')).'&dir='.(!LR::isEmpty(LR::invokeHelper($cx, $cx->helpers['and'], 'and', [LR::invokeHelper($cx, $cx->helpers['eq'], 'eq', [$cx->depths[$sc-1]['currentSort']['key'] ?? null,$in['key'] ?? null], [], $in),LR::invokeHelper($cx, $cx->helpers['eq'], 'eq', [$cx->depths[$sc-1]['currentSort']['dir'] ?? null,'asc'], [], $in)], [], $in)) ? 'desc' : 'asc').'">
                                    '.LR::escapeExpression(LR::lookupValue($in, 'label')).'
                                    </a>
' : '                                    '.LR::escapeExpression(LR::lookupValue($in, 'label')).'
').'                            </th>
';};
 $p6 = function($cx, $in, array $blockParams = []) {$sc=count($cx->depths);return '                                <td class="col-'.LR::escapeExpression(LR::lookupValue($in, 'key')).(!LR::isEmpty($blockParams[0][0]['highlighted'] ?? null) ? ' highlighted' : '').'">
'.(!LR::isEmpty(LR::invokeHelper($cx, $cx->helpers['eq'], 'eq', [$in['type'] ?? null,'boolean'], [], $in)) ? '                                        '.(!LR::isEmpty(LR::invokeHelper($cx, $cx->helpers['lookup'], 'lookup', [$blockParams[0][0],$in['key'] ?? null], [], $in)) ? '<i class="icon-check text-success"></i>' : '<i class="icon-times text-muted"></i>').'
' : (!LR::isEmpty(LR::invokeHelper($cx, $cx->helpers['eq'], 'eq', [$in['type'] ?? null,'date'], [], $in)) ? '                                        <time datetime="'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['formatDate'], 'formatDate', [LR::invokeHelper($cx, $cx->helpers['lookup'], 'lookup', [$blockParams[0][0],$in['key'] ?? null], [], $in),'Y-m-d'], [], $in)).'">'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['formatDate'], 'formatDate', [LR::invokeHelper($cx, $cx->helpers['lookup'], 'lookup', [$blockParams[0][0],$in['key'] ?? null], [], $in),$in['format'] ?? null], [], $in)).'</time>
' : (!LR::isEmpty(LR::invokeHelper($cx, $cx->helpers['eq'], 'eq', [$in['type'] ?? null,'currency'], [], $in)) ? '                                        '.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['formatCurrency'], 'formatCurrency', [LR::invokeHelper($cx, $cx->helpers['lookup'], 'lookup', [$blockParams[0][0],$in['key'] ?? null], [], $in),$cx->depths[$sc-1]['currency'] ?? null], [], $in)).'
' : (!LR::isEmpty(LR::invokeHelper($cx, $cx->helpers['eq'], 'eq', [$in['type'] ?? null,'link'], [], $in)) ? '                                        <a href="'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['replace'], 'replace', [$in['linkTemplate'] ?? null,'{id}',$blockParams[0][0]['id'] ?? null], [], $in)).'">'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['lookup'], 'lookup', [$blockParams[0][0],$in['key'] ?? null], [], $in)).'</a>
' : '                                        '.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['lookup'], 'lookup', [$blockParams[0][0],$in['key'] ?? null], [], $in)).'
')))).'                                </td>
';};
 $p7 = function($cx, $in, array $blockParams = []) {return (LR::isEmpty(LR::invokeHelper($cx, $cx->helpers['and'], 'and', [$in['requiresAdmin'] ?? null,LR::invokeHelper($cx, $cx->helpers['not'], 'not', [$blockParams[0][0]['isAdmin'] ?? null], [], $in)], [], $in)) ? '                                            <a href="'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['replace'], 'replace', [$in['urlTemplate'] ?? null,'{id}',$blockParams[0][0]['id'] ?? null], [], $in)).'"
                                               class="btn btn-sm btn-'.LR::escapeExpression(LR::lookupValue($in, 'style')).'"
                                               '.(!LR::isEmpty(LR::lookupValue($in, 'confirm')) ? 'data-confirm="'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['t'], 't', [$in['confirmKey'] ?? null], [], $in)).'"' : '').'
                                               title="'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['t'], 't', [$in['labelKey'] ?? null], [], $in)).'">
                                                <i class="icon-'.LR::escapeExpression(LR::lookupValue($in, 'icon')).'"></i>
                                            </a>
' : '');};
 $p8 = function($cx, $in, array $blockParams = []) use ($p6, $p7) {$sc=count($cx->depths);return '                        <tr class="'.(!LR::isEmpty($blockParams[0][0]['deleted'] ?? null) ? 'deleted' : '').' '.(!LR::isEmpty(LR::invokeHelper($cx, $cx->helpers['eq'], 'eq', [$cx->data['index'] ?? null,$cx->depths[$sc-1]['selectedIndex'] ?? null], [], $in)) ? 'selected' : '').'" data-id="'.LR::escapeExpression(LR::lambda($blockParams[0][0]['id'] ?? null)).'">
'.LR::invokeBlockHelper($cx, $cx->helpers['each'], 'each', [$cx->depths[$sc-1]['columns'] ?? null], [], $in, $p6, null, $blockParams, 0).(!LR::isEmpty($cx->depths[$sc-1]['showActions'] ?? null) ? '                                <td class="actions">
'.LR::invokeBlockHelper($cx, $cx->helpers['each'], 'each', [$cx->depths[$sc-1]['rowActions'] ?? null], [], $in, $p7, null, $blockParams, 0).'                                </td>
' : '').'                        </tr>
';};
 $p9 = function($cx, $in) {return '                        <tr><td colspan="'.LR::escapeExpression(LR::lookupValue($in, 'columnCount')).'" class="empty-message">'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['t'], 't', ['table.empty'], [], $in)).'</td></tr>
';};
 $p10 = function($cx, $in) {$sc=count($cx->depths);return '                                <td>'.(!LR::isEmpty(LR::lookupValue($in, 'showTotal')) ? LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['formatCurrency'], 'formatCurrency', [LR::invokeHelper($cx, $cx->helpers['lookup'], 'lookup', [$cx->depths[$sc-1]['totals'] ?? null,$in['key'] ?? null], [], $in),$cx->depths[$sc-2]['currency'] ?? null], [], $in)) : '').'</td>
';};
 $p11 = function($cx, $in) {return LR::invokePartial($cx, 'side-panel', $in, [], '            ');};
 $p12 = function($cx, $in) {return LR::invokePartial($cx, 'footer-col', $in, [], '                ');};
 $p13 = function($cx, $in) {return '                        <li><a href="'.LR::escapeExpression(LR::lookupValue($in, 'url')).'" aria-label="'.LR::escapeExpression(LR::lookupValue($in, 'name')).'"><i class="icon-'.LR::escapeExpression(LR::lookupValue($in, 'icon')).'"></i></a></li>
';};
 $p14 = function($cx, $in) {return '    <script src="'.LR::escapeExpression(LR::lookupValue($in, 'url')).'"'.(!LR::isEmpty(LR::lookupValue($in, 'defer')) ? ' defer' : '').(!LR::isEmpty(LR::lookupValue($in, 'async')) ? ' async' : '').(!LR::isEmpty(LR::lookupValue($in, 'integrity')) ? ' integrity="'.LR::escapeExpression(LR::lookupValue($in, 'integrity')).'" crossorigin="anonymous"' : '').'></script>
';};
 $in = &$cx->data['root'];
 return '<!DOCTYPE html>
<html lang="'.LR::escapeExpression(LR::lookupValue($in, 'lang')).'">
<head>
    <meta charset="UTF-8">
    <title>'.(!LR::isEmpty(LR::lookupValue($in, 'pageTitle')) ? LR::escapeExpression(LR::lookupValue($in, 'pageTitle')) : LR::escapeExpression(LR::lookupValue($in, 'siteName')).' &mdash; Default Title').'</title>
'.LR::invokeBlockHelper($cx, $cx->helpers['each'], 'each', [$in['stylesheets'] ?? null], [], $in, $p0, null).'</head>
<body class="'.LR::escapeExpression(LR::lookupValue($in, 'bodyClass')).' '.(!LR::isEmpty($in['user']['isAdmin'] ?? null) ? 'is-admin' : '').' '.(LR::isEmpty($in['user']['verified'] ?? null) ? 'unverified' : '').'">

'.'<header id="site-header" '.(!LR::isEmpty(LR::lookupValue($in, 'sticky')) ? 'data-sticky="true"' : '').'>
    <nav>
        <a class="logo" href="'.LR::escapeExpression(LR::lookupValue($in, 'rootUrl')).'">
            '.LR::raw(LR::lookupValue($in, 'logoHtml')).'
            <span>'.LR::escapeExpression(LR::lookupValue($in, 'siteName')).'</span>
        </a>

        <ul class="nav-links">
'.LR::invokeBlockHelper($cx, $cx->helpers['each'], 'each', [$in['navItems'] ?? null], [], $in, $p1, null).'        </ul>

'.LR::invokeBlockHelper($cx, $cx->helpers['with'], 'with', [$in['user'] ?? null], [], $in, $p2, $p3).'    </nav>
</header>

'.LR::invokeBlockHelper($cx, $cx->helpers['each'], 'each', [$in['alerts'] ?? null], [], $in, $p4, null).'
'.'<main id="main-content">
    <div class="container'.(!LR::isEmpty(LR::lookupValue($in, 'fluid')) ? '-fluid' : '').'">

'.LR::invokePartial($cx, 'breadcrumbs', $in, [], '        ').'
'.LR::invokePartial($cx, 'page-header', $in, [], '        ').'
'.(!LR::isEmpty(LR::lookupValue($in, 'items')) ? '            <div class="table-responsive">
                <table class="table table-striped'.(!LR::isEmpty(LR::lookupValue($in, 'hoverable')) ? ' table-hover' : '').(!LR::isEmpty(LR::lookupValue($in, 'bordered')) ? ' table-bordered' : '').'">
                    <thead>
                    <tr>
'.LR::invokeBlockHelper($cx, $cx->helpers['each'], 'each', [$in['columns'] ?? null], [], $in, $p5, null).'                        '.(!LR::isEmpty(LR::lookupValue($in, 'showActions')) ? '<th class="actions-col">'.LR::escapeExpression(LR::invokeHelper($cx, $cx->helpers['t'], 't', ['table.actions'], [], $in)).'</th>' : '').'
                    </tr>
                    </thead>
                    <tbody>
'.LR::invokeBlockHelper($cx, $cx->helpers['each'], 'each', [$in['items'] ?? null], [], $in, $p8, $p9, [], 2).'                    </tbody>
'.(!LR::isEmpty(LR::lookupValue($in, 'showTotals')) ? '                        <tfoot>
                        <tr class="totals">
'.LR::invokeBlockHelper($cx, $cx->helpers['each'], 'each', [$in['columns'] ?? null], [], $in, $p10, null).'                            '.(!LR::isEmpty($cx->depths[count($cx->depths)-1]['showActions'] ?? null) ? '<td></td>' : '').'
                        </tr>
                        </tfoot>
' : '').'                </table>
            </div>

'.LR::invokePartial($cx, 'pagination', $in, [], '            ').'
' : '
'.'            <div class="empty-state">
                '.(!LR::isEmpty($in['emptyState']['illustration'] ?? null) ? LR::raw(LR::lambda($in['emptyState']['illustration'] ?? null)) : '').'
                <h3>'.LR::escapeExpression(LR::lambda($in['emptyState']['heading'] ?? null)).'</h3>
                '.(!LR::isEmpty($in['emptyState']['message'] ?? null) ? '<p>'.LR::escapeExpression(LR::lambda($in['emptyState']['message'] ?? null)).'</p>' : '').'
'.(!LR::isEmpty($in['emptyState']['action'] ?? null) ? '                    <a href="'.LR::escapeExpression(LR::lambda($in['emptyState']['action']['url'] ?? null)).'" class="btn btn-primary">'.LR::escapeExpression(LR::lambda($in['emptyState']['action']['label'] ?? null)).'</a>
' : '').'            </div>

').'
'.LR::invokeBlockHelper($cx, $cx->helpers['each'], 'each', [$in['sidePanels'] ?? null], [], $in, $p11, null).'
    </div>
</main>

'.'<footer id="site-footer">
    <div class="container">
        <div class="footer-cols">
'.LR::invokeBlockHelper($cx, $cx->helpers['each'], 'each', [$in['footerColumns'] ?? null], [], $in, $p12, null).'        </div>
        <div class="footer-bottom">
            <p>'.LR::escapeExpression(LR::lookupValue($in, 'copyright')).' '.(!LR::isEmpty(LR::lookupValue($in, 'showYear')) ? LR::escapeExpression(LR::lookupValue($in, 'currentYear')) : '').' '.LR::escapeExpression(LR::lookupValue($in, 'siteName')).'</p>
'.(!LR::isEmpty(LR::lookupValue($in, 'social')) ? '                <ul class="social-links">
'.LR::invokeBlockHelper($cx, $cx->helpers['each'], 'each', [$in['social'] ?? null], [], $in, $p13, null).'                </ul>
' : '').'        </div>
    </div>
</footer>

'.LR::invokeBlockHelper($cx, $cx->helpers['each'], 'each', [$in['scripts'] ?? null], [], $in, $p14, null).(!LR::isEmpty(LR::lookupValue($in, 'inlineScript')) ? '    <script>
            '.LR::raw(LR::lookupValue($in, 'inlineScript')).'
    </script>
' : '').'
</body>
</html>
';
};
