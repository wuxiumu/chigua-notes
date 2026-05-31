# 51呀呀 API 文档

## 概览

| 项目 | 说明 |
|------|------|
| Base URL | `http://localhost:8000/api` |
| 数据格式 | JSON (UTF-8) |
| 跨域 | 已配置 CORS（见 `backend/config/config.php`） |

## 数据源架构

```
站点/栏目  → SQLite (backend/data/chigua.sqlite)
事件数据    → Markdown 文件 (backend/data/events/*.md)
```

每个事件是一个独立的 `.md` 文件，格式为 **YAML frontmatter + Markdown body**。

## 文件命名规则

```
backend/data/events/{序号}-{slug}.md
```

- 序号：3 位数字，按创建顺序递增（`001`、`002`、`003`...）
- slug：事件英文标识，小写 + 连字符（如 `xhs-algorithm-leak`）
- 示例：`001-xhs-algorithm-leak.md`

## Markdown 文件格式

```markdown
---
id: 1                              # 事件 ID（唯一递增整数）
slug: xhs-algorithm-leak           # 事件唯一标识（URL 中使用）
site_id: 3                         # 所属子站 ID（对应 sites 表）
site_name: 互联网呀呀              # 子站名称（冗余字段，避免关联查询）
title: 小红书内部算法被曝光...      # 事件标题
summary: 一份疑似小红书推荐...      # 事件摘要（列表页展示）
content_type: news                 # 内容类型: news | analysis | wiki
status: fermenting                 # 事件状态: fermenting | responded | ended
views: 45200                       # 浏览量
first_seen: '2026-05-28 14:30'    # 首次出现时间
updated_at: '2026-05-30 12:45'    # 最后更新时间
timeline:                          # 时间线（数组）
  - id: 1
    happened_at: '2026-05-28 14:30'
    title: 泄露文档被发现
    detail: GitHub安全研究者发现...
  - id: 2
    happened_at: '2026-05-28 16:45'
    title: 话题冲上微博热搜
    detail: '#小红书算法泄露 冲上...'
opinions:                          # 各方观点（数组）
  - id: 1
    side: official                 # 观点方: official | media | player
    source: 小红书官方微博
    content: 我们尊重数据安全...
  - id: 2
    side: media
    source: 新浪科技
    content: 这次泄露事件暴露了...
persons:                           # 相关人物（数组）
  - id: 1
    name: 小红书CEO毛文超
  - id: 2
    name: 小红书首席安全官
---

## 事件背景

正文内容，支持任意 Markdown 语法。
前端使用简易渲染器处理（## 标题 → h2，段落 → p）。

## 泄露详情

更多内容...
```

### frontmatter 字段说明

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `id` | int | ✅ | 事件唯一 ID，递增整数 |
| `slug` | string | ✅ | URL 安全标识，全小写 + 连字符 |
| `site_id` | int | ✅ | 所属子站 ID，关联 sites 表 |
| `site_name` | string | ✅ | 子站显示名称 |
| `title` | string | ✅ | 事件标题 |
| `summary` | string | ✅ | 事件摘要，100 字以内 |
| `content_type` | string | ✅ | `news`（新闻）/ `analysis`（分析）/ `wiki`（百科） |
| `status` | string | ✅ | `fermenting`（发酵中）/ `responded`（已回应）/ `ended`（已结束） |
| `views` | int | ✅ | 浏览量，初始 0 |
| `first_seen` | string | ✅ | 首次发现时间，格式 `YYYY-MM-DD HH:mm` |
| `updated_at` | string | ✅ | 最后更新时间 |
| `timeline` | array | ✅ | 时间线数组，按时间顺序排列 |
| `timeline[].id` | int | ✅ | 时间线条目 ID |
| `timeline[].happened_at` | string | ✅ | 发生时间 |
| `timeline[].title` | string | ✅ | 节点标题 |
| `timeline[].detail` | string | ✅ | 节点详情 |
| `opinions` | array | ✅ | 观点数组 |
| `opinions[].id` | int | ✅ | 观点 ID |
| `opinions[].side` | string | ✅ | `official`（官方）/ `media`（媒体）/ `player`（网友/当事人） |
| `opinions[].source` | string | ✅ | 来源标识 |
| `opinions[].content` | string | ✅ | 观点内容 |
| `persons` | array | ✅ | 相关人物数组 |
| `persons[].id` | int | ✅ | 人物 ID |
| `persons[].name` | string | ✅ | 人物名称 |

### 新增事件

1. 在 `backend/data/events/` 下创建新文件
2. 文件名按 `序号-slug.md` 格式（序号递增）
3. 编写 frontmatter（必填字段）和 body
4. 无需重启服务，API 自动读取新文件

