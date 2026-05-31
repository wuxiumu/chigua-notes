# 数据架构说明

## 架构概览

```
chigua/
├── backend/
│   ├── config/config.php          # 数据库连接配置
│   ├── public/index.php           # API 路由入口
│   ├── src/
│   │   ├── DB.php                 # SQLite PDO 封装（站点/栏目数据）
│   │   ├── AiSummarizer.php       # AI 摘要生成器（Mock）
│   │   └── MarkdownEventReader.php # Markdown 事件解析器 ★
│   ├── data/
│   │   ├── chigua.sqlite          # SQLite 数据库（站点、栏目、原始 feed）
│   │   └── events/                # ★ 事件数据目录
│   │       ├── 001-xhs-algorithm-leak.md
│   │       ├── 002-claude-4-breakthrough.md
│   │       ├── 003-honkai-star-rail-drama.md
│   │       ├── 004-douyin-anchor-breakup.md
│   │       ├── 005-openai-gpt5-leak.md
│   │       ├── 006-bilibili-vup-protest.md
│   │       ├── 007-genshin-anniversary.md
│   │       └── 008-tech-giant-antitrust.md
│   └── scripts/
│       ├── schema.sql             # 数据库建表语句
│       └── init.php               # 数据库初始化 + 种子数据
├── frontend/
│   ├── src/
│   │   ├── mock.js                # 站点/栏目本地 mock（仅 4 个子站）
│   │   └── api/index.js           # 统一 API 客户端
│   └── ...
└── docs/
    └── API.md                     # API 文档
```

## 数据分工

| 数据类型 | 存储方式 | 原因 |
|----------|----------|------|
| 站点配置（sites） | SQLite | 低频变动，需要与栏目关联查询 |
| 栏目分类（categories） | SQLite | 与站点强关联 |
| 原始采集（raw_feeds） | SQLite | 待处理队列，需要状态标记 |
| **事件数据（events）** | **Markdown 文件** | **高频编辑，Git 版本控制，人工可读** |

## Markdown 事件文件

### 为什么用 Markdown？

1. **人工可读** — 编辑事件就像写文章
2. **Git 友好** — 每次修改都有 diff 记录
3. **版本控制** — 事件演变历史清晰
4. **无数据库依赖** — 新增事件不需要 SQL
5. **易于迁移** — 未来可对接 CMS、Headless 等

### 文件格式

```
---
YAML Frontmatter（结构化数据）
---
Markdown Body（事件正文）
```

Frontmatter 包含：id、slug、site_id、title、summary、status、content_type、
views、first_seen、updated_at、timeline[]、opinions[]、persons[]

Body 包含：事件详细叙述，使用标准 Markdown 语法。

### 命名规则

```
NNN-slug.md
```

- `NNN`：3 位序号，从 `001` 开始递增
- `slug`：英文标识，全小写，单词间用 `-` 连接
- 示例：`009-tiktok-ban.md`

### 如何新增事件

**方法一：直接创建文件**

```bash
cd backend/data/events
touch 009-new-event.md
# 编辑文件，复制现有文件的 frontmatter 模板
```

**方法二：使用工具脚本（待开发）**

```bash
php backend/scripts/create-event.php --title "事件标题" --site game
```

## 当前数据状态

| 事件 | 子站 | 状态 | 时间线 | 观点 | 人物 |
|------|------|------|--------|------|------|
| 001 小红书算法泄露 | 互联网 | fermenting | 5 | 3 | 3 |
| 002 Claude 4.7 发布 | AI | responded | 3 | 3 | 3 |
| 003 崩坏画师网暴 | 游戏 | fermenting | 3 | 3 | 3 |
| 004 抖音CP分手 | 主播 | fermenting | 2 | 3 | 3 |
| 005 GPT-5 泄露 | AI | fermenting | 6 | 4 | 3 |
| 006 B站VUP抗议 | 主播 | responded | 6 | 4 | 3 |
| 007 原神福利缩水 | 游戏 | fermenting | 7 | 4 | 3 |
| 008 反垄断调查 | 互联网 | fermenting | 6 | 4 | 3 |

## SQLite 表结构

仍然保留的表：

- `sites` — 子站配置
- `categories` — 栏目分类
- `raw_feeds` — 原始采集队列
- `events` — 事件表（仍保留，用于 AI 生成发布时写入，之后可手动迁移为 md）
- `timelines` — 时间线（同上）
- `opinions` — 观点（同上）
- `persons` — 人物（同上）
- `event_persons` — 事件人物关联（同上）

> **未来规划：** AI 生成的事件直接输出为 Markdown 文件，不再写入 SQLite 的 events 系列表。
> 届时 events 系列表可以完全删除。
