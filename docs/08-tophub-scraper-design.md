# Phase 3.2 方案设计：TopHub 热榜 → AI 分类 → 生成事件

> **数据源**: `https://api.meiyoufan.com/tophub/`  
> **目标**: 获取 25 个平台 274 条热榜 → AI 自动分类 → 匹配子站 → 生成/更新事件  
> **最后更新**: 2026-05-30

---

## 1. 数据源分析

### 1.1 数据结构

```json
{
  "data": {
    "items": [
      {
        "ID": "239633859",       // 唯一 ID
        "title": "释永信一审被判有期徒刑 24 年...",  // 标题
        "thumbnail": "https://...",     // 缩略图
        "url": "https://www.zhihu.com/...",  // 原文链接
        "md5": "17061a1a963d...",   // 去重标识
        "extra": "2632 万热度",      // 热度/排名信息
        "time": "1780061424",      // 时间戳（Unix）
        "nodeids": "6",            // 分类节点
        "topicid": "0",
        "domain": "zhihu.com",     // 域名
        "sitename": "知乎",        // 平台名称
        "logo": "https://...",     // 平台 Logo
        "views": "1 万热度"        // 浏览量
      }
    ]
  }
}
```

### 1.2 25 个数据源分布

| 平台 | 条数 | 域名 | 匹配子站 |
|------|------|------|----------|
| 微博 | 107 | weibo.com | ⭐ 主播呀呀 / 💻 互联网呀呀 |
| 知乎 | 50 | zhihu.com | 全部子站（AI 判定） |
| 虎扑社区 | 50 | bbs.hupu.com | 🎮 游戏呀呀 |
| Zaker | 22 | myzaker.com | 💻 互联网呀呀 |
| IT之家 | 5 | ithome.com | 🤖 AI呀呀 / 💻 互联网呀呀 |
| 抖音短视频 | 5 | douyin.com | ⭐ 主播呀呀 |
| 微信 | 4 | weixin.com | 💻 互联网呀呀 |
| 36氪 | 4 | 36kr.com | 🤖 AI呀呀 / 💻 互联网呀呀 |
| 水木社区 | 4 | smth.org | 💻 互联网呀呀 |
| 虎嗅网 | 3 | huxiu.com | 💻 互联网呀呀 |
| 51吃瓜 | 2 | 51.pw | ⭐ 主播呀呀 |
| 百度 | 2 | baidu.com | 💻 互联网呀呀 |
| 哔哩哔哩 | 2 | bilibili.com | ⭐ 主播呀呀 / 🎮 游戏呀呀 |
| 百度贴吧 | 1 | tieba.baidu.com | ⭐ 主播呀呀 |
| 今日头条 | 2 | toutiao.com | 全部子站（AI 判定） |
| 猫眼电影 | 1 | maoyan.com | ⭐ 主播呀呀 |
| 起点中文网 | 1 | qidian.com | 🎮 游戏呀呀 |
| 雪球 | 1 | xueqiu.com | 💻 互联网呀呀 |
| 少数派 | 1 | sspai.com | 🤖 AI呀呀 |
| V2EX | 1 | v2ex.com | 🤖 AI呀呀 |

---

## 2. 系统架构流程图

