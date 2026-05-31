<?php
declare(strict_types=1);
namespace App;

/**
 * 事件发布器 — 将 AI 生成的内容发布为 Markdown 事件文件
 */
class EventPublisher
{
    private string $eventsDir;

    private const SITE_MAP = [
        'game' => ['id' => 1, 'name' => '游戏呀呀'],
        'ai'   => ['id' => 2, 'name' => 'AI呀呀'],
        'tech' => ['id' => 3, 'name' => '互联网呀呀'],
        'star' => ['id' => 4, 'name' => '主播呀呀'],
    ];

    public function __construct(?string $eventsDir = null)
    {
        $this->eventsDir = $eventsDir ?? __DIR__ . '/../data/events';
        if (!is_dir($this->eventsDir)) {
            mkdir($this->eventsDir, 0755, true);
        }
    }

    /**
     * 发布事件
     *
     * @param array  $content   AI 生成的内容 {title, summary, body, timeline, opinions, persons}
     * @param string $siteSlug  目标子站 slug
     * @param array  $sourceMd5s 来源热榜的 md5 列表（可选，用于关联）
     * @return array {ok: bool, file: string, slug: string}
     */
    public function publish(array $content, string $siteSlug = 'tech', array $sourceMd5s = []): array
    {
        $title = $content['title'] ?? '未命名事件';
        $summary = $content['summary'] ?? '';
        $body = $content['body'] ?? '';
        $timeline = is_array($content['timeline'] ?? null) ? $content['timeline'] : [];
        $opinions = is_array($content['opinions'] ?? null) ? $content['opinions'] : [];
        $persons = is_array($content['persons'] ?? null) ? $content['persons'] : [];

        $site = self::SITE_MAP[$siteSlug] ?? self::SITE_MAP['tech'];
        $now = date('Y-m-d H:i');

        // 生成序号和 slug
        $existing = glob($this->eventsDir . '/*.md');
        $nextNum = count($existing) + 1;
        $numStr = str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);

        $slug = $this->generateSlug($title);

        // 构建 Markdown 内容
        $md = $this->buildMarkdown(
            $nextNum, $slug, $site, $title, $summary, $body,
            $timeline, $opinions, $persons, $now
        );

        // 写入文件
        $filePath = $this->eventsDir . "/{$numStr}-{$slug}.md";
        file_put_contents($filePath, $md);

        return [
            'ok'   => true,
            'file' => "{$numStr}-{$slug}.md",
            'slug' => $slug,
            'id'   => $nextNum,
        ];
    }

    /**
     * 生成 URL 安全的 slug
     */
    private function generateSlug(string $title): string
    {
        // 尝试保留中文拼音或直接使用英文
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(
            preg_replace('/[\s]+/', '-', preg_replace('/[^\w\s]/', '', $title))
        ));

        if (empty($slug)) {
            // 中文标题用 md5 前缀
            $slug = 'event-' . substr(md5($title . microtime()), 0, 10);
        }

        // 确保唯一性
        $filePath = $this->eventsDir . "/000-{$slug}.md";
        $existing = glob($this->eventsDir . "/*-{$slug}.md");
        if (!empty($existing)) {
            $slug .= '-' . substr(md5(microtime()), 0, 4);
        }

        return $slug;
    }

    /**
     * 构建 Markdown 文件内容
     */
    private function buildMarkdown(
        int $id, string $slug, array $site, string $title, string $summary,
        string $body, array $timeline, array $opinions, array $persons, string $now
    ): string {
        $yamlTimeline = $this->arrayToYaml($timeline);
        $yamlOpinions = $this->arrayToYaml($opinions);
        $yamlPersons  = $this->arrayToYaml($persons);

        return <<<MD
---
id: {$id}
slug: {$slug}
site_id: {$site['id']}
site_name: {$site['name']}
title: {$this->yamlSafe($title)}
summary: {$this->yamlSafe($summary)}
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

{$body}
MD;
    }

    private function arrayToYaml(array $list): string
    {
        if (empty($list)) return "  []\n";

        $yaml = "";
        foreach ($list as $item) {
            $yaml .= "  -\n";
            foreach ($item as $key => $val) {
                $yaml .= "    {$key}: " . $this->yamlSafe((string)$val) . "\n";
            }
        }
        return $yaml;
    }

    /**
     * YAML 安全值（包含特殊字符时加单引号）
     */
    private function yamlSafe(string $val): string
    {
        if (preg_match('/[:#{}[\],&*?|>!%@`\'"\\\]/', $val)) {
            return "'" . str_replace("'", "''", $val) . "'";
        }
        return $val;
    }
}
