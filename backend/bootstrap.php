<?php
/**
 * 51呀呀 后端启动引导
 *
 * 1. 加载 composer autoload
 * 2. 通过 Dotenv 读取 backend/.env → 注入 $_ENV
 *
 * 所有 PHP 入口文件（index.php、scrapers/*.php、scripts/*.php）
 * 都应该首先 require 此文件。
 */

// 项目根目录（此文件在 backend/bootstrap.php）
define('APP_ROOT', __DIR__);

// 1. 先加载 composer autoload（包含 phpdotenv 类）
require APP_ROOT . '/vendor/autoload.php';

// 2. 加载 .env 到 $_ENV（.env 不存在时不报错）
$dotenv = Dotenv\Dotenv::createImmutable(APP_ROOT);
$dotenv->safeLoad();
