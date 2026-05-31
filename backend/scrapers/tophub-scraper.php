<?php
/**
 * TopHub 热榜采集器
 *
 * 获取全网热榜 → 黑名单过滤 → 按站点+日期目录存储 → 去重
 *
 * 缓存目录结构:
 *   backend/data/cache/tophub/
 *   ├── 知乎/
 *   │   ├── 2026-05-30/
 *   │   │   ├── 17061a1a963d51b4.json
 *   │   │   └── 3ea5a2f0dcd3291d.json
 *   │   └── 2026-05-29/
 *   │       └── ...
 *   ├── 微博/
 *   │   └── 2026-05-30/
 *   │       └── ...
 *   └── IT之家/
 *       └── 2026-05-30/
 *           └── ...
 *
 * 用法:
 *   php backend/scrapers/tophub-scraper.php            # 正常采集
 *   php backend/scrapers/tophub-scraper.php --dry-run   # 预览模式（不写文件）
 *   php backend/scrapers/tophub-scraper.php --stats     # 查看缓存统计
 */

require __DIR__ . '/../bootstrap.php';

class TopHubScraper
{
    private const API_URL = 'https://api.meiyoufan.com/tophub/';
    private const CACHE_ROOT = __DIR__ . '/../data/cache/tophub';

    /**
     * 关键词黑名单 — 命中任意一条则跳过，不缓存
     * 用于过滤低质量、重复、无关内容
     */
    private const BLACKLIST = [
        '51吃瓜',
        // ===== 低质量/灌水 =====
//        '为什么年轻人',
//        '为什么现在的人',
//        '如何看待',
//        '为什么有人',
//        '为什么越来越',
//        '是什么原因',
//        '是一种怎样的体验',
//        '你遇到过',
//        '你觉得',
//
//        // ===== 生活琐事 =====
//        '速冻水饺',
//        '为什么电车',
//        '为什么电车普遍',
//        '养路费',
//        '买衣服',
//        '年轻人都不太愿意',
//
//        // ===== 娱乐八卦（低价值） =====
//        '洛阳女海王',
//        '瓜，大家吃了',
//
//        // ===== 广告/营销 =====
//        '拼多多',
//        '淘宝',
//        '京东',
//        '天猫',
//
//        // ===== 重复/过时 =====
//        '超市里卖的速冻水饺',
//        '年轻人都不太愿意在买衣服上花钱',
    ];

    /**
     * sitename → 子站 slug 映射
     * 用于后续 AI 分类时按子站分组
     */
    private const SITE_ROUTING = [
        // 🎮 游戏呀呀
        '虎扑社区'     => 'game',
        '游民星空'     => 'game',
        'NGA'          => 'game',
        '起点中文网'   => 'game',
        '哔哩哔哩'     => 'game',   // 游戏区

        // 🤖 AI呀呀
        'IT之家'       => 'ai',
        '36氪'         => 'ai',
        '少数派'       => 'ai',
        'V2EX'         => 'ai',

        // 💻 互联网呀呀
        'Zaker'        => 'tech',
        '虎嗅网'       => 'tech',
        '水木社区'     => 'tech',
        '第一财经'     => 'tech',
        '新京报网'     => 'tech',
        '百度'         => 'tech',
        '今日头条'     => 'tech',
        '微信'         => 'tech',
        '雪球'         => 'tech',
        '宽带山'       => 'tech',

        // ⭐ 主播呀呀
        '微博'         => 'star',
        '抖音短视频'   => 'star',
        '51吃瓜'       => 'star',
        '百度贴吧'     => 'star',
        '猫眼电影'     => 'star',

        // 需要 AI 判定
        '知乎'         => 'ai',     // 全部子站都可能
        '知乎日报'     => 'tech',
    ];

    private bool $dryRun;
    private string $targetDate;

    public function __construct(bool $dryRun = false, ?string $targetDate = null)
    {
        $this->dryRun = $dryRun;
        $this->targetDate = $targetDate ?: date('Y-m-d');

        if (!is_dir(self::CACHE_ROOT)) {
            mkdir(self::CACHE_ROOT, 0755, true);
        }
    }

    // ==================== 主流程 ====================

