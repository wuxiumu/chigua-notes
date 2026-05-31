<?php
// 简单配置
return [
    'db_path' => __DIR__ . '/../data/chigua.sqlite',
    // 允许的子站
    'sites'   => ['game', 'ai', 'tech', 'star'],
    // 允许的 CORS 来源（数组，前端跨域请求时自动匹配）
    'cors_allowed_origins' => [
        'https://www.51chigua.com',
        'https://51chigua.com',
        'http://localhost:5173',   // 开发环境
    ],
];
