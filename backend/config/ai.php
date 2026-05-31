<?php
/**
 * AI 配置 — 通义千问（DashScope）
 *
 * 环境变量从 backend/.env 读取（由 vendor/autoload 加载 phpdotenv）
 *
 * 升级模型只需改 .env 中的 DASHSCOPE_MODEL 这一行：
 *   qwen-turbo  → 最快最便宜
 *   qwen-plus   → 性价比（推荐默认）
 *   qwen-max    → 最强最贵
 *   qwen3-max   → 下一代（发布后改 .env）
 */
return [
    // ========== 模型版本 ==========
    'model'         => $_ENV['DASHSCOPE_MODEL'] ?? 'qwen-plus',

    // DashScope OpenAI 兼容接口
    'api_url'       => 'https://dashscope.aliyuncs.com/compatible-mode/v1',

    // ========== API Key（从 .env 读取） ==========
    'api_key'       => $_ENV['DASHSCOPE_API_KEY'] ?? '',

    // ========== 请求参数 ==========
    'temperature'   => 0.7,
    'max_tokens'    => 4000,
    'timeout'       => 15,       // 健康检查超时（秒）
    'test_timeout'  => 60,       // 完整测试超时（秒）
    'long_timeout'  => 120,      // 长任务超时（分类、摘要等）

    // ========== 降级策略 ==========
    'max_retries'   => 2,
    'retry_delay'   => 3,        // 秒
    'fallback_model' => 'qwen-turbo',  // 主模型失败时降级

    // ========== 开关 ==========
    // true = 调用真实 API，false = 所有 AI 调用返回 mock
    'enabled'       => filter_var($_ENV['DASHSCOPE_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOL),
];
