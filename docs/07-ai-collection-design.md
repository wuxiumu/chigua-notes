# Phase 3 设计方案：AI 接入 + 数据采集

> **目标**: 快速跑通 MVP 后端 — 采集 → AI 整理 → 发布为 Markdown 事件  
> **AI 模型**: 通义千问（DashScope），版本可配置升级  
> **预计工时**: 3-4 天  
> **最后更新**: 2026-05-30

---

## 1. 整体架构

```
┌─────────────────────────────────────────────────────────┐
│                    数据采集层（Cron）                      │
│                                                         │
│  微博热搜 ──┐                                            │
│  B站热搜 ──┼──→ scrapers/*.php ──→ raw_feeds (SQLite)   │
│  NGA 热帖 ─┘                                            │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│                  AI 处理层（Admin / API）                  │
│                                                         │
│  /api/generate  ──→ AiSummarizer ──→ 通义千问 API         │
│                      ↓                                   │
│                  结构化输出（JSON）                        │
│                      ↓                                   │
│                  写入 .md 文件 + SQLite                   │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│                  数据输出层（Markdown）                     │
│                                                         │
│  backend/data/events/NNN-slug.md                        │
│  frontmatter: 结构化数据                                 │
│  body: 事件正文                                          │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│                  前端展示层（React）                       │
│                                                         │
│  /api/events       ← MarkdownEventReader.php             │
│  /api/events/slug  ← MarkdownEventReader.php             │
└─────────────────────────────────────────────────────────┘
```

---

## 2. AI 接入设计

### 2.1 通义千问 API 选型

| 方案 | 端点 | 说明 |
|------|------|------|
| **DashScope（推荐）** | `https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions` | OpenAI 兼容接口，HTTP 调用 |
| DashScope SDK | `alibabacloud/dashscope-php-sdk` | 需 composer，增加依赖 |
| 阿里云 SDK | `alibabacloud/client` | 过重，不推荐 |

**选择 OpenAI 兼容 HTTP 接口**，理由：
- 无需安装 composer/SDK，纯 `curl` 调用
- 未来换模型只需改 URL 和 model 名
- PHP 内置 `curl` 即可，零外部依赖

### 2.2 模型版本配置

所有 AI 相关配置集中在 `backend/config/ai.php`：

```php
<?php
return [
    // 模型版本（升级时只需改这一行）
    'model'         => 'qwen-plus',

    // API 端点
    'api_url'       => 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions',

    // API Key（从环境变量读取，不写死在代码中）
    'api_key'       => getenv('DASHSCOPE_API_KEY') ?: '',

    // 请求参数
    'temperature'   => 0.7,
    'max_tokens'    => 4000,
    'timeout'       => 30,  // 秒

    // 重试配置
    'max_retries'   => 2,
    'retry_delay'   => 3,   // 秒

    // 降级：主模型失败时使用的备用模型
    'fallback_model' => 'qwen-turbo',

    // 开关：true 调用真实 API，false 返回 mock
    'enabled'       => true,
];
```

### 2.3 模型升级路径

```
当前: qwen-plus（性价比最优）
  ↓ 改一行配置
v1.1: qwen-max（更强的推理和写作）
  ↓ 改一行配置
v2.0: qwen3-max（下一代模型）
  ↓ 改端点
v3.0: 其他厂商（换 api_url + model 即可）
```

**升级只需改 `config/ai.php` 的 `model` 字段，零代码改动。**

### 2.4 AiSummarizer 重构

