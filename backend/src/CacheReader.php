<?php
declare(strict_types=1);
namespace App;

/**
 * 缓存读取器 — 读取 backend/data/cache/tophub/ 下的热榜缓存
 *
 * 目录结构:
 *   backend/data/cache/tophub/
 *   ├── 知乎/
 *   │   ├── 2026-05-30/
 *   │   │   ├── {md5}.json
 *   │   │   └── ...
 *   │   └── 2026-05-29/
 *   ├── 微博/
 *   │   └── 2026-05-30/
 *   └── ...
 */
class CacheReader
{
    private string $cacheRoot;

    public function __construct(?string $cacheRoot = null)
    {
        $this->cacheRoot = $cacheRoot ?? __DIR__ . '/../data/cache/tophub';
    }

    /**
     * 获取有缓存的站点列表
     */
    public function getCacheSites(): array
    {
        if (!is_dir($this->cacheRoot)) return [];

        $sites = [];
        foreach (scandir($this->cacheRoot) as $siteDir) {
            if ($siteDir === '.' || $siteDir === '..') continue;
            $sitePath = $this->cacheRoot . '/' . $siteDir;
            if (!is_dir($sitePath)) continue;

            $dates = [];
            $total = 0;
            foreach (scandir($sitePath) as $dateDir) {
                if ($dateDir === '.' || $dateDir === '..') continue;
                $datePath = $sitePath . '/' . $dateDir;
                if (!is_dir($datePath)) continue;

                $files = glob($datePath . '/*.json');
                $count = count($files);
                if ($count > 0) {
                    $dates[] = $dateDir;
                    $total += $count;
                }
            }

            if (!empty($dates)) {
                rsort($dates); // 日期倒序
                $sites[] = [
                    'site'  => $siteDir,
                    'dates' => $dates,
                    'total' => $total,
                ];
            }
        }

        // 按总数倒序
        usort($sites, fn($a, $b) => $b['total'] - $a['total']);
        return $sites;
    }