    public function run(): void
    {
        echo "🚀 TopHub 热榜采集开始\n";
        echo ($this->dryRun ? "🔍 模式: 仅预览（不写文件）\n" : "📝 模式: 保存到本地\n");
        echo "📅 日期: {$this->targetDate}\n";
        echo str_repeat('─', 50) . "\n";

        // Step 1: 拉取热榜
        $items = $this->fetchHotlist();
        if (empty($items)) {
            echo "❌ 获取热榜失败\n";
            return;
        }
        echo "✅ 获取到 " . count($items) . " 条热榜\n";

        // 统计来源分布
        $this->printDistribution($items);

        // Step 2: 黑名单过滤
        $blocked = [];
        $passed = [];
        foreach ($items as $item) {
            if ($this->isBlocked($item['title'])) {
                $blocked[] = $item;
            } else {
                $passed[] = $item;
            }
        }
        echo "\n🚫 黑名单拦截: " . count($blocked) . " 条\n";
        echo "✅ 通过过滤: " . count($passed) . " 条\n";

        if (!empty($blocked) && !$this->dryRun) {
            echo "\n📋 黑名单拦截示例（前 10 条）:\n";
            foreach (array_slice($blocked, 0, 10) as $i => $item) {
                $reason = $this->getBlacklistReason($item['title']);
                echo sprintf("  %2d. [%s] %s\n      └ 命中关键词: %s\n",
                    $i + 1, $item['sitename'], $item['title'], $reason);
            }
        }

        // Step 3: 按 sitename 分组 + 去重 + 保存
        echo "\n📂 按站点+日期保存...\n";
        $saved = 0;
        $skipped = 0;

        $grouped = $this->groupBySite($passed);
        foreach ($grouped as $sitename => $siteItems) {
            // 安全目录名（替换非法字符）
            $dirName = preg_replace('/[\\\\\/\?%*:|"<>]/', '_', $sitename);
            $dateDir = $this->targetDate;
            $targetDir = self::CACHE_ROOT . '/' . $dirName . '/' . $dateDir;

            foreach ($siteItems as $item) {
                $md5 = $item['md5'] ?? '';
                if (empty($md5)) continue;

                $filePath = $targetDir . '/' . $md5 . '.json';

                if (file_exists($filePath)) {
                    $skipped++;
                    continue;
                }

                if (!$this->dryRun) {
                    // 确保目录存在
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }

                    // 保存为 JSON 文件
                    $fileData = [
                        'ID'        => $item['ID'],
                        'title'     => $item['title'],
                        'url'       => $item['url'],
                        'thumbnail' => $item['thumbnail'] ?? '',
                        'extra'     => $item['extra'] ?? '',
                        'views'     => $item['views'] ?? '',
                        'time'      => $item['time'] ?? '',
                        'nodeids'   => $item['nodeids'] ?? '',
                        'domain'    => $item['domain'] ?? '',
                        'sitename'  => $item['sitename'],
                        'logo'      => $item['logo'] ?? '',
                        'md5'       => $md5,
                        'fetched_at' => date('Y-m-d H:i:s'),
                    ];
                    file_put_contents(
                        $filePath,
                        json_encode($fileData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    );
                }

                $saved++;
            }

            // 打印每个站点的统计
            echo sprintf("  %-12s  今日 %s → 新增 %d，已存在 %d\n",
                $sitename,
                count($siteItems),
                $this->dryRun ? 0 : $this->countNewInGroup($siteItems, $dirName, $dateDir),
                count($siteItems) - ($this->dryRun ? 0 : $this->countNewInGroup($siteItems, $dirName, $dateDir))
            );
        }

        if ($this->dryRun) {
            echo "\n🔍 预览模式，未写入文件\n";
        } else {
            echo "\n💾 保存目录: " . self::CACHE_ROOT . '/' . $this->targetDate . "/\n";
        }

        echo "\n🎉 TopHub 采集完成\n";
        echo "   总计: 获取" . count($items)
           . " | 拦截" . count($blocked)
           . " | 新增" . $saved
           . " | 已存在" . $skipped . "\n";
    }

    // ==================== Step 1: 拉取 ====================

