<?php
// 简单配置
return [
    'db_path' => __DIR__ . '/../data/chigua.sqlite',
    // 允许的子站
    'sites'   => ['game', 'ai', 'tech', 'star'],
    // 允许的 CORS 来源（数组，'*' = 允许所有域名）
    // 生产锁定具体域名例：['https://www.51chigua.com', 'https://51chigua.com']
    'cors_allowed_origins' => [
        'https://note.51chigua.com',
        'https://note-api.51chigua.com',
    ],
];