```php
// backend/src/AiSummarizer.php（新结构）

class AiSummarizer
{
    private static array $config;

    public static function summarize(string $rawTitle, string $rawText): array
    {
        self::$config = require __DIR__ . '/../config/ai.php';

        // 开关关闭时降级为 mock
        if (!self::$config['enabled']) {
            return self::mockFallback($rawTitle, $rawText);
        }

        // 构建 Prompt
        $prompt = self::buildPrompt($rawTitle, $rawText);

        // 调用 API（带重试）
        $response = self::callApiWithRetry($prompt);

        if ($response === null) {
            return self::mockFallback($rawTitle, $rawText);
        }

        // 解析 JSON 输出
        return self::parseResponse($response);
    }

    // ---- 私有方法 ----

    private static function buildPrompt(string $title, string $text): string
    {
        return <<<PROMPT
你是一个专业的新闻事件编辑。请根据以下原始内容，整理成一篇结构化的事件报告。

## 要求
1. 标题要简洁，适合搜索引擎，不超过 50 字
2. 摘要 100 字以内，概括事件核心
3. 正文按「事件背景 → 详细经过 → 各方回应 → 后续影响」结构
4. 时间线按时间顺序排列，每条包含 happened_at、title、detail
5. 观点至少 3 条，覆盖官方(official)、媒体(media)、网友(player)
6. 保持中立客观，不加入个人判断

## 输出格式
必须输出纯 JSON，不要其他文字。格式如下：
{
  "title": "事件标题",
  "summary": "100字以内摘要",
  "body": "Markdown格式正文",
  "timeline": [
    {"happened_at": "YYYY-MM-DD HH:mm", "title": "节点标题", "detail": "详情"}
  ],
  "opinions": [
    {"side": "official", "source": "来源", "content": "内容"}
  ],
  "persons": [
    {"name": "相关人物"}
  ]
}

## 原始标题
{$title}

## 原始内容
{$text}
PROMPT;
    }

    private static function callApiWithRetry(string $prompt): ?string
    {
        $config = self::$config;
        $maxRetries = $config['max_retries'];

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $result = self::callApi($prompt, $config['model']);
            if ($result !== null) return $result;

            // 主模型失败，最后一次尝试备用模型
            if ($attempt == $maxRetries && !empty($config['fallback_model'])) {
                $result = self::callApi($prompt, $config['fallback_model']);
                if ($result !== null) return $result;
            }

            if ($attempt < $maxRetries) {
                sleep($config['retry_delay']);
            }
        }

        return null;
    }

    private static function callApi(string $prompt, string $model): ?string
    {
        $config = self::$config;
        $apiKey = $config['api_key'];

        if (empty($apiKey)) {
            error_log('[AI] API Key 未配置');
            return null;
        }

        $body = json_encode([
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => '你是一个专业的新闻事件编辑，擅长从碎片化信息中提取关键时间线和多方观点。'],
                ['role' => 'user',   'content' => $prompt],
            ],
            'temperature' => $config['temperature'],
            'max_tokens'  => $config['max_tokens'],
            'response_format' => ['type' => 'json_object'],  // 强制 JSON 输出
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $config['api_url'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $config['timeout'],
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("[AI] Curl 错误 ({$model}): {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("[AI] API 错误 ({$model}): HTTP {$httpCode} - {$response}");
            return null;
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private static function parseResponse(string $rawJson): array
    {
        // 去除可能的 markdown 代码块包裹
        $rawJson = preg_replace('/^```(?:json)?\s*/', '', $rawJson);
        $rawJson = preg_replace('/\s*```$/', '', $rawJson);

        $parsed = json_decode($rawJson, true);
        if (!$parsed) {
            throw new \RuntimeException('AI 输出 JSON 解析失败');
        }

        // 补充默认值（防止 AI 漏输出某些字段）
        return [
            'title'    => $parsed['title'] ?? '未命名事件',
            'summary'  => $parsed['summary'] ?? '',
            'body'     => $parsed['body'] ?? '',
            'timeline' => is_array($parsed['timeline']) ? $parsed['timeline'] : [],
            'opinions' => is_array($parsed['opinions']) ? $parsed['opinions'] : [],
            'persons'  => is_array($parsed['persons']) ? $parsed['persons'] : [],
        ];
    }

    private static function mockFallback(string $title, string $text): array
    {
        // 原有 mock 逻辑，作为 API 失败时的降级方案
        // ...（保持原有实现）
    }
}
```

### 2.5 AI 生成直接输出 Markdown 文件

修改 `/api/generate` 端点，AI 生成的事件直接写 `.md` 文件：

```php
// backend/public/index.php 中 generate 路由修改

