<?php
return [
    'type' => 'Plugin',
    'title' => __d('baser_core', 'WordPressエクスポート'),
    'description' => __d('baser_core', 'baserCMS のコンテンツを WordPress 用 WXR として出力するプラグインです。'),
    'author' => 'kaburk',
    'url' => 'https://blog.kaburk.com/',
    'adminLink' => [
        'prefix' => 'Admin',
        'plugin' => 'BcWpExport',
        'controller' => 'WpExports',
        'action' => 'index',
    ],
];