    private function fetchHotlist(): array
    {
        $url = self::API_URL . '?date=' . $this->targetDate;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; 51chigua/1.0)',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("[TopHub] Curl 错误: {$error}");
            return [];
        }

        if ($httpCode !== 200) {
            error_log("[TopHub] 请求失败: HTTP {$httpCode}");
            return [];
        }

        $data = json_decode($response, true);
        return $data['data']['items'] ?? [];
    }

    // ==================== 黑名单 ====================

    private function isBlocked(string $title): bool
    {
        foreach (self::BLACKLIST as $keyword) {
            if (mb_strpos($title, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function getBlacklistReason(string $title): ?string
    {
        foreach (self::BLACKLIST as $keyword) {
            if (mb_strpos($title, $keyword) !== false) {
                return $keyword;
            }
        }
        return null;
    }

    // ==================== 分组 ====================

    private function groupBySite(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $sitename = $item['sitename'] ?? '未知';
            if (!isset($groups[$sitename])) {
                $groups[$sitename] = [];
            }
            $groups[$sitename][] = $item;
        }
        return $groups;
    }

    // ==================== 统计 ====================

    private function printDistribution(array $items): void
    {
        $domains = [];
        foreach ($items as $item) {
            $domains[$item['sitename']] = ($domains[$item['sitename']] ?? 0) + 1;
        }
        arsort($domains);

        echo "📊 来源分布:\n";
        foreach ($domains as $site => $count) {
            $slug = self::SITE_ROUTING[$site] ?? '?';
            $bar = str_repeat('█', min($count, 50));
            echo sprintf("  %-12s %3d %s\n", $site, $count, $bar);
        }
    }

    private function countNewInGroup(array $items, string $dirName, string $dateDir): int
    {
        $targetDir = self::CACHE_ROOT . '/' . $dirName . '/' . $dateDir;
        $count = 0;
        foreach ($items as $item) {
            $md5 = $item['md5'] ?? '';
            if (empty($md5)) continue;
            if (!file_exists($targetDir . '/' . $md5 . '.json')) {
                $count++;
            }
        }
        return $count;
    }

    // ==================== 统计命令 ====================

    public static function showStats(): void
    {
        $root = self::CACHE_ROOT;
        if (!is_dir($root)) {
            echo "📂 缓存目录不存在: {$root}\n";
            return;
        }

        echo "📊 TopHub 缓存统计\n";
        echo str_repeat('─', 50) . "\n";

        $totalFiles = 0;
        $totalSize = 0;
        $sites = [];

        foreach (scandir($root) as $siteDir) {
            if ($siteDir === '.' || $siteDir === '..') continue;
            $sitePath = $root . '/' . $siteDir;
            if (!is_dir($sitePath)) continue;

            $siteCount = 0;
            foreach (scandir($sitePath) as $dateDir) {
                if ($dateDir === '.' || $dateDir === '..') continue;
                $datePath = $sitePath . '/' . $dateDir;
                if (!is_dir($datePath)) continue;

                $files = glob($datePath . '/*.json');
                $count = count($files);
                $siteCount += $count;

                echo sprintf("  %-15s %-12s %4d 条\n", $siteDir, $dateDir, $count);
            }

            $totalFiles += $siteCount;
            $sites[$siteDir] = $siteCount;
        }

        echo str_repeat('─', 50) . "\n";
        echo "总计: {$totalFiles} 条，分布在 " . count($sites) . " 个站点\n";

        // 磁盘大小
        $output = [];
        exec("du -sh {$root} 2>/dev/null", $output);
        if (!empty($output[0])) {
            echo "磁盘占用: " . explode("\t", $output[0])[0] . "\n";
        }
    }
}

// ==================== 入口 ====================

if (in_array('--stats', $argv)) {
    TopHubScraper::showStats();
} else {
    $dryRun = in_array('--dry-run', $argv);

    // 解析 --date=YYYY-MM-DD 参数
    $targetDate = null;
    foreach ($argv as $arg) {
        if (preg_match('/^--date=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
            $targetDate = $m[1];
            break;
        }
    }

    (new TopHubScraper($dryRun, $targetDate))->run();
}
