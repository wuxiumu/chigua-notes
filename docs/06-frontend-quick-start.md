# 前端优化 - 快速开始指南

## 🎯 当前状态
✅ **已完成**: Mock数据文件已生成  
⏳ **下一步**: 集成mock到前端，开始优化页面

---

## 📦 新增文件

### 1. `frontend/src/mock.js` 
完整的模拟数据库，包含：
- 4个子站（游戏、AI、互联网、主播）
- 8个详细的示范事件
- 每个事件都有：完整title、summary、body、timeline、opinions、persons
- 辅助函数：getSites()、getSiteBySlug()、getEvents()、getEventBySlug()

**关键数据结构** (打开文件查看完整内容)

---

## 🚀 立即要做的事（优先级P1）

### Step 1: 让前端使用 Mock 数据 (2h)

**目标**: Home、SitePage、EventDetail 都能正常显示mock数据，不依赖后端

**修改文件**: `frontend/src/api/index.js`

**当前代码结构** (示例):
```javascript
// 现在是调用后端
export const api = {
  sites: () => fetch('/api/sites').then(...),
  events: (query) => fetch('/api/events?...').then(...),
  // ...
}
```

**修改为**:
```javascript
import { mockData } from '../mock.js'

export const api = {
  // 使用 mock 数据替代 fetch
  sites: () => Promise.resolve(mockData.getSites()),
  
  events: (query = {}) => Promise.resolve(mockData.getEvents(query)),
  
  site: (slug) => Promise.resolve(mockData.getSiteBySlug(slug)),
  
  event: (slug) => Promise.resolve(mockData.getEventBySlug(slug)),
  
  // 后续添加 addFeed, generate 等（暂时可以mock返回成功）
  addFeed: (feed) => Promise.resolve({ id: Math.random() }),
  
  generate: (params) => Promise.resolve({ 
    event_id: Math.random(), 
    slug: 'new-event' 
  }),
}
```

**验证**: 运行 `npm run dev`，访问 http://localhost:5173
- [ ] 首页显示4个子站卡片
- [ ] 首页显示最新8个事件
- [ ] 可以点击子站进入SitePage
- [ ] 可以点击事件进入EventDetail看完整内容
- [ ] Admin页面可以采集和生成（虽然是mock）

---

### Step 2: 验证Mock数据完整性 (1h)

打开 `frontend/src/mock.js`，检查：

- ✅ **sites** 是否有 4 个（game/ai/tech/star）
- ✅ **events** 是否有 8+ 个完整事件
- ✅ 每个事件是否都有：
  - `title` - 吸引人的标题
  - `summary` - 100字以内概述
  - `body` - markdown格式详情
  - `timeline` - 时间线（3-5个节点）
  - `opinions` - 多方观点（官方/媒体/网友）
  - `persons` - 相关人物
  - `views` - 浏览量

**如果某个事件不完整** → 补充内容

**快速检查脚本**:
```javascript
// 在浏览器控制台运行
import { mockData } from './mock.js'
mockData.events.forEach(e => {
  console.log(e.title, ':', {
    summary: e.summary?.length || 0,
    body: e.body?.length || 0,
    timeline: e.timeline?.length || 0,
    opinions: e.opinions?.length || 0,
  })
})
```

---

### Step 3: 运行和截图 (1h)

```bash
# 进入前端目录
cd frontend

# 安装依赖（如果还没装）
npm install

# 启动开发服务器
npm run dev
```

访问 http://localhost:5173 并测试：

**Home 页** 
- [ ] 显示hero section（标题和描述）
- [ ] 显示4个子站卡片
- [ ] 显示最新热瓜列表（8个事件）
- [ ] 搜索框可用

**SitePage (点击任意子站)**
- [ ] 显示子站名字和描述
- [ ] 显示该子站的所有事件列表
- [ ] 返回母站链接可用

**EventDetail (点击任意事件)**
- [ ] 显示事件标题、摘要
- [ ] 显示完整的body内容
- [ ] 显示时间线（timeline）
- [ ] 显示多方观点（opinions）
- [ ] 显示相关人物（persons）

**Admin 页**
- [ ] Step1 采集表单可用（可以输入标题和内容）
- [ ] Step2 生成按钮可用
- [ ] 日志窗口会显示操作记录

---

## 📋 接下来的计划（Phase 2-3）

### Phase 2: 页面说明和引导 (Week 2-3)
一旦 mock 数据完全跑通，开始优化页面：

1. **Home 页增强** (FE004, FE005)
   - 增强 hero section 文案，添加快速开始指引
   - 子站卡片添加图标和互动

