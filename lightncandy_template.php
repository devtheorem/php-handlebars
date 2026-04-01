Compiled 1000 times  |  5.04 ms/compile  |    35.8 KB code    |  5.3 MB peak
Executed 1000 times  |  2.40 ms/render   |   139.9 KB output  |  3.7 MB peak
<?php
use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array(            't' => function(string $key, $options) {
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
            'formatDate' => function(mixed $value, string $format) {
        return date($format, strtotime($value));
    },
            'formatCurrency' => function(mixed $value, ?string $format) {
        return ($format ? "$format " : '') . number_format($value, 2);
    },
            'replace' => function(string $subject, string $search, ?string $replace) {
        return str_replace($search, $replace ?? '', $subject);
    },
            'eq' => function(mixed $a, mixed $b) {
        if ($a === null || $b === null) {
            // in JS, null is not equal to blank string or false or zero
            return $a === $b;
        }

        return $a == $b;
    },
            'and' => function(mixed $a, mixed $b) {
        return $a && $b;
    },
            'not' => function(mixed $a) {
        return !$a;
    },
            'gt' => function(mixed $a, mixed $b) {
        return $a > $b;
    },
);
    $partials = array();
    $cx = array(
        'flags' => array(
            'jstrue' => true,
            'jsobj' => true,
            'jslen' => true,
            'spvar' => true,
            'prop' => false,
            'method' => false,
            'lambda' => false,
            'mustlok' => false,
            'mustlam' => false,
            'mustsec' => false,
            'echo' => false,
            'partnc' => false,
            'knohlp' => false,
            'debug' => isset($options['debug']) ? $options['debug'] : 1,
        ),
        'constants' => array(),
        'helpers' => isset($options['helpers']) ? array_merge($helpers, $options['helpers']) : $helpers,
        'partials' => isset($options['partials']) ? array_merge($partials, $options['partials']) : $partials,
        'scopes' => array(),
        'sp_vars' => isset($options['data']) ? array_merge(array('root' => $in), $options['data']) : array('root' => $in),
        'blparam' => array(),
        'partialid' => 0,
        'runtime' => '\LightnCandy\Runtime',
    );
    
    $inary=is_array($in);
    return '<!DOCTYPE html>
<html lang="'.LR::encq($cx, (($inary && isset($in['lang'])) ? $in['lang'] : null)).'">
<head>
    <meta charset="UTF-8">
    <title>'.((LR::ifvar($cx, (($inary && isset($in['pageTitle'])) ? $in['pageTitle'] : null), false)) ? ''.LR::encq($cx, (($inary && isset($in['pageTitle'])) ? $in['pageTitle'] : null)).'' : ''.LR::encq($cx, (($inary && isset($in['siteName'])) ? $in['siteName'] : null)).' &mdash; Default Title').'</title>
'.LR::sec($cx, (($inary && isset($in['stylesheets'])) ? $in['stylesheets'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return '        <link rel="stylesheet" href="'.LR::encq($cx, (($inary && isset($in['url'])) ? $in['url'] : null)).'" '.((LR::ifvar($cx, (($inary && isset($in['media'])) ? $in['media'] : null), false)) ? 'media="'.LR::encq($cx, (($inary && isset($in['media'])) ? $in['media'] : null)).'"' : '').'>
';}).'</head>
<body class="'.LR::encq($cx, (($inary && isset($in['bodyClass'])) ? $in['bodyClass'] : null)).' '.((LR::ifvar($cx, ((isset($in['user']) && is_array($in['user']) && isset($in['user']['isAdmin'])) ? $in['user']['isAdmin'] : null), false)) ? 'is-admin' : '').' '.((!LR::ifvar($cx, ((isset($in['user']) && is_array($in['user']) && isset($in['user']['verified'])) ? $in['user']['verified'] : null), false)) ? 'unverified' : '').'">

<header id="site-header" '.((LR::ifvar($cx, (($inary && isset($in['sticky'])) ? $in['sticky'] : null), false)) ? 'data-sticky="true"' : '').'>
    <nav>
        <a class="logo" href="'.LR::encq($cx, (($inary && isset($in['rootUrl'])) ? $in['rootUrl'] : null)).'">
            '.LR::raw($cx, (($inary && isset($in['logoHtml'])) ? $in['logoHtml'] : null)).'
            <span>'.LR::encq($cx, (($inary && isset($in['siteName'])) ? $in['siteName'] : null)).'</span>
        </a>

        <ul class="nav-links">
'.LR::sec($cx, (($inary && isset($in['navItems'])) ? $in['navItems'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return ''.'                <li class="'.((LR::ifvar($cx, (($inary && isset($in['active'])) ? $in['active'] : null), false)) ? 'active' : '').' '.((LR::ifvar($cx, (($inary && isset($in['disabled'])) ? $in['disabled'] : null), false)) ? 'disabled' : '').'">
                    <a href="'.LR::encq($cx, (($inary && isset($in['url'])) ? $in['url'] : null)).'" '.((LR::ifvar($cx, (($inary && isset($in['newTab'])) ? $in['newTab'] : null), false)) ? 'target="_blank" rel="noopener"' : '').'>
                        '.((LR::ifvar($cx, (($inary && isset($in['icon'])) ? $in['icon'] : null), false)) ? '<i class="icon-'.LR::encq($cx, (($inary && isset($in['icon'])) ? $in['icon'] : null)).'"></i>' : '').'
                        '.LR::encq($cx, (($inary && isset($in['label'])) ? $in['label'] : null)).'
                        '.((LR::ifvar($cx, (($inary && isset($in['badge'])) ? $in['badge'] : null), false)) ? '<span class="badge">'.LR::encq($cx, (($inary && isset($in['badge'])) ? $in['badge'] : null)).'</span>' : '').'
                    </a>
'.((LR::ifvar($cx, (($inary && isset($in['children'])) ? $in['children'] : null), false)) ? '                        <ul class="dropdown">
'.LR::sec($cx, (($inary && isset($in['children'])) ? $in['children'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return '                                <li><a href="'.LR::encq($cx, (($inary && isset($in['url'])) ? $in['url'] : null)).'">'.LR::encq($cx, (($inary && isset($in['label'])) ? $in['label'] : null)).'</a></li>
';}).'                        </ul>
' : '').'                </li>
'.'';}).'        </ul>

'.LR::wi($cx, (($inary && isset($in['user'])) ? $in['user'] : null), null, $in, function($cx, $in) {$inary=is_array($in);return '            <div class="user-menu">
                <img src="'.LR::encq($cx, (($inary && isset($in['avatar'])) ? $in['avatar'] : null)).'" alt="'.LR::encq($cx, (($inary && isset($in['name'])) ? $in['name'] : null)).'" width="32" height="32">
                <span>'.LR::encq($cx, (($inary && isset($in['name'])) ? $in['name'] : null)).'</span>
                <ul class="user-dropdown">
                    <li><a href="/profile/'.LR::encq($cx, (($inary && isset($in['id'])) ? $in['id'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 't', array(array('nav.profile'),array()), 'encq', $in)).'</a></li>
                    <li><a href="/settings">'.LR::encq($cx, LR::hbch($cx, 't', array(array('nav.settings'),array()), 'encq', $in)).'</a></li>
                    '.((LR::ifvar($cx, (($inary && isset($in['isAdmin'])) ? $in['isAdmin'] : null), false)) ? '<li><a href="/admin">'.LR::encq($cx, LR::hbch($cx, 't', array(array('nav.admin'),array()), 'encq', $in)).'</a></li>' : '').'
                    <li class="divider"></li>
                    <li><a href="/logout">'.LR::encq($cx, LR::hbch($cx, 't', array(array('nav.logout'),array()), 'encq', $in)).'</a></li>
                </ul>
            </div>
';}, function($cx, $in) {$inary=is_array($in);return '            <a class="btn btn-primary" href="/login">'.LR::encq($cx, LR::hbch($cx, 't', array(array('nav.login'),array()), 'encq', $in)).'</a>
';}).'    </nav>
</header>

'.LR::sec($cx, (($inary && isset($in['alerts'])) ? $in['alerts'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return ''.'    <div class="alert alert-'.LR::encq($cx, (($inary && isset($in['type'])) ? $in['type'] : null)).' '.((LR::ifvar($cx, (($inary && isset($in['dismissible'])) ? $in['dismissible'] : null), false)) ? 'alert-dismissible' : '').'" role="alert">
        '.((LR::ifvar($cx, (($inary && isset($in['icon'])) ? $in['icon'] : null), false)) ? '<i class="icon-'.LR::encq($cx, (($inary && isset($in['icon'])) ? $in['icon'] : null)).'"></i>' : '').'
        '.LR::raw($cx, (($inary && isset($in['message'])) ? $in['message'] : null)).'
        '.((LR::ifvar($cx, (($inary && isset($in['dismissible'])) ? $in['dismissible'] : null), false)) ? '<button type="button" class="close" data-dismiss="alert">&times;</button>' : '').'
    </div>
'.'';}).'
<main id="main-content">
    <div class="container'.((LR::ifvar($cx, (($inary && isset($in['fluid'])) ? $in['fluid'] : null), false)) ? '-fluid' : '').'">

'.''.((LR::ifvar($cx, (($inary && isset($in['breadcrumbs'])) ? $in['breadcrumbs'] : null), false)) ? '            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
'.LR::sec($cx, (($inary && isset($in['breadcrumbs'])) ? $in['breadcrumbs'] : null), array('crumb','idx'), $in, true, function($cx, $in) {$inary=is_array($in);return '                        <li class="breadcrumb-item'.((LR::ifvar($cx, (isset($cx['sp_vars']['last']) ? $cx['sp_vars']['last'] : null), false)) ? ' active' : '').'">
'.((LR::ifvar($cx, (isset($cx['sp_vars']['last']) ? $cx['sp_vars']['last'] : null), false)) ? '                                '.LR::encq($cx, ((isset($in['crumb']) && is_array($in['crumb']) && isset($in['crumb']['label'])) ? $in['crumb']['label'] : null)).'
' : '                                <a href="'.LR::encq($cx, ((isset($in['crumb']) && is_array($in['crumb']) && isset($in['crumb']['url'])) ? $in['crumb']['url'] : null)).'">'.LR::encq($cx, ((isset($in['crumb']) && is_array($in['crumb']) && isset($in['crumb']['label'])) ? $in['crumb']['label'] : null)).'</a>
').'                        </li>
';}).'                </ol>
            </nav>
' : '').''.'
'.'        <div class="page-header">
            <h1>
                '.LR::raw($cx, (($inary && isset($in['heading'])) ? $in['heading'] : null)).'
                '.LR::wi($cx, (($inary && isset($in['headingBadge'])) ? $in['headingBadge'] : null), null, $in, function($cx, $in) {$inary=is_array($in);return '<span class="badge badge-'.LR::encq($cx, (($inary && isset($in['type'])) ? $in['type'] : null)).'">'.LR::encq($cx, (($inary && isset($in['text'])) ? $in['text'] : null)).'</span>';}).'
            </h1>
            '.((LR::ifvar($cx, (($inary && isset($in['subheading'])) ? $in['subheading'] : null), false)) ? '<p class="lead">'.LR::encq($cx, (($inary && isset($in['subheading'])) ? $in['subheading'] : null)).'</p>' : '').'
'.((LR::ifvar($cx, (($inary && isset($in['actions'])) ? $in['actions'] : null), false)) ? '                <div class="page-actions">
'.LR::sec($cx, (($inary && isset($in['actions'])) ? $in['actions'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return '                        <a href="'.LR::encq($cx, (($inary && isset($in['url'])) ? $in['url'] : null)).'" class="btn btn-'.((LR::ifvar($cx, (($inary && isset($in['primary'])) ? $in['primary'] : null), false)) ? 'primary' : 'secondary').''.((LR::ifvar($cx, (($inary && isset($in['size'])) ? $in['size'] : null), false)) ? ' btn-'.LR::encq($cx, (($inary && isset($in['size'])) ? $in['size'] : null)).'' : '').'">
                            '.((LR::ifvar($cx, (($inary && isset($in['icon'])) ? $in['icon'] : null), false)) ? '<i class="icon-'.LR::encq($cx, (($inary && isset($in['icon'])) ? $in['icon'] : null)).'"></i> ' : '').''.LR::encq($cx, (($inary && isset($in['label'])) ? $in['label'] : null)).'
                        </a>
';}).'                </div>
' : '').'        </div>
'.'
'.((LR::ifvar($cx, (($inary && isset($in['items'])) ? $in['items'] : null), false)) ? '            <div class="table-responsive">
                <table class="table table-striped'.((LR::ifvar($cx, (($inary && isset($in['hoverable'])) ? $in['hoverable'] : null), false)) ? ' table-hover' : '').''.((LR::ifvar($cx, (($inary && isset($in['bordered'])) ? $in['bordered'] : null), false)) ? ' table-bordered' : '').'">
                    <thead>
                    <tr>
'.LR::sec($cx, (($inary && isset($in['columns'])) ? $in['columns'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return '                            <th '.((LR::ifvar($cx, (($inary && isset($in['width'])) ? $in['width'] : null), false)) ? 'style="width:'.LR::encq($cx, (($inary && isset($in['width'])) ? $in['width'] : null)).'"' : '').' class="'.((LR::ifvar($cx, (($inary && isset($in['sortable'])) ? $in['sortable'] : null), false)) ? 'sortable' : '').''.((LR::ifvar($cx, LR::hbch($cx, 'eq', array(array(((isset($in['currentSort']) && is_array($in['currentSort']) && isset($in['currentSort']['key'])) ? $in['currentSort']['key'] : null),(($inary && isset($in['key'])) ? $in['key'] : null)),array()), 'raw', $in), false)) ? ' sort-'.LR::encq($cx, ((isset($in['currentSort']) && is_array($in['currentSort']) && isset($in['currentSort']['dir'])) ? $in['currentSort']['dir'] : null)).'' : '').'">
'.((LR::ifvar($cx, (($inary && isset($in['sortable'])) ? $in['sortable'] : null), false)) ? '                                    <a href="'.LR::encq($cx, ((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1]['sortBaseUrl'])) ? $cx['scopes'][count($cx['scopes'])-1]['sortBaseUrl'] : null)).'?sort='.LR::encq($cx, (($inary && isset($in['key'])) ? $in['key'] : null)).'&dir='.((LR::ifvar($cx, LR::hbch($cx, 'and', array(array(LR::hbch($cx, 'eq', array(array(((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]['currentSort']) && isset($cx['scopes'][count($cx['scopes'])-1]['currentSort']['key'])) ? $cx['scopes'][count($cx['scopes'])-1]['currentSort']['key'] : null),(($inary && isset($in['key'])) ? $in['key'] : null)),array()), 'raw', $in),LR::hbch($cx, 'eq', array(array(((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]['currentSort']) && isset($cx['scopes'][count($cx['scopes'])-1]['currentSort']['dir'])) ? $cx['scopes'][count($cx['scopes'])-1]['currentSort']['dir'] : null),'asc'),array()), 'raw', $in)),array()), 'raw', $in), false)) ? 'desc' : 'asc').'">
                                    '.LR::encq($cx, (($inary && isset($in['label'])) ? $in['label'] : null)).'
                                    </a>
' : '                                    '.LR::encq($cx, (($inary && isset($in['label'])) ? $in['label'] : null)).'
').'                            </th>
';}).'                        '.((LR::ifvar($cx, (($inary && isset($in['showActions'])) ? $in['showActions'] : null), false)) ? '<th class="actions-col">'.LR::encq($cx, LR::hbch($cx, 't', array(array('table.actions'),array()), 'encq', $in)).'</th>' : '').'
                    </tr>
                    </thead>
                    <tbody>
'.LR::sec($cx, (($inary && isset($in['items'])) ? $in['items'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return '                        <tr class="'.((LR::ifvar($cx, (($inary && isset($in['deleted'])) ? $in['deleted'] : null), false)) ? 'deleted' : '').' '.((LR::ifvar($cx, LR::hbch($cx, 'eq', array(array((isset($cx['sp_vars']['index']) ? $cx['sp_vars']['index'] : null),((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1]['selectedIndex'])) ? $cx['scopes'][count($cx['scopes'])-1]['selectedIndex'] : null)),array()), 'raw', $in), false)) ? 'selected' : '').'" data-id="'.LR::encq($cx, (($inary && isset($in['id'])) ? $in['id'] : null)).'">
'.LR::sec($cx, ((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1]['columns'])) ? $cx['scopes'][count($cx['scopes'])-1]['columns'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return '                                <td class="col-'.LR::encq($cx, (($inary && isset($in['key'])) ? $in['key'] : null)).''.((LR::ifvar($cx, ((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1]['highlighted'])) ? $cx['scopes'][count($cx['scopes'])-1]['highlighted'] : null), false)) ? ' highlighted' : '').'">
'.((LR::ifvar($cx, LR::hbch($cx, 'eq', array(array((($inary && isset($in['type'])) ? $in['type'] : null),'boolean'),array()), 'raw', $in), false)) ? '                                        '.((LR::ifvar($cx, LR::raw($cx, ((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1][(($inary && isset($in['key'])) ? $in['key'] : null)])) ? $cx['scopes'][count($cx['scopes'])-1][(($inary && isset($in['key'])) ? $in['key'] : null)] : null), 1), false)) ? '<i class="icon-check text-success"></i>' : '<i class="icon-times text-muted"></i>').'
' : ''.((LR::ifvar($cx, LR::hbch($cx, 'eq', array(array((($inary && isset($in['type'])) ? $in['type'] : null),'date'),array()), 'raw', $in), false)) ? '                                        <time datetime="'.LR::encq($cx, LR::hbch($cx, 'formatDate', array(array(LR::raw($cx, ((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1][(($inary && isset($in['key'])) ? $in['key'] : null)])) ? $cx['scopes'][count($cx['scopes'])-1][(($inary && isset($in['key'])) ? $in['key'] : null)] : null), 1),'Y-m-d'),array()), 'encq', $in)).'">'.LR::encq($cx, LR::hbch($cx, 'formatDate', array(array(LR::raw($cx, ((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1][(($inary && isset($in['key'])) ? $in['key'] : null)])) ? $cx['scopes'][count($cx['scopes'])-1][(($inary && isset($in['key'])) ? $in['key'] : null)] : null), 1),(($inary && isset($in['format'])) ? $in['format'] : null)),array()), 'encq', $in)).'</time>
' : ''.((LR::ifvar($cx, LR::hbch($cx, 'eq', array(array((($inary && isset($in['type'])) ? $in['type'] : null),'currency'),array()), 'raw', $in), false)) ? '                                        '.LR::encq($cx, LR::hbch($cx, 'formatCurrency', array(array(LR::raw($cx, ((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1][(($inary && isset($in['key'])) ? $in['key'] : null)])) ? $cx['scopes'][count($cx['scopes'])-1][(($inary && isset($in['key'])) ? $in['key'] : null)] : null), 1),((isset($cx['scopes'][count($cx['scopes'])-2]) && is_array($cx['scopes'][count($cx['scopes'])-2]) && isset($cx['scopes'][count($cx['scopes'])-2]['currency'])) ? $cx['scopes'][count($cx['scopes'])-2]['currency'] : null)),array()), 'encq', $in)).'
' : ''.((LR::ifvar($cx, LR::hbch($cx, 'eq', array(array((($inary && isset($in['type'])) ? $in['type'] : null),'link'),array()), 'raw', $in), false)) ? '                                        <a href="'.LR::encq($cx, LR::hbch($cx, 'replace', array(array((($inary && isset($in['linkTemplate'])) ? $in['linkTemplate'] : null),'{id}',((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1]['id'])) ? $cx['scopes'][count($cx['scopes'])-1]['id'] : null)),array()), 'encq', $in)).'">'.LR::encq($cx, ((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1][(($inary && isset($in['key'])) ? $in['key'] : null)])) ? $cx['scopes'][count($cx['scopes'])-1][(($inary && isset($in['key'])) ? $in['key'] : null)] : null)).'</a>
' : '                                        '.LR::encq($cx, ((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1][(($inary && isset($in['key'])) ? $in['key'] : null)])) ? $cx['scopes'][count($cx['scopes'])-1][(($inary && isset($in['key'])) ? $in['key'] : null)] : null)).'
').'').'').'').'                                </td>
';}).''.((LR::ifvar($cx, ((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1]['showActions'])) ? $cx['scopes'][count($cx['scopes'])-1]['showActions'] : null), false)) ? '                                <td class="actions">
'.LR::sec($cx, ((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1]['rowActions'])) ? $cx['scopes'][count($cx['scopes'])-1]['rowActions'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return ''.((!LR::ifvar($cx, LR::hbch($cx, 'and', array(array((($inary && isset($in['requiresAdmin'])) ? $in['requiresAdmin'] : null),LR::hbch($cx, 'not', array(array(((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1]['isAdmin'])) ? $cx['scopes'][count($cx['scopes'])-1]['isAdmin'] : null)),array()), 'raw', $in)),array()), 'raw', $in), false)) ? '                                            <a href="'.LR::encq($cx, LR::hbch($cx, 'replace', array(array((($inary && isset($in['urlTemplate'])) ? $in['urlTemplate'] : null),'{id}',((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1]['id'])) ? $cx['scopes'][count($cx['scopes'])-1]['id'] : null)),array()), 'encq', $in)).'"
                                               class="btn btn-sm btn-'.LR::encq($cx, (($inary && isset($in['style'])) ? $in['style'] : null)).'"
                                               '.((LR::ifvar($cx, (($inary && isset($in['confirm'])) ? $in['confirm'] : null), false)) ? 'data-confirm="'.LR::encq($cx, LR::hbch($cx, 't', array(array((($inary && isset($in['confirmKey'])) ? $in['confirmKey'] : null)),array()), 'encq', $in)).'"' : '').'
                                               title="'.LR::encq($cx, LR::hbch($cx, 't', array(array((($inary && isset($in['labelKey'])) ? $in['labelKey'] : null)),array()), 'encq', $in)).'">
                                                <i class="icon-'.LR::encq($cx, (($inary && isset($in['icon'])) ? $in['icon'] : null)).'"></i>
                                            </a>
' : '').'';}).'                                </td>
' : '').'                        </tr>
';}, function($cx, $in) {$inary=is_array($in);return '                        <tr><td colspan="'.LR::encq($cx, (($inary && isset($in['columnCount'])) ? $in['columnCount'] : null)).'" class="empty-message">'.LR::encq($cx, LR::hbch($cx, 't', array(array('table.empty'),array()), 'encq', $in)).'</td></tr>
';}).'                    </tbody>
'.((LR::ifvar($cx, (($inary && isset($in['showTotals'])) ? $in['showTotals'] : null), false)) ? '                        <tfoot>
                        <tr class="totals">
'.LR::sec($cx, (($inary && isset($in['columns'])) ? $in['columns'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return '                                <td>'.((LR::ifvar($cx, (($inary && isset($in['showTotal'])) ? $in['showTotal'] : null), false)) ? ''.LR::encq($cx, LR::hbch($cx, 'formatCurrency', array(array(LR::raw($cx, ((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]['totals']) && isset($cx['scopes'][count($cx['scopes'])-1]['totals'][(($inary && isset($in['key'])) ? $in['key'] : null)])) ? $cx['scopes'][count($cx['scopes'])-1]['totals'][(($inary && isset($in['key'])) ? $in['key'] : null)] : null), 1),((isset($cx['scopes'][count($cx['scopes'])-2]) && is_array($cx['scopes'][count($cx['scopes'])-2]) && isset($cx['scopes'][count($cx['scopes'])-2]['currency'])) ? $cx['scopes'][count($cx['scopes'])-2]['currency'] : null)),array()), 'encq', $in)).'' : '').'</td>
';}).'                            '.((LR::ifvar($cx, ((isset($cx['scopes'][count($cx['scopes'])-1]) && is_array($cx['scopes'][count($cx['scopes'])-1]) && isset($cx['scopes'][count($cx['scopes'])-1]['showActions'])) ? $cx['scopes'][count($cx['scopes'])-1]['showActions'] : null), false)) ? '<td></td>' : '').'
                        </tr>
                        </tfoot>
' : '').'                </table>
            </div>

'.''.((LR::ifvar($cx, (($inary && isset($in['pagination'])) ? $in['pagination'] : null), false)) ? '                <nav aria-label="'.LR::encq($cx, LR::hbch($cx, 't', array(array('pagination.label'),array()), 'encq', $in)).'">
                    <ul class="pagination '.((LR::ifvar($cx, ((isset($in['pagination']) && is_array($in['pagination']) && isset($in['pagination']['small'])) ? $in['pagination']['small'] : null), false)) ? 'pagination-sm' : '').'">
                        <li class="page-item'.((!LR::ifvar($cx, ((isset($in['pagination']) && is_array($in['pagination']) && isset($in['pagination']['hasPrev'])) ? $in['pagination']['hasPrev'] : null), false)) ? ' disabled' : '').'">
                            <a class="page-link" href="'.LR::encq($cx, ((isset($in['pagination']) && is_array($in['pagination']) && isset($in['pagination']['prevUrl'])) ? $in['pagination']['prevUrl'] : null)).'">&laquo; '.LR::encq($cx, LR::hbch($cx, 't', array(array('pagination.prev'),array()), 'encq', $in)).'</a>
                        </li>
'.LR::sec($cx, ((isset($in['pagination']) && is_array($in['pagination']) && isset($in['pagination']['pages'])) ? $in['pagination']['pages'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return '                            <li class="page-item'.((LR::ifvar($cx, (($inary && isset($in['active'])) ? $in['active'] : null), false)) ? ' active' : '').''.((LR::ifvar($cx, (($inary && isset($in['ellipsis'])) ? $in['ellipsis'] : null), false)) ? ' disabled' : '').'">
'.((LR::ifvar($cx, (($inary && isset($in['ellipsis'])) ? $in['ellipsis'] : null), false)) ? '                                    <span class="page-link">&hellip;</span>
' : '                                    <a class="page-link" href="'.LR::encq($cx, (($inary && isset($in['url'])) ? $in['url'] : null)).'">'.LR::encq($cx, (($inary && isset($in['number'])) ? $in['number'] : null)).'</a>
').'                            </li>
';}).'                        <li class="page-item'.((!LR::ifvar($cx, ((isset($in['pagination']) && is_array($in['pagination']) && isset($in['pagination']['hasNext'])) ? $in['pagination']['hasNext'] : null), false)) ? ' disabled' : '').'">
                            <a class="page-link" href="'.LR::encq($cx, ((isset($in['pagination']) && is_array($in['pagination']) && isset($in['pagination']['nextUrl'])) ? $in['pagination']['nextUrl'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 't', array(array('pagination.next'),array()), 'encq', $in)).' &raquo;</a>
                        </li>
                    </ul>
                    <p class="pagination-summary">
                        '.LR::encq($cx, LR::hbch($cx, 't', array(array('pagination.showing'),array('start'=>((isset($in['pagination']) && is_array($in['pagination']) && isset($in['pagination']['start'])) ? $in['pagination']['start'] : null),'end'=>((isset($in['pagination']) && is_array($in['pagination']) && isset($in['pagination']['end'])) ? $in['pagination']['end'] : null),'total'=>((isset($in['pagination']) && is_array($in['pagination']) && isset($in['pagination']['total'])) ? $in['pagination']['total'] : null))), 'encq', $in)).'
                    </p>
                </nav>
' : '').''.'
' : '
            <div class="empty-state">
                '.((LR::ifvar($cx, ((isset($in['emptyState']) && is_array($in['emptyState']) && isset($in['emptyState']['illustration'])) ? $in['emptyState']['illustration'] : null), false)) ? ''.LR::raw($cx, ((isset($in['emptyState']) && is_array($in['emptyState']) && isset($in['emptyState']['illustration'])) ? $in['emptyState']['illustration'] : null)).'' : '').'
                <h3>'.LR::encq($cx, ((isset($in['emptyState']) && is_array($in['emptyState']) && isset($in['emptyState']['heading'])) ? $in['emptyState']['heading'] : null)).'</h3>
                '.((LR::ifvar($cx, ((isset($in['emptyState']) && is_array($in['emptyState']) && isset($in['emptyState']['message'])) ? $in['emptyState']['message'] : null), false)) ? '<p>'.LR::encq($cx, ((isset($in['emptyState']) && is_array($in['emptyState']) && isset($in['emptyState']['message'])) ? $in['emptyState']['message'] : null)).'</p>' : '').'
'.((LR::ifvar($cx, ((isset($in['emptyState']) && is_array($in['emptyState']) && isset($in['emptyState']['action'])) ? $in['emptyState']['action'] : null), false)) ? '                    <a href="'.LR::encq($cx, ((isset($in['emptyState']['action']) && is_array($in['emptyState']['action']) && isset($in['emptyState']['action']['url'])) ? $in['emptyState']['action']['url'] : null)).'" class="btn btn-primary">'.LR::encq($cx, ((isset($in['emptyState']['action']) && is_array($in['emptyState']['action']) && isset($in['emptyState']['action']['label'])) ? $in['emptyState']['action']['label'] : null)).'</a>
' : '').'            </div>

').'
'.LR::sec($cx, (($inary && isset($in['sidePanels'])) ? $in['sidePanels'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return ''.'            <aside class="side-panel'.((LR::ifvar($cx, (($inary && isset($in['collapsible'])) ? $in['collapsible'] : null), false)) ? ' collapsible'.((LR::ifvar($cx, (($inary && isset($in['collapsed'])) ? $in['collapsed'] : null), false)) ? ' collapsed' : '').'' : '').'" id="panel-'.LR::encq($cx, (($inary && isset($in['id'])) ? $in['id'] : null)).'">
                <div class="panel-header">
                    <h4>'.LR::encq($cx, (($inary && isset($in['title'])) ? $in['title'] : null)).'</h4>
                    '.((LR::ifvar($cx, (($inary && isset($in['collapsible'])) ? $in['collapsible'] : null), false)) ? '<button class="toggle" aria-expanded="'.((LR::ifvar($cx, (($inary && isset($in['collapsed'])) ? $in['collapsed'] : null), false)) ? 'false' : 'true').'"></button>' : '').'
                </div>
                <div class="panel-body">
'.((LR::ifvar($cx, LR::hbch($cx, 'eq', array(array((($inary && isset($in['type'])) ? $in['type'] : null),'list'),array()), 'raw', $in), false)) ? '                        <ul>
                            '.LR::sec($cx, (($inary && isset($in['items'])) ? $in['items'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return '<li>'.((LR::ifvar($cx, (($inary && isset($in['url'])) ? $in['url'] : null), false)) ? '<a href="'.LR::encq($cx, (($inary && isset($in['url'])) ? $in['url'] : null)).'">' : '').''.LR::encq($cx, (($inary && isset($in['label'])) ? $in['label'] : null)).''.((LR::ifvar($cx, (($inary && isset($in['url'])) ? $in['url'] : null), false)) ? '</a>' : '').''.((LR::ifvar($cx, (($inary && isset($in['count'])) ? $in['count'] : null), false)) ? ' <span class="count">('.LR::encq($cx, (($inary && isset($in['count'])) ? $in['count'] : null)).')</span>' : '').'</li>';}).'
                        </ul>
' : ''.((LR::ifvar($cx, LR::hbch($cx, 'eq', array(array((($inary && isset($in['type'])) ? $in['type'] : null),'stats'),array()), 'raw', $in), false)) ? '                        <dl class="stats">
'.LR::sec($cx, (($inary && isset($in['stats'])) ? $in['stats'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return '                                <dt>'.LR::encq($cx, (($inary && isset($in['label'])) ? $in['label'] : null)).'</dt>
                                <dd class="'.((LR::ifvar($cx, (($inary && isset($in['trend'])) ? $in['trend'] : null), false)) ? 'trend-'.LR::encq($cx, (($inary && isset($in['trend'])) ? $in['trend'] : null)).'' : '').'">
                                    '.LR::encq($cx, (($inary && isset($in['value'])) ? $in['value'] : null)).''.((LR::ifvar($cx, (($inary && isset($in['unit'])) ? $in['unit'] : null), false)) ? ' '.LR::encq($cx, (($inary && isset($in['unit'])) ? $in['unit'] : null)).'' : '').'
                                    '.((LR::ifvar($cx, (($inary && isset($in['delta'])) ? $in['delta'] : null), false)) ? '<small class="delta">'.((LR::ifvar($cx, LR::hbch($cx, 'gt', array(array((($inary && isset($in['delta'])) ? $in['delta'] : null),0),array()), 'raw', $in), false)) ? '+' : '').''.LR::encq($cx, (($inary && isset($in['delta'])) ? $in['delta'] : null)).'</small>' : '').'
                                </dd>
';}).'                        </dl>
' : ''.((LR::ifvar($cx, LR::hbch($cx, 'eq', array(array((($inary && isset($in['type'])) ? $in['type'] : null),'html'),array()), 'raw', $in), false)) ? '                        '.LR::raw($cx, (($inary && isset($in['content'])) ? $in['content'] : null)).'
' : '').'').'').'                </div>
            </aside>
'.'';}).'
    </div>
</main>

<footer id="site-footer">
    <div class="container">
        <div class="footer-cols">
'.LR::sec($cx, (($inary && isset($in['footerColumns'])) ? $in['footerColumns'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return ''.'                <div class="footer-col">
                    '.((LR::ifvar($cx, (($inary && isset($in['heading'])) ? $in['heading'] : null), false)) ? '<h5>'.LR::encq($cx, (($inary && isset($in['heading'])) ? $in['heading'] : null)).'</h5>' : '').'
                    <ul>
'.LR::sec($cx, (($inary && isset($in['links'])) ? $in['links'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return '                            <li><a href="'.LR::encq($cx, (($inary && isset($in['url'])) ? $in['url'] : null)).'"'.((LR::ifvar($cx, (($inary && isset($in['external'])) ? $in['external'] : null), false)) ? ' target="_blank" rel="noopener noreferrer"' : '').'>'.LR::encq($cx, (($inary && isset($in['label'])) ? $in['label'] : null)).'</a></li>
';}).'                    </ul>
                </div>
'.'';}).'        </div>
        <div class="footer-bottom">
            <p>'.LR::encq($cx, (($inary && isset($in['copyright'])) ? $in['copyright'] : null)).' '.((LR::ifvar($cx, (($inary && isset($in['showYear'])) ? $in['showYear'] : null), false)) ? ''.LR::encq($cx, (($inary && isset($in['currentYear'])) ? $in['currentYear'] : null)).'' : '').' '.LR::encq($cx, (($inary && isset($in['siteName'])) ? $in['siteName'] : null)).'</p>
'.((LR::ifvar($cx, (($inary && isset($in['social'])) ? $in['social'] : null), false)) ? '                <ul class="social-links">
'.LR::sec($cx, (($inary && isset($in['social'])) ? $in['social'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return '                        <li><a href="'.LR::encq($cx, (($inary && isset($in['url'])) ? $in['url'] : null)).'" aria-label="'.LR::encq($cx, (($inary && isset($in['name'])) ? $in['name'] : null)).'"><i class="icon-'.LR::encq($cx, (($inary && isset($in['icon'])) ? $in['icon'] : null)).'"></i></a></li>
';}).'                </ul>
' : '').'        </div>
    </div>
</footer>

'.LR::sec($cx, (($inary && isset($in['scripts'])) ? $in['scripts'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return '    <script src="'.LR::encq($cx, (($inary && isset($in['url'])) ? $in['url'] : null)).'"'.((LR::ifvar($cx, (($inary && isset($in['defer'])) ? $in['defer'] : null), false)) ? ' defer' : '').''.((LR::ifvar($cx, (($inary && isset($in['async'])) ? $in['async'] : null), false)) ? ' async' : '').''.((LR::ifvar($cx, (($inary && isset($in['integrity'])) ? $in['integrity'] : null), false)) ? ' integrity="'.LR::encq($cx, (($inary && isset($in['integrity'])) ? $in['integrity'] : null)).'" crossorigin="anonymous"' : '').'></script>
';}).''.((LR::ifvar($cx, (($inary && isset($in['inlineScript'])) ? $in['inlineScript'] : null), false)) ? '    <script>
            '.LR::raw($cx, (($inary && isset($in['inlineScript'])) ? $in['inlineScript'] : null)).'
    </script>
' : '').'
</body>
</html>
';
};