```
┌─────────────────────────────────────────────────────────────┐
│                   Step 1: 数据拉取                            │
│                                                             │
│  PHP CLI ──→ curl api.meiyoufan.com/tophub/                 │
│               ↓                                              │
│         解析 274 条 items                                     │
│               ↓                                              │
│         与缓存对比（md5 去重）                                 │
│               ↓                                              │
│         新热榜 → 写入 backend/data/cache/tophub-{date}.json  │
│         旧热榜 → 跳过                                        │
└──────────────────┬──────────────────────────────────────────┘
                   │ 新热榜数据
                   ▼
┌─────────────────────────────────────────────────────────────┐
│               Step 2: AI 分类 + 聚类                         │
│                                                             │
│  将所有 title + sitename 发送给千问，Prompt 要求：             │
│  1. 每条热榜分配到 4 个子站之一（game/ai/tech/star）            │
│  2. 识别相似事件，聚类归组                                      │
│  3. 输出 JSON 格式                                             │
│                                                             │
│  输入: 274 条标题 + 来源                                       │
│  输出: {                                                      │
│    "items": [                                                │
│      {"md5": "...", "site_slug": "game", "title": "..."},    │
│      ...                                                     │
│    ],                                                        │
│    "clusters": [                                             │
│      {                                                       │
│        "event_title": "聚类后的事件标题",                       │
│        "site_slug": "game",                                   │
│        "items": ["md5_1", "md5_2", "md5_3"],                 │
│        "heat_total": 100                                     │
│      },                                                      │
│      ...                                                     │
│    ]                                                         │
│  }                                                            │
└──────────────────┬──────────────────────────────────────────┘
                   │ AI 分类 + 聚类结果
                   ▼
┌─────────────────────────────────────────────────────────────┐
│             Step 3: 事件生成 / 更新                           │
│                                                             │
│  for each cluster（聚类事件组）:                              │
│    ├─ 检查是否已存在（按 md5 组合判断）                         │
│    ├─ 新事件 → 调用 AiSummarizer 生成完整内容                  │
│    │          → 写入 backend/data/events/NNN-slug.md        │
│    └─ 已存在 → 更新 views + updated_at                       │
│                                                             │
│  生成完成：                                                   │
│    - 新事件写入 .md 文件                                      │
│    - 热榜数据写入 raw_feeds（可选）                            │
└──────────────────┬──────────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────────┐
│              Step 4: 前端自动展示                              │
│                                                             │
│  /api/events     → MarkdownEventReader 读取新事件              │
│  /hot            → 按 views 排序展示                           │
│  /s/{siteSlug}   → 按子站展示                                  │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. 核心代码实现

### 3.1 TopHub 采集器

```php
<?php
// backend/scrapers/tophub-scraper.php
/**
 * TopHub 热榜采集器
 * 获取全网热榜 → AI 分类 → 生成事件
 *
 * 用法: php backend/scrapers/tophub-scraper.php [--dry-run]
 *       --dry-run: 只获取和分类，不生成 .md 文件
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../src/DB.php';
require __DIR__ . '/../src/AiClient.php';
require __DIR__ . '/../src/MarkdownEventReader.php';

use App\DB;
use App\AiClient;
use App\MarkdownEventReader;

class TopHubScraper
{
    private const API_URL = 'https://api.meiyoufan.com/tophub/';
    private const CACHE_DIR = __DIR__ . '/../data/cache';
    private const EVENTS_DIR = __DIR__ . '/../data/events';

    private AiClient $ai;
    private bool $dryRun;

    public function __construct(bool $dryRun = false)
    {
        $this->ai = new AiClient();
        $this->dryRun = $dryRun;

        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0755, true);
        }
    }

    // ==================== 主流程 ====================

    public function run(): void
    {
        echo "🚀 TopHub 热榜采集开始\n";
        echo ($this->dryRun ? "🔍 模式: 仅预览（不生成事件）\n" : "📝 模式: 生成事件\n");

        // Step 1: 拉取热榜
        $items = $this->fetchHotlist();
        if (empty($items)) {
            echo "❌ 获取热榜失败\n";
            return;
        }
        echo "✅ 获取到 " . count($items) . " 条热榜\n";

        // Step 2: 缓存 + 去重
        $newItems = $this->deduplicate($items);
        echo "✅ 新增 " . count($newItems) . " 条，已存在 " . (count($items) - count($newItems)) . " 条\n";

        if (empty($newItems)) {
            echo "⏭️  没有新的热榜数据，跳过\n";
            return;
        }

        // 保存缓存
        $this->saveCache($items);

        // Step 3: AI 分类 + 聚类
        echo "🤖 AI 分类中...\n";
        $classified = $this->aiClassify($newItems);
        if (empty($classified)) {
            echo "❌ AI 分类失败\n";
            return;
        }

        $clusters = $classified['clusters'] ?? [];
        echo "✅ AI 聚类完成，识别出 " . count($clusters) . " 个事件组\n";

        // Step 4: 生成事件
        if (!$this->dryRun) {
            $generated = 0;
            foreach ($clusters as $cluster) {
                if ($this->generateEvent($cluster, $newItems)) {
                    $generated++;
                }
            }
            echo "✅ 生成/更新 " . $generated . " 个事件\n";
        } else {
            echo "\n📋 预览：将生成的事件\n";
            foreach ($clusters as $i => $c) {
                echo sprintf("  %d. [%s] %s (%d 条热榜)\n",
                    $i + 1,
                    $c['site_slug'] ?? '?',
                    $c['event_title'] ?? '未知',
                    count($c['items'] ?? [])
                );
            }
        }

        echo "\n🎉 TopHub 采集完成\n";
    }

    // ==================== Step 1: 拉取 ====================

    private function fetchHotlist(): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; 51chigua/1.0)',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            error_log("[TopHub] 请求失败: HTTP {$httpCode}");
            return [];
        }

        $data = json_decode($response, true);
        return $data['data']['items'] ?? [];
    }

    // ==================== Step 2: 去重 ====================

    private function deduplicate(array $items): array
    {
        $newItems = [];

        foreach ($items as $item) {
            $md5 = $item['md5'] ?? '';
            if (empty($md5)) continue;

            // 检查缓存文件
            $cacheFile = self::CACHE_DIR . '/seen-' . $md5;
            if (file_exists($cacheFile)) {
                // 已有缓存，跳过
                continue;
            }

            $newItems[] = $item;
            // 标记为已见
            file_put_contents($cacheFile, date('Y-m-d H:i:s'));
        }

        return $newItems;
    }

    // ==================== Step 3: AI 分类 ====================

    private function aiClassify(array $items): ?array
    {
        // 构建输入：仅提取关键信息，减少 token 消耗
        $input = [];
        foreach ($items as $item) {
            $input[] = [
                'md5'  => $item['md5'],
                'title' => $item['title'],
                'site'  => $item['sitename'],
                'domain' => $item['domain'],
                'views' => $item['views'] ?? '',
            ];
        }

        $prompt = <<<PROMPT
你是一个热榜分类专家。请将以下热搜条目分配到对应的子站，并进行事件聚类。

## 子站定义
- **game**（游戏呀呀）: 游戏、电竞、主播、cosplay、二次元、动漫、漫画、小说
- **ai**（AI呀呀）: 人工智能、大模型、机器学习、科技前沿、编程
- **tech**（互联网呀呀）: 互联网公司、创业、融资、反垄断、科技政策、互联网产品
- **star**（主播呀呀）: 主播、网红、直播、短视频、娱乐八卦、明星

## 任务
1. 将每个条目分配到一个子站（game/ai/tech/star）
2. 将讨论同一事件的条目聚类为一组
3. 为每个聚类组起一个简洁的事件标题

## 输出格式（纯 JSON）
{
  "items": [
    {"md5": "abc123", "site_slug": "game", "title": "原标题"}
  ],
  "clusters": [
    {
      "event_title": "事件标题（20字以内）",
      "site_slug": "game",
      "items": ["md5_1", "md5_2", "md5_3"],
      "summary": "一句话概括事件"
    }
  ]
}

## 热榜数据（共 {count($input)} 条）
PROMPT;

        foreach ($input as $i => $item) {
            $prompt .= sprintf("\n%d. [%s] %s (热度: %s)",
                $i + 1, $item['site'], $item['title'], $item['views']);
        }

        $rawJson = $this->ai->testChat($prompt);
        if (!$rawJson || !($rawJson['ok'] ?? false)) {
            error_log('[TopHub] AI 调用失败: ' . ($rawJson['error'] ?? '未知'));
            return null;
        }

        $content = $rawJson['reply'] ?? '';

        // 去除 markdown 代码块
        $content = preg_replace('/^```(?:json)?\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        $parsed = json_decode(trim($content), true);
        if (!$parsed) {
            error_log('[TopHub] AI 输出 JSON 解析失败');
            return null;
        }

        return $parsed;
    }

    // ==================== Step 4: 生成事件 ====================

    private function generateEvent(array $cluster, array $allItems): bool
    {
        $eventTitle = $cluster['event_title'] ?? '';
        $siteSlug = $cluster['site_slug'] ?? 'tech';
        $md5List = $cluster['items'] ?? [];
        $clusterSummary = $cluster['summary'] ?? '';

        if (empty($eventTitle) || empty($md5List)) {
            return false;
        }

        // 检查是否已存在（通过 md5 组合判断）
        $clusterKey = md5(implode(',', sort($md5List)));
        $markerFile = self::CACHE_DIR . '/cluster-' . $clusterKey;
        if (file_exists($markerFile)) {
            echo "  ⏭️  跳过已存在事件: {$eventTitle}\n";
            return false;
        }

        // 获取 site 信息
        $siteMap = [
            'game' => ['id' => 1, 'name' => '游戏呀呀'],
            'ai'   => ['id' => 2, 'name' => 'AI呀呀'],
            'tech' => ['id' => 3, 'name' => '互联网呀呀'],
            'star' => ['id' => 4, 'name' => '主播呀呀'],
        ];
        $site = $siteMap[$siteSlug] ?? $siteMap['tech'];

        // 构建 feed 文本（所有相关条目的标题）
        $titles = [];
        foreach ($allItems as $item) {
            if (in_array($item['md5'], $md5List)) {
                $titles[] = sprintf("[%s] %s (热度: %s)",
                    $item['sitename'], $item['title'], $item['views'] ?? '');
            }
        }
        $feedText = implode("\n", $titles);

        // 调用 AiSummarizer 生成完整内容
        echo "  📝 生成事件: {$eventTitle}\n";
        $aiResult = \App\AiSummarizer::summarize($eventTitle, $feedText);

        // 写入 Markdown 文件
        $eventsDir = self::EVENTS_DIR;
        $existing = glob($eventsDir . '/*.md');
        $nextNum = count($existing) + 1;
        $numStr = str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);

        // 生成 slug
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(
            preg_replace('/[\s]+/', '-', preg_replace('/[^\w\s]/', '', $eventTitle))
        ));
        if (empty($slug)) $slug = 'tophub-' . substr(md5($eventTitle . time()), 0, 8);

        $now = date('Y-m-d H:i');
        $mdContent = $this->buildMarkdownFile(
            $aiResult, $site, $slug, $nextNum, $now, $clusterSummary
        );

        $filePath = "{$eventsDir}/{$numStr}-{$slug}.md";
        file_put_contents($filePath, $mdContent);

        // 标记已处理
        file_put_contents($markerFile, $now);

        echo "  ✅ 写入: {$numStr}-{$slug}.md\n";
        return true;
    }

    // ==================== 辅助方法 ====================

    private function buildMarkdownFile(
        array $ai, array $site, string $slug, int $id, string $now, string $summary
    ): string {
        $timelineJson = json_encode($ai['timeline'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $opinionsJson = json_encode($ai['opinions'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $personsJson  = json_encode($ai['persons'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $yamlTimeline = $this->yamlArray($ai['timeline'] ?? []);
        $yamlOpinions = $this->yamlArray($ai['opinions'] ?? []);
        $yamlPersons  = $this->yamlArray($ai['persons'] ?? []);

        return <<<MD
---
id: {$id}
slug: {$slug}
site_id: {$site['id']}
site_name: {$site['name']}
title: {$ai['title']}
summary: {$summary}
content_type: news
status: fermenting
views: 0
first_seen: '{$now}'
updated_at: '{$now}'
timeline:
{$yamlTimeline}
opinions:
{$yamlOpinions}
persons:
{$yamlPersons}
---

{$ai['body']}
MD;
    }

    private function yamlArray(array $list): string
    {
        if (empty($list)) return "  []\n";

        $yaml = "";
        foreach ($list as $item) {
            $yaml .= "  -\n";
            foreach ($item as $key => $val) {
                $yaml .= "    {$key}: " . $this->yamlValue($val) . "\n";
            }
        }
        return $yaml;
    }

    private function yamlValue($val): string
    {
        if (is_string($val) && (strpos($val, ':') !== false || strpos($val, '#') !== false)) {
            return "'" . addcslashes($val, "'\\") . "'";
        }
        if (is_int($val)) return (string)$val;
        if (is_null($val)) return 'null';
        return (string)$val;
    }

    private function saveCache(array $items): void
    {
        $date = date('Ymd-His');
        $file = self::CACHE_DIR . "/tophub-{$date}.json";
        file_put_contents($file, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

// 运行
$dryRun = in_array('--dry-run', $argv);
(new TopHubScraper($dryRun))->run();