2. **SitePage 增强** (FE006, FE007)
   - 添加子站简介卡片
   - 实现事件分类和排序功能

3. **EventDetail 增强** (FE008, FE009)
   - 添加"快速了解"卡片（bullet points）
   - 相关事件推荐列表

4. **Admin 增强** (FE010, FE011)
   - 示例采集按钮（一键填充示范文案）
   - 详细说明AI会做什么

### Phase 3: UX 和样式优化 (Week 3-4)
- Empty states 友好提示
- 搜索技巧提示
- 加载骨架屏
- 移动端适配完整测试
- 色彩方案定义（4个子站品牌色）
- 整体样式统一（字号、间距、阴影）

### Phase 4: 功能增强和设计 (Week 4-6)
- 事件分享功能
- 观点点赞功能
- 相关热词标签
- Dark mode
- 动效和过渡

---

## 🎨 设计参考

### 现有色彩系统
```css
--melon: #f3a664       /* 主色 - 蜜瓜色 */
--rind: #9b7e4c        /* 次色 - 瓜皮色 */
--paper: #fefdfb       /* 背景 */
--ink: #1a1410         /* 文字 */
--muted: #7b7367       /* 灰色 */

/* 事件状态色 */
--fermenting: #fcd34d  /* 发酵中 - 黄 */
--responded: #c7d2fe   /* 已回应 - 紫 */
--ended: #9ca3af       /* 已结束 - 灰 */
```

### 推荐增加的品牌色
```css
--site-game: #8b5cf6   /* 游戏 - 紫 */
--site-ai: #3b82f6     /* AI - 蓝 */
--site-tech: #f97316   /* 互联网 - 橙 */
--site-star: #ec4899   /* 主播 - 粉 */
```

---

## 📝 Common Patterns

### 模板：增强 Hero Section

**现在**:
```jsx
<section className="hero">
  <span className="label">头条</span>
  <h1>全网最完整的事件时间线 + 观点聚合</h1>
  <p>游戏 · AI · 互联网 · 主播 —— ...</p>
</section>
```

**优化后** (参考 `FRONTEND-OPTIMIZATION-PLAN.md`):
```jsx
<section className="hero">
  <span className="label">头条</span>
  <h1>🥒 全网最完整的事件时间线 + 观点聚合</h1>
  <p>...（增强文案）</p>
  
  {/* 新增：快速开始指引 */}
  <div className="getting-started">
    <h3>👉 快速开始</h3>
    <ul>
      <li>🔍 <strong>搜索事件</strong> - 输入关键词快速找到相关呀呀</li>
      <li>🎮 <strong>浏览子站</strong> - 选择你感兴趣的领域深入了解</li>
      {/* ... */}
    </ul>
  </div>
</section>
```

### 模板：Empty State

**现在**:
```jsx
<div className="empty">暂无内容。先到 <Link to="/admin">后台</Link> 采集试试。</div>
```

**优化后**:
```jsx
<div className="empty-state">
  <div className="icon">🌱</div>
  <h3>该子站还没有事件</h3>
  <p>这里汇聚的是最受关注的事件。<br/>
     一旦有新的事件发生，我们会第一时间更新。</p>
  <button onClick={() => navigate('/')}>← 返回母站浏览其他子站</button>
</div>
```

---

## 🔍 故障排除

### 问题：Home 页为空
**原因**: api 还没改成 mock  
**解决**: 检查 `api/index.js` 是否正确导入了 `mockData`

### 问题：事件列表空白
**原因**: mock.js 中的数据不完整  
**解决**: 在 mock.js 中补充完整的 body/timeline/opinions 字段

### 问题：样式破损
**原因**: CSS 变量没更新  
**解决**: 检查 `styles.css` 是否有所有必要的变量定义

---

## 📞 下一步沟通

- **已完成**: ✅ Mock 数据库创建
- **开发人员**: 完成 Step 1-3（集成 mock 和验证）
- **下周会议**: 展示 mock 数据页面，讨论优化优先级

---

## 📚 相关文档

- [`FRONTEND-OPTIMIZATION-PLAN.md`](FRONTEND-OPTIMIZATION-PLAN.md) - 详细的5周优化计划
- [`frontend-tasks.csv`](frontend-tasks.csv) - 所有任务清单
- [`../../frontend/src/mock.js`](../../frontend/src/mock.js) - Mock数据文件

---

**Last Updated**: 2026-05-30  
**Status**: Ready to Start  
**Estimated Time**: Phase 1 = 6h
