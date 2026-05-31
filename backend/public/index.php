<?php
declare(strict_types=1);
/**
 * 51呀呀 API 入口
 *
 * 路由说明：
 * GET  /api/sites           所有子站
 * GET  /api/sites/{slug}    子站详情 + 栏目
 * GET  /api/events          事件列表（支持 site/status/type/q/page 参数）
 * GET  /api/events/{slug}   事件详情（含时间线、观点、人物）
 * POST /api/feeds           采集落地（写入 raw_feeds）
 * POST /api/generate        AI 摘要生成 + 发布
 * GET  /api/ai/health       AI 健康检查（Key 配置、网络连通性）
 * GET  /api/ai/test         AI 完整测试（实际调用模型，消耗少量 token）
 *
 * 数据源说明：
 * - 子站/栏目 数据来自 SQLite（backend/data/chigua.sqlite）
 * - 事件数据来自 Markdown 文件（backend/data/events/*.md）
 *   每个 md 文件 = 一个事件，frontmatter 存结构化数据，body 存正文
 *
 * 环境变量：
 * - 通过 composer autoload 自动加载 backend/.env（phpdotenv）
 * - 所有配置变量通过 $_ENV 读取
 */

// 1. Bootstrap（加载 .env → $_ENV，加载 composer autoload）
require __DIR__ . '/../bootstrap.php';

require __DIR__ . '/../src/DB.php';
require __DIR__ . '/../src/AiSummarizer.php';
require __DIR__ . '/../src/AiClient.php';
require __DIR__ . '/../src/MarkdownEventReader.php';
require __DIR__ . '/../src/CacheReader.php';
require __DIR__ . '/../src/EventPublisher.php';
require __DIR__ . '/../src/Auth.php';




use App\DB;
use App\AiSummarizer;
use App\AiClient;
use App\CacheReader;
use App\EventPublisher;
use App\Auth;

$config = require __DIR__ . '/../config/config.php';

// CORS
header('Access-Control-Allow-Origin: ' . $config['cors_origin']);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

// 路由解析：/api/xxx
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/api#', '', $path);
$path = trim($path, '/');
$parts = $path === '' ? [] : explode('/', $path);

function json($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?: [];
}


$mdReader = new MarkdownEventReader();

