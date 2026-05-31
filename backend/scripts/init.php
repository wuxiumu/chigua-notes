<?php
// 初始化数据库 + 填充种子数据
require __DIR__ . '/../src/DB.php';
use App\DB;

$config = require __DIR__ . '/../config/config.php';
@mkdir(dirname($config['db_path']), 0777, true);

// 建表
$schema = file_get_contents(__DIR__ . '/schema.sql');
DB::conn()->exec($schema);

// 子站
$sites = [
    ['game', '游戏呀呀', 'game.51chigua.com', '游戏圈事件百科 + 呀呀时间线 + 玩家观点聚合'],
    ['ai',   'AI呀呀',   'ai.51chigua.com',   'AI圈热点、模型大战、行业八卦聚合'],
    ['tech', '互联网呀呀', 'tech.51chigua.com', '互联网大厂事件与行业争议聚合'],
    ['star', '网红主播呀呀', 'star.51chigua.com', '主播塌房、网红争议事件全记录'],
];
foreach ($sites as $s) {
    $exists = DB::one('SELECT id FROM sites WHERE slug = ?', [$s[0]]);
    if (!$exists) {
        DB::exec('INSERT INTO sites (slug, name, subdomain, description) VALUES (?,?,?,?)', $s);
    }
}

// 栏目（每个子站建几个核心栏目）
$cats = [
    'game' => ['王者荣耀事件', '原神事件', 'DNF事件', 'LOL事件', 'Steam事件', '游戏停服'],
    'ai'   => ['模型发布', '行业八卦', '开源争议'],
    'tech' => ['腾讯', '阿里', '字节', '裁员潮'],
    'star' => ['主播塌房', '直播争议', '网红翻车'],
];
foreach ($cats as $siteSlug => $names) {
    $site = DB::one('SELECT id FROM sites WHERE slug = ?', [$siteSlug]);
    foreach ($names as $n) {
        $catSlug = $siteSlug . '-' . substr(md5($n), 0, 6);
        $exists = DB::one('SELECT id FROM categories WHERE site_id = ? AND slug = ?', [$site['id'], $catSlug]);
        if (!$exists) {
            DB::exec('INSERT INTO categories (site_id, slug, name) VALUES (?,?,?)', [$site['id'], $catSlug, $n]);
        }
    }
}

// 几条示范事件（演示三种内容类型 + 时间线 + 观点）
function seedEvent($siteSlug, $title, $summary, $type, $status, $timeline, $opinions) {
    $site = DB::one('SELECT id FROM sites WHERE slug = ?', [$siteSlug]);
    $slug = 'demo-' . substr(md5($title), 0, 8);
    if (DB::one('SELECT id FROM events WHERE slug = ?', [$slug])) return;
    $body = "## 事件简介\n$summary\n\n## 起因\n（示范数据）\n\n## 争议点\n（示范数据）\n\n## 玩家观点\n（示范数据）\n";
    $id = DB::exec('INSERT INTO events (site_id, slug, title, summary, body, content_type, status)
                    VALUES (?,?,?,?,?,?,?)', [$site['id'], $slug, $title, $summary, $body, $type, $status]);
    foreach ($timeline as $i => $t) {
        DB::exec('INSERT INTO timelines (event_id, happened_at, title, detail, sort_order)
                  VALUES (?,?,?,?,?)', [$id, $t[0], $t[1], $t[2], $i]);
    }
    foreach ($opinions as $o) {
        DB::exec('INSERT INTO opinions (event_id, source, side, content) VALUES (?,?,?,?)', [$id, $o[0], $o[1], $o[2]]);
    }
}

seedEvent('game', '某热门MOBA手游赛季更新引发玩家争议事件全记录',
    '新赛季改动幅度大，部分英雄遭削弱，玩家社区出现两极评价，本文中立梳理事件全貌。',
    'wiki', 'fermenting',
    [['2026-05-28 10:00','官方公告','发布新赛季更新说明'],
     ['2026-05-28 14:30','社区发酵','贴吧/NGA出现大量讨论'],
     ['2026-05-29 09:00','官方回应','发布平衡性补充说明']],
    [['NGA','player','改动太大，老玩家不适应'],
     ['微博','player','整体方向是好的，需要时间适应'],
     ['官方','official','会持续根据数据微调']]);

seedEvent('star', '某头部主播直播间言论争议事件持续跟进',
    '主播在直播中的一段言论引发争议，相关话题登上热搜，本文中立整理时间线与各方回应。',
    'analysis', 'responded',
    [['2026-05-27 20:00','直播发生','争议言论被剪辑传播'],
     ['2026-05-28 08:00','登上热搜','话题阅读量破亿'],
     ['2026-05-28 18:00','主播道歉','发布致歉声明']],
    [['抖音','player','觉得是被断章取义'],
     ['微博','media','多家媒体跟进报道'],
     ['当事人','official','已发声明致歉']]);

seedEvent('ai', '某大模型发布会数据对比引发同行讨论',
    '新模型发布会公布的对比数据引发同行与社区讨论，本文中立整理各方观点。',
    'news', 'fermenting',
    [['2026-05-29 10:00','发布会','公布benchmark对比'],
     ['2026-05-29 15:00','社区讨论','测评博主提出质疑']],
    [['知乎','player','基准选择有讲究'],
     ['官方','official','数据可复现']]);

echo "✅ 数据库初始化完成：" . $config['db_path'] . "\n";
echo "   子站 " . count($sites) . " 个，示范事件 3 条。\n";