if ($parts === ['generate'] && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = body();
    $siteSlug = $b['site'] ?? 'game';
    $site = DB::one('SELECT * FROM sites WHERE slug = ?', [$siteSlug]);
    if (!$site) json(['error' => 'unknown site'], 400);

    // 获取原始 feed
    $feed = DB::one('SELECT * FROM raw_feeds WHERE id = ? OR processed = 0 ORDER BY id LIMIT 1',
        [$b['feed_id'] ?? 0]);
    if (!$feed) json(['error' => 'no feed to process'], 404);

    // AI 整理
    $ai = AiSummarizer::summarize($feed['raw_title'], $feed['raw_text']);

    // 生成 slug
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(
        preg_replace('/[\s]+/', '-', preg_replace('/[^\w\s]/', '', $ai['title']))
    ));
    if (empty($slug)) $slug = 'evt-' . substr(md5($ai['title'] . microtime()), 0, 10);

    // 获取最大 ID
    $eventsDir = __DIR__ . '/../data/events';
    $existing = glob($eventsDir . '/*.md');
    $nextNum = count($existing) + 1;
    $numStr = str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);

    // 写入 Markdown 文件
    $now = date('Y-m-d H:i');
    $mdContent = self::buildMarkdownFile($ai, $site, $now);
    $filePath = "{$eventsDir}/{$numStr}-{$slug}.md";
    file_put_contents($filePath, $mdContent);

    // 标记 feed 已处理
    DB::exec('UPDATE raw_feeds SET processed = 1 WHERE id = ?', [$feed['id']]);

    json([
        'event_id' => $nextNum,
        'slug'     => $slug,
        'file'     => "{$numStr}-{$slug}.md",
        'ok'       => true,
    ]);
}
```

---

## 3. 数据采集设计

### 3.1 架构

```
采集脚本 (scrapers/*.php)
    ↓ 定时执行 (cron)
raw_feeds 表 (SQLite)
    ↓
/api/generate (AI 整理)
    ↓
backend/data/events/*.md
```

**原则**:
- 每个数据源一个独立脚本
- 输出统一格式写入 `raw_feeds` 表
- 脚本可手动运行，也可 cron 定时
- 去重：相同标题不重复写入

### 3.2 第一批采集源（MVP）

| 数据源 | 方式 | 优先级 | 预计工时 |
|--------|------|--------|----------|
| 微博热搜 | RSS / 网页抓取 | P0 | 4h |
| B站热搜 | 网页抓取 | P0 | 4h |
| 知乎热榜 | RSS | P1 | 2h |

### 3.3 采集脚本模板

```php
<?php
// backend/scrapers/weibo-hot.php
/**
 * 微博热搜采集器
 * 用法: php backend/scrapers/weibo-hot.php
 */

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/DB.php';

use App\DB;

class WeiboHotScraper
{
    // 去重：最近 24 小时内已有相同标题则跳过
    private static function isDuplicate(string $title): bool
    {
        $exists = DB::one(
            'SELECT id FROM raw_feeds WHERE raw_title = ? AND created_at > datetime("now", "-24 hours")',
            [$title]
        );
        return $exists !== null;
    }

    // 写入 raw_feeds
    private static function saveFeed(string $title, string $text, string $source = '微博'): void
    {
        if (self::isDuplicate($title)) {
            echo "跳过重复: {$title}\n";
            return;
        }

        DB::exec(
            'INSERT INTO raw_feeds (source, raw_title, raw_text, created_at) VALUES (?,?,?,?)',
            [$source, $title, $text, date('Y-m-d H:i:s')]
        );
        echo "✅ 入库: {$title}\n";
    }

    // 主逻辑
    public static function run(): void
    {
        $html = file_get_contents('https://s.weibo.com/top/summary');
        if (!$html) {
            echo "❌ 微博热搜页面获取失败\n";
            return;
        }

        // 解析热搜列表
        preg_match_all('/<td class="td-02">.*?<a[^>]*>(.*?)<\/a>.*?<span>(.*?)<\/span>/s', $html, $matches);

        $count = 0;
        foreach ($matches[1] as $i => $title) {
            $title = strip_tags(trim($title));
            if (empty($title) || strlen($title) < 5) continue;

            $heat = strip_tags(trim($matches[2][$i]));
            $text = "微博热搜，热度: {$heat}";

            self::saveFeed($title, $text);
            $count++;

            if ($count >= 20) break;  // 每次最多采集 20 条
        }

        echo "共采集 {$count} 条\n";
    }
}

WeiboHotScraper::run();
```

### 3.4 Cron 配置

```bash
# 每 30 分钟采集一次
*/30 * * * * cd /path/to/chigua && php backend/scrapers/weibo-hot.php >> logs/scrape.log 2>&1
*/30 * * * * cd /path/to/chigua && php backend/scrapers/bilibili-hot.php >> logs/scrape.log 2>&1

# 每天凌晨 2 点清理 7 天前的已处理 feed
0 2 * * * cd /path/to/chigua && php backend/scripts/cleanup-feeds.php >> logs/cleanup.log 2>&1
```

---

## 4. 文件变动清单

| 操作 | 文件路径 | 说明 |
|------|----------|------|
| **新增** | `backend/config/ai.php` | AI 配置（模型版本、API Key、端点） |
| **重写** | `backend/src/AiSummarizer.php` | 接入千问 API，保留 mock 降级 |
| **修改** | `backend/public/index.php` | `/api/generate` 输出 Markdown 文件 |
| **新增** | `backend/scrapers/weibo-hot.php` | 微博热搜采集 |
| **新增** | `backend/scrapers/bilibili-hot.php` | B站热搜采集 |
| **新增** | `backend/scripts/cleanup-feeds.php` | 清理过期 feed |
| **新增** | `backend/logs/` | 日志目录 |
| **修改** | `backend/src/MarkdownEventReader.php` | 新增 `getNextId()` 方法 |

---

## 5. 环境变量

生产环境需要设置以下环境变量：

```bash
# .env 或 shell
export DASHSCOPE_API_KEY="sk-xxxxxxxxxxxx"
```

本地开发可以在 `.env` 文件配置：

```bash
# backend/.env（已加入 .gitignore）
DASHSCOPE_API_KEY=sk-your-key-here
AI_MODEL=qwen-plus
AI_ENABLED=true
```

---

## 6. 错误处理策略

```
AI API 调用
    ↓ 失败
重试 2 次（间隔 3s）
    ↓ 仍失败
切换备用模型（qwen-turbo）
    ↓ 仍失败
降级为 mock 数据（保证流程不中断）
    ↓
写入 .md 文件（标记为 AI 生成失败，人工审核）
```

所有错误通过 `error_log()` 写入 PHP 错误日志，便于排查。

---

## 7. 成本估算

| 项目 | 单价 | 预估用量 | 月费用 |
|------|------|----------|--------|
| 千问 qwen-plus | ¥0.008/千 token | 500 次/天 × 2000 token | ~¥80/月 |
| 备用 qwen-turbo | ¥0.003/千 token | 降级时少量使用 | ~¥5/月 |
| **合计** | | | **~¥85/月** |

> 按每天 500 条 feed 采集估算，初期实际用量会更低。

---

## 8. 开发顺序

| 步骤 | 任务 | 工时 | 验证方式 |
|------|------|------|----------|
| 1 | 创建 `config/ai.php` | 0.5h | `php -r "print_r(require 'config/ai.php');"` |
| 2 | 重写 `AiSummarizer.php` | 2h | 手动调用 API，确认 JSON 输出正确 |
| 3 | 修改 `index.php` generate 路由 | 1.5h | POST `/api/generate`，检查 .md 文件生成 |
| 4 | 编写微博热搜采集器 | 2h | `php scrapers/weibo-hot.php`，检查 raw_feeds |
| 5 | 端到端测试：采集 → AI → .md | 1h | 完整流程跑通 |
| 6 | 错误处理 + 日志 | 1h | 模拟 API 失败，验证降级 |

**总计: ~8h（1-2 天完成核心功能）**
