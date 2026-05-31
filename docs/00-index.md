# 51呀呀 项目文档

## 📂 文档索引

### 核心文档

| # | 文件 | 说明 |
|---|------|------|
| [01](01-project-plan.md) | **项目计划与进度** ⭐ | 整体进度、里程碑、下一步行动 |
| [02](02-improvement-plan.md) | 改进战略计划 | 25 项任务，1120h，三阶段规划 |
| [03](03-api.md) | API 文档 | 6 个端点 + Markdown 数据格式 + 客户端用法 |
| [04](04-data-architecture.md) | 数据架构 | Markdown 数据源详解 + 操作指南 |

### 参考文档

| # | 文件 | 说明 |
|---|------|------|
| [05](05-frontend-optimization.md) | 前端优化计划 | 6 周 5 阶段前端优化方案 |
| [06](06-frontend-quick-start.md) | 前端快速上手 | Mock 数据集成指南 |
| [07](07-ai-collection-design.md) | AI 接入 + 采集方案 | 千问 AI 接入设计、数据采集流程、MVP 跑通 |
| [08](08-tophub-scraper-design.md) | TopHub 采集方案 | 全网热榜采集、黑名单过滤、按站点+日期缓存 |
| [09](09-admin-content-design.md) | **内容管理后台方案** | 浏览缓存 → 勾选 → AI 生成 → 发布的完整设计 |

### 数据文件

| 文件 | 说明 |
|------|------|
| `improvement-roadmap.csv` | 结构化任务清单 |
| `frontend-tasks.csv` | 前端任务清单 |

---

## 🏗️ 项目概览

**51呀呀** — 泛娱乐事件时间线 + 观点聚合平台

- **技术栈**: React 18 (Vite) + PHP 8 (SQLite)
- **数据源**: SQLite（站点/栏目）+ Markdown 文件（事件）
- **4 个子站**: 游戏、AI、互联网、主播
- **8 个事件**: 每个包含完整时间线、多方观点、相关人物

## 🚀 快速开始

```bash
./run.sh    # 一键启动（PHP 后端 + Vite 前端）
```

打开 `http://localhost:5173` 即可访问。
