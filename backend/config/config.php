<?php
// 简单配置
return [
    'db_path' => __DIR__ . '/../data/chigua.sqlite',
    // 允许的子站
    'sites'   => ['game', 'ai', 'tech', 'star'],
    // 前端地址，用于 CORS
    'cors_origin' => '*',
];
