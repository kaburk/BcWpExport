<?php

return [
    'BcApp' => [
        'adminNavigation' => [
            'Contents' => [
                'BcWpExport' => [
                    'title' => __d('baser_core', 'WordPressエクスポート'),
                    'url' => [
                        'Admin' => true,
                        'plugin' => 'BcWpExport',
                        'controller' => 'wp_exports',
                        'action' => 'index',
                    ],
                ],
            ],
        ],
    ],
    'BcWpExport' => [
        'jobExpireDays' => 3,
        'batchSize' => 100,
    ],
];
