# 51呀呀 · 泛娱乐呀呀母站（MVP）

游戏 / AI / 互联网 / 主播 四大子站，共享同一套**事件库 + 时间线 + 观点聚合**系统。
前端 **React (Vite)**，后端**简单 PHP**（单文件路由 + SQLite，零外部依赖）。

> 当前 AI 整理为 **mock 占位**，跑通「采集 → 整理 → 发布」全流程；真实接入只需替换一个函数。

## 架构

```
chigua/
├── backend/                  # PHP 后端（无框架，PDO + SQLite）
│   ├── public/index.php      # 唯一入口 / API 路由
│   ├── src/
│   │   ├── DB.php            # PDO 封装
│   │   └── AiSummarizer.php  # AI整理（mock，替换此文件即可接真实API）
│   ├── config/config.php
│   └── scripts/
│       ├── schema.sql        # 表结构：sites/categories/events/timelines/persons/opinions/raw_feeds
│       └── init.php          # 建库 + 子站/栏目/示范事件 种子
├── frontend/                 # React + Vite + react-router
│   └── src/{pages,components,api}
└── run.sh                    # 一键起后端+前端
```

子站矩阵（对应文档拆分）：
- 游戏呀呀 `game.51chigua.com`
- AI呀呀 `ai.51chigua.com`
- 互联网呀呀 `tech.51chigua.com`
- 主播呀呀 `star.51chigua.com`

## 环境要求
- PHP **8.0+**（需 `pdo_sqlite`，PHP 自带）
- Node **18+**

## 快速运行

```bash
# 方式一：一键
chmod +x run.sh && ./run.sh

# 方式二：分开起（两个终端）
# 终端1 后端
php backend/scripts/init.php                       # 建库+种子（只需一次）
php -S localhost:8000 -t backend/public            # 后端 :8000
# 终端2 前端
cd frontend && npm install && npm run dev          # 前端 :5173
```

打开 http://localhost:5173 ：
- 首页 = 母站，展示子站矩阵 + 最新事件
- `⚙ 后台` 可演示 **Step1 采集 → Step2/3 AI生成发布** 流程
- 点任意事件进详情页，看时间线 + 各方观点聚合

## API 一览

| 方法 | 路径 | 说明 |
|---|---|---|
| GET  | `/api/sites` | 所有子站 |
| GET  | `/api/sites/{slug}` | 子站详情 + 栏目 |
| GET  | `/api/events?site=&status=&type=&q=&page=` | 事件列表（可筛选/搜索） |
| GET  | `/api/events/{slug}` | 事件详情（含时间线/观点/人物） |
| POST | `/api/feeds` | Step1：采集入库 `{source,title,text}` |
| POST | `/api/generate` | Step2/3：取队列→AI整理→发布 `{site,feed_id?}` |

## 接入真实 AI

打开 `backend/src/AiSummarizer.php`，把 `summarize()` 里 MOCK 段替换为对
OpenAI / Claude 的 HTTP 调用，返回结构（title/summary/body/timeline/opinions）保持不变即可，
前端与发布逻辑无需任何改动。

## 推到 GitHub

```bash
cd chigua
git init
git add .
git commit -m "init: 51呀呀 泛娱乐母站 MVP (React + PHP)"
git branch -M main
git remote add origin git@github.com:<你的用户名>/<仓库名>.git
git push -u origin main
```

## 后续路线
- [ ] 接真实 AI（替换 AiSummarizer）
- [ ] 真实采集脚本（微博热搜/NGA/TapTap/B站… 写入 `/api/feeds`）
- [ ] 事件聚类去重
- [ ] SSR / 预渲染（利于 SEO 与 AI 引用）
- [ ] sitemap + 结构化数据(JSON-LD)
- [ ] 广告位 / CPS 联盟
