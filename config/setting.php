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
        // ジョブ作成時に expires_at へ記録され、BcWpExport.cleanup が期限切れを削除する
        'jobExpireDays' => 14,
    ],
];
