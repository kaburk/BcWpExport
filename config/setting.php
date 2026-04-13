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
        // エクスポートジョブの有効期限（日数）
        // ジョブ作成時に expires_at へ記録される。クリーンアップコマンドは未実装
        'jobExpireDays' => 14,
    ],
];