try {
    // ====== GET /api/sites  →  所有子站 ======
    if ($parts === ['sites'] && $_SERVER['REQUEST_METHOD'] === 'GET') {
        json(DB::all('SELECT * FROM sites ORDER BY id'));
    }

    // ====== GET /api/sites/{slug}  →  子站详情 + 栏目 ======
    if (count($parts) === 2 && $parts[0] === 'sites' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $site = DB::one('SELECT * FROM sites WHERE slug = ?', [$parts[1]]);
        if (!$site) json(['error' => 'site not found'], 404);
        $site['categories'] = DB::all('SELECT * FROM categories WHERE site_id = ? ORDER BY id', [$site['id']]);
        json($site);
    }

    // ====== GET /api/events  →  事件列表（Markdown 数据源） ======
    // 参数：site=game&status=fermenting&type=news&q=关键词&page=1
    if ($parts === ['events'] && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $filters = [];
        if (!empty($_GET['site'])) {
            // 通过 slug 反查 site_id
            $site = DB::one('SELECT id FROM sites WHERE slug = ?', [$_GET['site']]);
            if ($site) $filters['site_id'] = (int)$site['id'];
        }
        if (!empty($_GET['status']))  $filters['status']  = $_GET['status'];
        if (!empty($_GET['type']))    $filters['type']    = $_GET['type'];
        if (!empty($_GET['q']))       $filters['q']       = $_GET['q'];
        if (!empty($_GET['page']))    $filters['page']    = (int)$_GET['page'];

        $result = $mdReader->getEvents($filters);
        json($result['items']);  // 前端只消费 items 数组
    }

    // ====== GET /api/events/{slug}  →  事件详情 ======
    if (count($parts) === 2 && $parts[0] === 'events' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $e = $mdReader->getEventBySlug($parts[1]);
        if (!$e) json(['error' => 'event not found'], 404);
        json($e);
    }

    // ====== POST /api/feeds  →  Step1 采集落地 ======
    if ($parts === ['feeds'] && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $b = body();
        $id = DB::exec('INSERT INTO raw_feeds (source, raw_title, raw_text) VALUES (?,?,?)',
            [$b['source'] ?? '', $b['title'] ?? '', $b['text'] ?? '']);
        json(['id' => $id, 'ok' => true]);
    }

    // ====== GET /api/ai/health  →  AI 健康检查 ======
    // 检查 API Key 是否配置、网络是否连通、模型是否可用
    if ($parts === ['ai', 'health'] && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $client = new AiClient();
        json($client->healthCheck());
    }

    // ====== GET /api/ai/test  →  AI 完整测试 ======
    // 发送一个简单 prompt，验证模型能否正常回复（消耗少量 token）
    // 参数: ?prompt=你好（可选）
    if ($parts === ['ai', 'test'] && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $client = new AiClient();
        $prompt = $_GET['prompt'] ?? '你好，请简短回复';
        json($client->testChat($prompt));
    }

    // ====== POST /api/generate  →  Step2+3 AI 摘要生成 + 发布 ======
    if ($parts === ['generate'] && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $b = body();
        $siteSlug = $b['site'] ?? 'game';
        $site = DB::one('SELECT * FROM sites WHERE slug = ?', [$siteSlug]);
        if (!$site) json(['error' => 'unknown site'], 400);

        if (!empty($b['feed_id'])) {
            $feed = DB::one('SELECT * FROM raw_feeds WHERE id = ?', [$b['feed_id']]);
        } else {
            $feed = DB::one('SELECT * FROM raw_feeds WHERE processed = 0 ORDER BY id LIMIT 1');
        }
        if (!$feed) json(['error' => 'no feed to process'], 404);

        $ai = AiSummarizer::summarize($feed['raw_title'], $feed['raw_text']);
        $slug = 'evt-' . substr(md5($feed['raw_title'] . microtime()), 0, 10);

        $eventId = DB::exec(
            'INSERT INTO events (site_id, slug, title, summary, body, content_type, status)
             VALUES (?,?,?,?,?,?,?)',
            [$site['id'], $slug, $ai['title'], $ai['summary'], $ai['body'], 'analysis', 'fermenting']
        );
        foreach ($ai['timeline'] as $i => $t) {
            DB::exec('INSERT INTO timelines (event_id, happened_at, title, detail, sort_order)
                      VALUES (?,?,?,?,?)', [$eventId, $t['happened_at'], $t['title'], $t['detail'], $i]);
        }
        foreach ($ai['opinions'] as $o) {
            DB::exec('INSERT INTO opinions (event_id, source, side, content)
                      VALUES (?,?,?,?)', [$eventId, $o['source'], $o['side'], $o['content']]);
        }
        DB::exec('UPDATE raw_feeds SET processed = 1 WHERE id = ?', [$feed['id']]);
        json(['event_id' => $eventId, 'slug' => $slug, 'ok' => true]);
    }

    // ==================== 认证 API（公开，无需登录） ====================

    // ====== GET /api/auth/captcha  →  获取验证码 ======
    if (count($parts) === 2 && $parts[0] === 'auth' && $parts[1] === 'captcha'
        && $_SERVER['REQUEST_METHOD'] === 'GET') {

        $auth = new Auth();
        // 检查是否被锁定
        $ip = $auth->getClientIp();
        $lockStatus = $auth->getLockStatus($ip);
        if ($lockStatus['locked']) {
            json(['locked' => true, 'remaining' => $lockStatus['remaining']], 429);
        }
        json($auth->generateCaptchaSession());
    }

    // ====== POST /api/auth/login  →  登录 ======
    if (count($parts) === 2 && $parts[0] === 'auth' && $parts[1] === 'login'
        && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = new Auth();
        $ip = $auth->getClientIp();

        // 检查锁定
        $lockStatus = $auth->getLockStatus($ip);
        if ($lockStatus['locked']) {
            json(['locked' => true, 'remaining' => $lockStatus['remaining'], 'message' => 'IP 已被锁定，请 15 分钟后再试'], 429);
        }

        $b = body();
        $username = $b['username'] ?? '';
        $password = $b['password'] ?? '';
        $captchaAnswer = (int)($b['captcha_answer'] ?? 0);
        $captchaToken = $b['captcha_token'] ?? '';
        $captchaSig = $b['captcha_sig'] ?? '';

        // 验证验证码
        if (!$auth->verifyCaptchaToken($captchaToken, $captchaSig, $captchaAnswer)) {
            $auth->recordFailure($ip);
            json(['ok' => false, 'error' => '验证码错误'], 401);
        }

        // 验证凭据
        if (!$auth->checkCredentials($username, $password)) {
            $auth->recordFailure($ip);
            json(['ok' => false, 'error' => '用户名或密码错误'], 401);
        }

        // 登录成功
        $auth->clearFailures($ip);
        $token = $auth->createSession();

        // 设置 HTTP-Only Cookie
        setcookie('admin_token', $token, [
            'expires'  => time() + 86400,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        json(['ok' => true, 'token' => $token]);
    }

    // ====== POST /api/auth/logout  →  登出 ======
    if (count($parts) === 2 && $parts[0] === 'auth' && $parts[1] === 'logout'
        && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = new Auth();
        $token = $_COOKIE['admin_token'] ?? '';
        if ($token) {
            $auth->destroySession($token);
        }
        setcookie('admin_token', '', time() - 3600, '/', '', false, true);
        json(['ok' => true]);
    }

    // ====== GET /api/auth/status  →  检查登录状态 ======
    if (count($parts) === 2 && $parts[0] === 'auth' && $parts[1] === 'status'
        && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $auth = new Auth();
        $token = $_COOKIE['admin_token'] ?? '';
        if ($token && $auth->verifySession($token)) {
            json(['logged_in' => true]);
        }
        json(['logged_in' => false]);
    }

    // ==================== 内容管理后台 API（需要认证） ====================

    // 认证中间件：所有 /api/admin/* 路由需要登录
    $auth = new Auth();
    $adminToken = $_COOKIE['admin_token'] ?? '';
    if (count($parts) >= 2 && $parts[0] === 'admin') {
        if (!$adminToken || !$auth->verifySession($adminToken)) {
            json(['error' => 'unauthorized'], 401);
        }
    }

    // ====== GET /api/admin/cache-sites  →  有缓存的站点列表 ======
    if ($parts === ['admin', 'cache-sites'] && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $reader = new CacheReader();
        json($reader->getCacheSites());
    }

    // ====== GET /api/admin/cache  →  热榜列表 ======
    // 参数: site=知乎&date=2026-05-30&q=关键词&page=1
    // site 必传，date 可选：空 = 所有日期（倒序），传 = 单日期
    if (count($parts) === 2 && $parts[0] === 'admin' && $parts[1] === 'cache'
        && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $site = $_GET['site'] ?? '';
        $date = $_GET['date'] ?? '';  // 空 = 所有日期
        $q = $_GET['q'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));

        if (empty($site)) {
            json(['error' => 'site parameter required'], 400);
        }

        $reader = new CacheReader();
        json($reader->getCacheItems($site, $date, $q, $page));
    }

    // ====== GET /api/admin/cache-by-date  →  日历视图热榜列表 ======
    // 参数: date=2026-05-30&site=知乎(可选)&q=关键词&page=1
    // date 必传，site 可选：不传 = 所有站点，传 = 单站点
    if (count($parts) === 2 && $parts[0] === 'admin' && $parts[1] === 'cache-by-date'
        && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $date = $_GET['date'] ?? '';
        $site = $_GET['site'] ?? '';  // 可选，空 = 所有站点
        $q = $_GET['q'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));

        if (empty($date)) {
            json(['error' => 'date parameter required'], 400);
        }

        $reader = new CacheReader();
        json($reader->getCacheItemsByDate($date, $site, $q, $page));
    }

    // ====== GET /api/admin/cache-date-categories  →  获取指定日期各站点缓存数量 ======
    if (count($parts) === 2 && $parts[0] === 'admin' && $parts[1] === 'cache-date-categories'
        && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $date = $_GET['date'] ?? '';
        if (empty($date)) {
            json(['error' => 'date parameter required'], 400);
        }
        $reader = new CacheReader();
        json($reader->getDateCategoryCounts($date));
    }

    // ====== GET /api/admin/cache-dates  →  获取有缓存的所有日期 ======
    if (count($parts) === 2 && $parts[0] === 'admin' && $parts[1] === 'cache-dates'
        && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $reader = new CacheReader();
        $sites = $reader->getCacheSites();
        $allDates = [];
        foreach ($sites as $s) {
            foreach ($s['dates'] as $d) {
                if (!isset($allDates[$d])) {
                    $allDates[$d] = 0;
                }
                $allDates[$d] += $s['total'];
            }
        }
        // 按日期倒序
        krsort($allDates);
        $result = [];
        foreach ($allDates as $d => $count) {
            $result[] = ['date' => $d, 'total' => $count];
        }
        json($result);
    }

    // ====== POST /api/admin/generate  →  AI 生成文章（预览，不写文件） ======
    if (count($parts) === 2 && $parts[0] === 'admin' && $parts[1] === 'generate'
        && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $b = body();
        $items = $b['items'] ?? [];

        if (empty($items)) {
            json(['error' => 'items required'], 400);
        }

        // 构建 prompt
        $itemLines = [];
        foreach ($items as $i => $item) {
            $itemLines[] = sprintf("%d. [%s] %s (热度: %s)",
                $i + 1,
                $item['sitename'] ?? '',
                $item['title'] ?? '',
                $item['views'] ?? $item['extra'] ?? ''
            );
        }
        $itemsText = implode("\n", $itemLines);
        $siteSlug = $b['site_slug'] ?? 'tech';
        $customPrompt = $b['custom_prompt'] ?? '';

        $siteNames = [
            'game' => '游戏呀呀', 'ai' => 'AI呀呀',
            'tech' => '互联网呀呀', 'star' => '主播呀呀',
        ];
        $siteName = $siteNames[$siteSlug] ?? '未知';

        $extraPrompt = $customPrompt ? "## 额外要求\n{$customPrompt}\n\n" : '';

        $prompt = <<<PROMPT
你是一个专业的新闻编辑。请根据以下热榜标题，撰写一篇完整的新闻事件文章。

## 目标子站: {$siteName} ({$siteSlug})
{$extraPrompt}
## 热榜来源（用户勾选的条目）:
{$itemsText}

## 要求
1. 标题简洁有力，适合搜索引擎，不超过 50 字
2. 摘要 100 字以内，概括事件核心
3. 正文按「## 事件背景 → ## 详细经过 → ## 争议焦点 → ## 各方回应 → ## 后续影响」结构，每个章节标题统一使用二级标题（##），不要用三级标题（###）
4. 时间线按时间顺序排列，每条包含 happened_at、title、detail
5. 观点至少 3 条，覆盖官方(official)、媒体(media)、网友(player)
6. 保持中立客观，不加入个人判断
7. 中文输出

## 输出格式（必须输出纯 JSON，不要其他文字）
{
  "title": "文章标题",
  "summary": "摘要",
  "body": "Markdown 正文",
  "timeline": [
    {"happened_at": "YYYY-MM-DD HH:mm", "title": "节点标题", "detail": "详情"}
  ],
  "opinions": [
    {"side": "official|media|player", "source": "来源", "content": "内容"}
  ],
  "persons": [
    {"name": "相关人物"}
  ]
}
PROMPT;

        $systemPrompt = '你是一个专业的新闻编辑，擅长从碎片化信息中提取关键事实，撰写结构清晰、中立客观的新闻文章。输出必须是纯 JSON。';

        $client = new AiClient();
        $result = $client->chat($prompt, 6000, $systemPrompt, 90);

        if (!($result['ok'] ?? false)) {
            json(['ok' => false, 'error' => $result['error'] ?? 'AI 调用失败'], 500);
        }

        // 解析 JSON 输出
        $content = $result['reply'] ?? '';
        $content = preg_replace('/^```(?:json)?\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        if (preg_match('/\{[\s\S]*\}/', $content, $match)) {
            $parsed = json_decode($match[0], true);
        } else {
            $parsed = json_decode($content, true);
        }

        if (!$parsed) {
            json(['ok' => false, 'error' => 'AI 输出 JSON 解析失败', 'raw' => substr($content, 0, 500)], 500);
        }

        json([
            'ok'         => true,
            'title'      => $parsed['title'] ?? '',
            'summary'    => $parsed['summary'] ?? '',
            'body'       => $parsed['body'] ?? '',
            'timeline'   => is_array($parsed['timeline'] ?? null) ? $parsed['timeline'] : [],
            'opinions'   => is_array($parsed['opinions'] ?? null) ? $parsed['opinions'] : [],
            'persons'    => is_array($parsed['persons'] ?? null) ? $parsed['persons'] : [],
            'latency_ms' => $result['latency_ms'] ?? 0,
            'usage'      => $result['usage'] ?? null,
        ]);
    }

    // ====== POST /api/admin/publish  →  确认发布（写入 .md 文件） ======
    if (count($parts) === 2 && $parts[0] === 'admin' && $parts[1] === 'publish'
        && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $b = body();

        $publisher = new EventPublisher();
        $result = $publisher->publish($b, $b['site_slug'] ?? 'tech', $b['source_md5s'] ?? []);
        json($result);
    }

    // ====== GET /api/admin/stats  →  缓存统计 ======
    if ($parts === ['admin', 'stats'] && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $reader = new CacheReader();

        // 事件统计
        $eventsDir = __DIR__ . '/../data/events';
        $eventCount = 0;
        if (is_dir($eventsDir)) {
            $eventCount = count(glob($eventsDir . '/*.md'));
        }

        json([
            'cache'  => $reader->getStats(),
            'events' => [
                'total' => $eventCount,
            ],
        ]);
    }

    json(['error' => 'not found', 'path' => $path], 404);

} catch (\Throwable $ex) {
    json(['error' => $ex->getMessage()], 500);
}

