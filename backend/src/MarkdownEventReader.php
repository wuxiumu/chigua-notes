<?php
declare(strict_types=1);

/**
 * Markdown 事件数据读取器
 *
 * 从 backend/data/events/*.md 文件中读取事件数据。
 * 文件格式：YAML frontmatter（--- 分隔） + Markdown body
 *
 * 示例文件 001-xhs-algorithm-leak.md：
 * ---
 * id: 1
 * slug: xhs-algorithm-leak
 * site_id: 3
 * title: 标题
 * summary: 摘要
 * ...
 * ---
 *
 * 正文内容...
 */
class MarkdownEventReader
{
    private string $eventsDir;

    public function __construct(?string $eventsDir = null)
    {
        $this->eventsDir = $eventsDir ?? __DIR__ . '/../data/events';
    }

    /**
     * 获取所有事件列表（可过滤、分页）
     */
    public function getEvents(array $filters = []): array
    {
        $events = [];
        $files = glob($this->eventsDir . '/*.md');

        foreach ($files as $file) {
            $data = $this->parseFile($file);
            if (!$data) continue;

            // 按 site_id 过滤
            if (!empty($filters['site'])) {
                // 需要通过 slug 反查 site_id，这里简化处理
                // 调用方应传入 site_id
            }
            if (!empty($filters['site_id']) && $data['site_id'] != $filters['site_id']) {
                continue;
            }

            // 按状态过滤
            if (!empty($filters['status']) && $data['status'] !== $filters['status']) {
                continue;
            }

            // 按类型过滤
            if (!empty($filters['type']) && $data['content_type'] !== $filters['type']) {
                continue;
            }

            // 关键词搜索
            if (!empty($filters['q'])) {
                $q = mb_strtolower($filters['q']);
                $haystack = mb_strtolower($data['title'] . ' ' . $data['summary'] . ' ' . $data['site_name']);
                if (strpos($haystack, $q) === false) {
                    continue;
                }
            }

            // 列表接口不返回 body（减小传输量）
            unset($data['body']);
            $events[] = $data;
        }

        // 按 first_seen 倒序
        usort($events, fn($a, $b) => strcmp($b['first_seen'], $a['first_seen']));

        // 分页
        $page = max(1, (int)($filters['page'] ?? 1));
        $pageSize = (int)($filters['page_size'] ?? 10);
        $total = count($events);

        return [
            'items' => array_slice($events, ($page - 1) * $pageSize, $pageSize),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 根据 slug 获取单个事件详情（包含 body）
     */
    public function getEventBySlug(string $slug): ?array
    {
        $files = glob($this->eventsDir . '/*.md');

        foreach ($files as $file) {
            $data = $this->parseFile($file);
            if ($data && (string)$data['slug'] === $slug) {
                return $data;
            }
        }

        return null;
    }

    /**
     * 解析单个 Markdown 文件
     */
    private function parseFile(string $filePath): ?array
    {
        $content = file_get_contents($filePath);
        if ($content === false) return null;

        // 提取 frontmatter（--- 之间的 YAML 内容）
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            return null;
        }

        $frontmatter = $this->parseYaml($matches[1]);
        $body = trim($matches[2]);

        if (!$frontmatter) return null;

        $frontmatter['body'] = $body;
        return $frontmatter;
    }

    /**
     * 简易 YAML 解析器
     * 支持：标量值、简单数组、嵌套数组（深度 2）
     * 不需要完整的 YAML 解析，因为我们的 frontmatter 结构固定
     */
    private function parseYaml(string $yaml): array
    {
        $result = [];
        $lines = explode("\n", $yaml);
        $currentKey = null;
        $currentList = null;
        $currentListKey = null;
        $currentListItem = null;
        $currentListItemKey = null;

        foreach ($lines as $line) {
            // 跳过空行和注释
            if (trim($line) === '' || trim($line)[0] === '#') {
                continue;
            }

            // 检测是否是列表项的子键（4 空格缩进 + 键: 值）
            if (preg_match('/^    (\w+):\s*(.*)$/', $line, $m)) {
                if ($currentListKey && $currentListItem !== null) {
                    $key = $m[1];
                    $val = $this->parseValue($m[2]);
                    if ($currentListItemKey !== null) {
                        // 嵌套对象中的键
                        $currentListItem[$currentListItemKey][$key] = $val;
                    } else {
                        $currentListItem[$key] = $val;
                    }
                    // 更新数组中的引用
                    $result[$currentListKey][count($result[$currentListKey]) - 1] = $currentListItem;
                }
                continue;
            }

            // 检测是否是列表项（2 空格缩进 + - ）
            if (preg_match('/^  - (.*)$/', $line, $m)) {
                // 如果之前的列表项有子键，先保存
                if ($currentListKey && $currentListItemKey !== null && $currentListItem !== null) {
                    $result[$currentListKey][count($result[$currentListKey]) - 1] = $currentListItem;
                }

                $content = trim($m[1]);
                // 检查是否是 "key: value" 形式的列表项
                if (preg_match('/^(\w+):\s*(.*)$/', $content, $cm)) {
                    $currentListKey = $currentKey;
                    $currentListItem = [$cm[1] => $this->parseValue($cm[2])];
                    $currentListItemKey = null;
                    $result[$currentListKey][] = $currentListItem;
                } else {
                    // 纯标量列表项
                    $currentListKey = null;
                    $currentListItem = null;
                    $currentListItemKey = null;
                    $result[$currentKey][] = $content;
                }
                continue;
            }

            // 顶层键值对
            if (preg_match('/^(\w[\w_]*):\s*(.*)$/', $line, $m)) {
                // 保存之前的复杂类型
                if ($currentListKey && $currentListItemKey !== null && $currentListItem !== null) {
                    $result[$currentListKey][count($result[$currentListKey]) - 1] = $currentListItem;
                }

                $key = $m[1];
                $val = $m[2];

                if ($val === '' || $val === null) {
                    // 这是一个数组或复杂类型的开始
                    $currentKey = $key;
                    $currentListKey = $key;
                    $currentListItem = null;
                    $currentListItemKey = null;
                    $result[$key] = [];
                } else {
                    $currentKey = $key;
                    $currentListKey = null;
                    $currentListItem = null;
                    $currentListItemKey = null;
                    $result[$key] = $this->parseValue($val);
                }
            }
        }

        // 保存最后一个列表项
        if ($currentListKey && $currentListItemKey !== null && $currentListItem !== null) {
            $result[$currentListKey][count($result[$currentListKey]) - 1] = $currentListItem;
        }

        return $result;
    }

    /**
     * 解析 YAML 标量值
     */
    private function parseValue(string $value)
    {
        $value = trim($value);

        // 空值
        if ($value === '' || $value === '~' || $value === 'null') {
            return null;
        }

        // 布尔值
        if ($value === 'true') return true;
        if ($value === 'false') return false;

        // 数字
        if (preg_match('/^-?\d+$/', $value)) return (int)$value;
        if (preg_match('/^-?\d+\.\d+$/', $value)) return (float)$value;

        // 去除引号
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
