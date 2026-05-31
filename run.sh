#!/usr/bin/env bash
# 一键本地运行：同时启动 PHP 后端(:8000) 与 Vite 前端(:5173)
set -e
ROOT="$(cd "$(dirname "$0")" && pwd)"

echo "▶ 初始化数据库 + 种子数据…"
php "$ROOT/backend/scripts/init.php"

echo "▶ 启动 PHP 后端  http://localhost:8000"
php -S localhost:8000 -t "$ROOT/backend/public" >/tmp/chigua-php.log 2>&1 &
PHP_PID=$!

cleanup() { echo; echo "停止服务…"; kill $PHP_PID 2>/dev/null || true; }
trap cleanup EXIT INT TERM

echo "▶ 安装前端依赖（首次较慢）…"
cd "$ROOT/frontend"
[ -d node_modules ] || npm install

echo "▶ 启动前端  http://localhost:5173"
npm run dev
