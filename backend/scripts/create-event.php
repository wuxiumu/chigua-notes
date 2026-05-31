#!/usr/bin/env php
<?php
/**
 * 快速创建新事件 Markdown 文件
 *
 * 用法：
 *   php backend/scripts/create-event.php --title "事件标题" --site game
 *   php backend/scripts/create-event.php --title "标题" --slug my-slug --site ai --status fermenting --type news
 *
 * 参数：
 *   --title     事件标题（必填）
 *   --slug      事件 slug（可选，默认从标题生成）
 *   --site      子站 slug：game/ai/tech/star（默认 game）
 *   --status    状态：fermenting/responded/ended（默认 fermenting）
 *   --type      类型：news/analysis/wiki（默认 news）
 *   --summary   事件摘要（可选）
 */

$opts = getopt('', ['title:', 'slug:', 'site:', 'status:', 'type:', 'summary:']);

$title = $opts['title'] ?? null;
if (!$title) {
    echo "用法: php {$argv[0]} --title \"事件标题\" [--slug xxx] [--site game] [--status fermenting] [--type news]\n";
    exit(1);
}

$slug = $opts['slug'] ?? pinyin_slug($title);
$site = $opts['site'] ?? 'game';
$status = $opts['status'] ?? 'fermenting';
$type = $opts['type'] ?? 'news';
$summary = $opts['summary'] ?? '';

// 站点映射
$siteMap = [
    'game' => ['id' => 1, 'name' => '游戏呀呀'],
    'ai'   => ['id' => 2, 'name' => 'AI呀呀'],
    'tech' => ['id' => 3, 'name' => '互联网呀呀'],
    'star' => ['id' => 4, 'name' => '主播呀呀'],
];

if (!isset($siteMap[$site])) {
    echo "错误: 未知子站 '{$site}'。可选: game, ai, tech, star\n";
    exit(1);
}

$siteInfo = $siteMap[$site];

// 确定文件序号
$eventsDir = __DIR__ . '/../data/events';
$existing = glob($eventsDir . '/*.md');
$nextNum = count($existing) + 1;
$numStr = str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);

// 生成临时 ID（基于已有事件的最大 ID + 1）
$maxId = 0;
foreach ($existing as $f) {
    $content = file_get_contents($f);
    if (preg_match('/^id:\s*(\d+)/m', $content, $m)) {
        $maxId = max($maxId, (int)$m[1]);
    }
}
$newId = $maxId + 1;

$now = date('Y-m-d H:i');
$filename = "{$numStr}-{$slug}.md";
$filepath = "{$eventsDir}/{$filename}";

if (file_exists($filepath)) {
    echo "错误: 文件已存在: {$filename}\n";
    exit(1);
}

$template = <<<MD
---
id: {$newId}
slug: {$slug}
site_id: {$siteInfo['id']}
site_name: {$siteInfo['name']}
title: {$title}
summary: {$summary}
content_type: {$type}
status: {$status}
views: 0
first_seen: '{$now}'
updated_at: '{$now}'
timeline:
  - id: 1
    happened_at: '{$now}'
    title: 事件开始
    detail: 事件首次出现或开始发酵
opinions:
  - id: 1
    side: official
    source: 官方来源
    content: 官方回应内容...
  - id: 2
    side: media
    source: 媒体来源
    content: 媒体评论内容...
  - id: 3
    side: player
    source: 网友/当事人
    content: 网友观点...
persons:
  - id: {$newId}
    name: 相关人物A
  - id: {$newId}
    name: 相关人物B
---

## 事件背景

事件发生的背景和起因...

## 详细经过

事件的具体发展过程...

## 各方回应

不同方面的回应和表态...

## 后续影响

事件带来的后续影响和讨论...

MD;

file_put_contents($filepath, $template);
echo "✅ 事件已创建: {$filename}\n";
echo "   ID: {$newId}\n";
echo "   路径: backend/data/events/{$filename}\n";
echo "   子站: {$siteInfo['name']}\n";

/**
 * 简易中文转拼音 slug（仅保留非中文字符）
 * 实际项目中建议用 pinyin 库
 */
function pinyin_slug(string $text): string {
    // 移除中文，保留英文、数字、空格
    $en = preg_replace('/[^\x00-\x7F]/', '', $text);
    $en = strtolower(trim($en));
    $en = preg_replace('/[^a-z0-9]+/', '-', $en);
    $en = trim($en, '-');
    return $en ?: 'untitled-' . substr(md5($text), 0, 6);
}