    /**
     * 获取指定站点的热榜（支持按日期筛选或全部日期）
     *
     * @param string $site  站点名（如 "知乎"）
     * @param string $date  日期（可选，空 = 所有日期）
     * @param string $q     搜索关键词（可选）
     * @param int    $page  页码
     * @param int    $pageSize 每页条数
     */
    public function getCacheItems(string $site, string $date = '', string $q = '', int $page = 1, int $pageSize = 50): array
    {
        $safeSite = preg_replace('/[\\\\\/\?%*:|"<>]/', '_', $site);
        $sitePath = $this->cacheRoot . '/' . $safeSite;
        if (!is_dir($sitePath)) {
            return ['items' => [], 'total' => 0];
        }

        $allItems = [];

        if ($date === '') {
            // 所有日期：遍历该站点下所有日期目录
            foreach (scandir($sitePath) as $dateDir) {
                if ($dateDir === '.' || $dateDir === '..') continue;
                $datePath = $sitePath . '/' . $dateDir;
                if (!is_dir($datePath)) continue;

                foreach (glob($datePath . '/*.json') as $file) {
                    $item = $this->loadItem($file, $q);
                    if ($item) $allItems[] = $item;
                }
            }

            // 按日期 + 热度排序（日期倒序，同日期按热度）
            usort($allItems, function($a, $b) {
                $dateCompare = strcmp($b['fetched_at'] ?? '', $a['fetched_at'] ?? '');
                if ($dateCompare !== 0) return $dateCompare;
                $aHeat = $this->parseHeat($a['extra'] ?? '');
                $bHeat = $this->parseHeat($b['extra'] ?? '');
                return $bHeat <=> $aHeat;
            });
        } else {
            // 单日期
            $safeDate = preg_replace('/[^\d\-]/', '', $date);
            $dirPath = $sitePath . '/' . $safeDate;
            if (!is_dir($dirPath)) {
                return ['items' => [], 'total' => 0];
            }

            foreach (glob($dirPath . '/*.json') as $file) {
                $item = $this->loadItem($file, $q);
                if ($item) $allItems[] = $item;
            }

            // 按热度排序
            usort($allItems, function($a, $b) {
                $aHeat = $this->parseHeat($a['extra'] ?? '');
                $bHeat = $this->parseHeat($b['extra'] ?? '');
                return $bHeat <=> $aHeat;
            });
        }

        $total = count($allItems);
        $offset = ($page - 1) * $pageSize;
        $paged = array_slice($allItems, $offset, $pageSize);

        return [
            'items' => $paged,
            'total' => $total,
            'site'  => $site,
            'date'  => $date,
            'page'  => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 加载单个 JSON 文件并应用搜索过滤
     */
    private function loadItem(string $file, string $q): ?array
    {
        $content = file_get_contents($file);
        if ($content === false) return null;

        $data = json_decode($content, true);
        if (!$data) return null;

        if ($q !== '') {
            $haystack = ($data['title'] ?? '') . ' ' . ($data['extra'] ?? '') . ' ' . ($data['sitename'] ?? '');
            if (mb_stripos($haystack, $q) === false) {
                return null;
            }
        }

        return $data;
    }

    /**
     * 获取指定日期各站点的缓存数量
     *
     * @param string $date 日期（如 "2026-05-30"）
     */
    public function getDateCategoryCounts(string $date): array
    {
        $safeDate = preg_replace('/[^\d\-]/', '', $date);
        $result = [];
        $totalAll = 0;

        foreach (scandir($this->cacheRoot) as $siteDir) {
            if ($siteDir === '.' || $siteDir === '..') continue;

            $datePath = $this->cacheRoot . '/' . $siteDir . '/' . $safeDate;
            if (!is_dir($datePath)) continue;

            $files = glob($datePath . '/*.json');
            $count = count($files);
            if ($count > 0) {
                $result[] = [
                    'site'  => $siteDir,
                    'count' => $count,
                ];
                $totalAll += $count;
            }
        }

        // 按数量倒序
        usort($result, fn($a, $b) => $b['count'] - $a['count']);

        // 在前面插入 "全部" 项
        array_unshift($result, [
            'site'  => '',
            'count' => $totalAll,
        ]);

        return $result;
    }

    /**
     * 获取指定日期下所有站点的热榜（日历视图）
     *
     * @param string $date    日期（如 "2026-05-30"）
     * @param string $filterSite 过滤站点（可选）
     * @param string $q       搜索关键词（可选）
     * @param int    $page    页码
     * @param int    $pageSize 每页条数
     */
    public function getCacheItemsByDate(string $date, string $filterSite = '', string $q = '', int $page = 1, int $pageSize = 50): array
    {
        $safeDate = preg_replace('/[^\d\-]/', '', $date);
        $allItems = [];

        foreach (scandir($this->cacheRoot) as $siteDir) {
            if ($siteDir === '.' || $siteDir === '..') continue;

            // 如果指定了站点过滤，跳过不匹配的
            if ($filterSite !== '' && $siteDir !== $filterSite) continue;

            $datePath = $this->cacheRoot . '/' . $siteDir . '/' . $safeDate;
            if (!is_dir($datePath)) continue;

            foreach (glob($datePath . '/*.json') as $file) {
                $content = file_get_contents($file);
                if ($content === false) continue;

                $data = json_decode($content, true);
                if (!$data) continue;

                // 搜索过滤
                if ($q !== '') {
                    $haystack = ($data['title'] ?? '') . ' ' . ($data['extra'] ?? '') . ' ' . ($data['sitename'] ?? '');
                    if (mb_stripos($haystack, $q) === false) {
                        continue;
                    }
                }

                $allItems[] = $data;
            }
        }

        // 按热度排序
        usort($allItems, function($a, $b) {
            $aHeat = $this->parseHeat($a['extra'] ?? '');
            $bHeat = $this->parseHeat($b['extra'] ?? '');
            return $bHeat <=> $aHeat;
        });

        $total = count($allItems);
        $offset = ($page - 1) * $pageSize;
        $paged = array_slice($allItems, $offset, $pageSize);

        return [
            'items' => $paged,
            'total' => $total,
            'date'  => $date,
            'page'  => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 缓存统计
     */
    public function getStats(): array
    {
        $sites = $this->getCacheSites();
        $totalItems = 0;
        $totalDisk = 0;

        foreach ($sites as $s) {
            $totalItems += $s['total'];
        }

        // 磁盘占用
        if (is_dir($this->cacheRoot)) {
            $output = [];
            @\exec("du -sk " . escapeshellarg($this->cacheRoot) . " 2>/dev/null", $output);
            if (!empty($output[0])) {
                $totalDisk = (int)explode("\t", $output[0])[0]; // KB
            }
        }

        return [
            'sites'       => count($sites),
            'total_items' => $totalItems,
            'disk_size'   => $totalDisk > 1024 ? round($totalDisk / 1024, 1) . 'MB' : $totalDisk . 'KB',
            'by_site'     => $sites,
        ];
    }

    /**
     * 解析热度数字（"2632 万热度" → 26320000）
     */
    private function parseHeat(string $extra): int
    {
        if (preg_match('/([\d.]+)\s*万/', $extra, $m)) {
            return (int)((float)$m[1] * 10000);
        }
        if (preg_match('/([\d.]+)\s*评/', $extra, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/([\d.]+)\s*亮/', $extra, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/([\d.]+)/', $extra, $m)) {
            return (int)$m[1];
        }
        return 0;
    }
}