### 修改事件

直接编辑对应 `.md` 文件，保存即生效。

### 删除事件

删除对应 `.md` 文件即可。

---

## API 端点

### 1. 获取所有子站

```
GET /api/sites
```

**响应：**

```json
[
  {
    "id": 1,
    "slug": "game",
    "name": "🎮 游戏呀呀",
    "subdomain": "game.51chigua.com",
    "description": "游戏圈爆料...",
    "categories": [
      { "id": 1, "name": "官方声明" },
      { "id": 2, "name": "玩家群情" }
    ]
  }
]
```

### 2. 获取子站详情

```
GET /api/sites/{slug}
```

**响应：** 同上单个对象。

### 3. 获取事件列表

```
GET /api/events?site=game&status=fermenting&type=news&q=关键词&page=1
```

**查询参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| `site` | string | 子站 slug（如 `game`、`ai`） |
| `status` | string | 过滤状态（`fermenting` / `responded` / `ended`） |
| `type` | string | 过滤类型（`news` / `analysis` / `wiki`） |
| `q` | string | 关键词搜索（匹配标题、摘要、子站名） |
| `page` | int | 页码，默认 1，每页 10 条 |

**响应：**

```json
[
  {
    "id": 1,
    "slug": "xhs-algorithm-leak",
    "site_id": 3,
    "site_name": "互联网呀呀",
    "title": "小红书内部算法被曝光...",
    "summary": "一份疑似小红书推荐...",
    "content_type": "news",
    "status": "fermenting",
    "views": 45200,
    "first_seen": "2026-05-28 14:30",
    "updated_at": "2026-05-30 12:45",
    "timeline": [...],
    "opinions": [...],
    "persons": [...]
  }
]
```

> **注意：** 列表接口目前返回完整数据（含 timeline/opinions/persons）。未来如需优化传输量，可在 PHP 层移除 body 字段。

### 4. 获取事件详情

```
GET /api/events/{slug}
```

**响应：** 单个事件对象，包含完整 body、timeline、opinions、persons。

### 5. 采集落地

```
POST /api/feeds
Content-Type: application/json

{
  "source": "微博",
  "title": "某事件标题",
  "text": "事件原始内容..."
}
```

**响应：**

```json
{ "id": 1, "ok": true }
```

### 6. AI 生成并发布

```
POST /api/generate
Content-Type: application/json

{
  "site": "game",
  "feed_id": 1
}
```

**响应：**

```json
{ "event_id": 5, "slug": "evt-xxxxx", "ok": true }
```

---

## 前端 API 客户端

文件：`frontend/src/api/index.js`

```javascript
import { api } from './api'

// 获取所有子站
const sites = await api.sites()

// 获取子站详情
const site = await api.site('game')

// 获取事件列表（支持过滤）
const events = await api.events({ site: 'game', status: 'fermenting' })

// 搜索事件
const results = await api.events({ q: '小红书' })

// 获取事件详情
const event = await api.event('xhs-algorithm-leak')

// 采集
await api.addFeed({ source: '微博', title: '...', text: '...' })

// AI 生成
await api.generate({ site: 'game', feed_id: 1 })
```

### 环境变量

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `VITE_API_BASE` | `/api` | API 基础路径 |
| `VITE_USE_MOCK` | `true` | 站点数据是否使用本地 mock |

---

## 前端路由

| 路由 | 组件 | 说明 |
|------|------|------|
| `/` | Home.jsx | 母站首页（搜索 + 子站矩阵 + 事件列表） |
| `/s/:siteSlug` | SitePage.jsx | 子站页面（简介 + 栏目 + 事件列表） |
| `/e/:eventSlug` | EventDetail.jsx | 事件详情（时间线 + 正文 + 观点 + 人物 + 相关事件） |
| `/hot` | HotPage.jsx | 热榜页面（按浏览量排序 + 状态过滤） |
| `/admin` | Admin.jsx | 管理后台（采集 → AI 生成 → 发布） |

---

## PHP Markdown 解析器

文件：`backend/src/MarkdownEventReader.php`

```php
$reader = new MarkdownEventReader();

// 获取所有事件
$events = $reader->getEvents();

// 按条件过滤
$events = $reader->getEvents([
    'site_id' => 2,      // 按子站过滤
    'status' => 'fermenting',
    'type' => 'news',
    'q' => '关键词',
    'page' => 1,
    'page_size' => 10,
]);

// 获取单个事件
$event = $reader->getEventBySlug('xhs-algorithm-leak');
```
