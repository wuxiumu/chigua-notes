# 前端优化计划 - Mock数据 + UI/UX完善

## 📍 当前状态

### 已有页面
- ✅ **Home.jsx** - 母站主页（4个子站 + 最新事件）
- ✅ **SitePage.jsx** - 子站详情页（栏目 + 事件列表）
- ✅ **EventDetail.jsx** - 事件详情（时间线 + 观点）
- ✅ **Admin.jsx** - 后台演示（采集 + AI生成流程）
- ✅ **EventCard.jsx** - 事件卡片组件

### 当前问题
- ⚠️ Mock数据不完整，很多字段为空
- ⚠️ 页面缺少说明文案和引导提示
- ⚠️ 空状态（empty state）提示不友好
- ⚠️ 移动端适配需要优化
- ⚠️ 详情页布局可以更美观

---

## 🎯 优化目标（6周完成）

### Phase 1: Mock数据完善 (Week 1-2)
**目标**: 让所有页面都有丰富的示范数据

#### 1.1 创建 `mock.js` 数据库
```javascript
// frontend/src/mock.js
export const mockData = {
  sites: [
    { id: 1, slug: 'game', name: '游戏呀呀', subdomain: 'game.51chigua.com', 
      description: '游戏圈爆料、主播骂战、官方回应...', 
      categories: ['官方声明', '玩家群情', '媒体报道'] },
    { id: 2, slug: 'ai', name: 'AI呀呀', subdomain: 'ai.51chigua.com',
      description: 'ChatGPT、Gemini、Claude...大模型这点事',
      categories: ['政策动向', '技术进展', '安全事件'] },
    // ... tech, star
  ],
  events: [
    { id: 1, slug: 'xhs-algorithm-leak', site_name: '互联网呀呀', 
      title: '小红书内部算法被曝光，千亿估值面临信任危机',
      summary: '一份疑似小红书推荐算法文档在GitHub泄露...',
      content_type: 'news', status: 'fermenting', views: 45200,
      first_seen: '2026-05-28 14:30', updated_at: '2026-05-30 12:45',
      body: `## 事件背景\n小红书因算法争议...\n## 官方回应\n小红书紧急声明...`,
      timeline: [
        { id: 1, happened_at: '2026-05-28 14:30', title: '文档泄露被发现',
          detail: 'GitHub上出现疑似小红书内部算法文档' },
        { id: 2, happened_at: '2026-05-28 18:00', title: '话题登上微博热搜',
          detail: '#小红书算法 冲上第一热搜，讨论数超百万' },
      ],
      opinions: [
        { id: 1, side: 'official', source: '小红书官方微博',
          content: '我们尊重隐私，这份文档不代表现有算法体系' },
        { id: 2, side: 'media', source: '新浪科技',
          content: '算法透明度长期以来是互联网平台的痛点' },
      ],
      persons: [
        { id: 1, name: '小红书CEO毛文超' },
        { id: 2, name: '微博CEO曾盛' },
      ]
    },
    // ... 10+ 个示范事件
  ]
}
```

**关键数据字段完善**:
- ✅ title: 吸引人的标题
- ✅ summary: 100字以内核心概述
- ✅ body: 详细事件分析（markdown格式）
- ✅ timeline: 完整的时间线（3-5个关键节点）
- ✅ opinions: 多方观点（官方/媒体/玩家各1个）
- ✅ persons: 相关人物列表
- ✅ views: 浏览量（生成随机数）

#### 1.2 检测: 所有页面是否都能显示mock数据
- [ ] Home页展示4个子站卡片
- [ ] Home页展示最新10个事件
- [ ] SitePage展示特定子站+该子站事件
- [ ] EventDetail展示完整信息（时间线、观点、人物）

---

### Phase 2: 页面说明和引导 (Week 2-3)
**目标**: 让用户一眼就懂系统的价值和用途

#### 2.1 Home页 - 增强hero section和引导

**改进内容**:
```jsx
// 现有 hero 的增强版本
<section className="hero">
  <span className="label">头条</span>
  <h1>🥒 全网最完整的事件时间线 + 观点聚合</h1>
  <p>游戏、AI、互联网、主播四大领域的爆料去向何处？
     这里不仅有详细的事件时间线，还有各方观点的聚合对比。
     每个事件持续更新，永不下线，方便搜索引擎与 AI 引用。</p>
  
  {/* 新增：快速开始指引 */}
  <div className="getting-started">
    <h3>👉 快速开始</h3>
    <ul>
      <li>🔍 <strong>搜索事件</strong> - 输入关键词快速找到相关呀呀</li>
      <li>🎮 <strong>浏览子站</strong> - 选择你感兴趣的领域深入了解</li>
      <li>⚡ <strong>查看时间线</strong> - 点击任何事件看完整的时间脉络</li>
      <li>💬 <strong>了解多方观点</strong> - 官方、媒体、网友各自怎么说</li>
    </ul>
  </div>
</section>
```

#### 2.2 SitePage - 添加子站简介和栏目说明

```jsx
{/* 在hero和栏目之间添加简介卡片 */}
<div className="site-intro-card">
  <h2>关于{site.name}</h2>
  <p>{site.fullDescription}</p>
  <div className="site-stats">
    <div><strong>{events.length}</strong> 个事件</div>
    <div><strong>{site.categories.length}</strong> 个栏目</div>
    <div><strong>每日更新</strong></div>
  </div>
</div>
```

#### 2.3 EventDetail - 增强可读性和信息架构

**改进**:
- 在summary之前添加"快速了解"卡片（3-5条bullet points）
- timeline左对齐美化（竖线 + 点）
- opinions按重要性排序（官方 > 媒体 > 其他）
- 添加"相关链接"section（参考链接、官方声明等）

#### 2.4 Admin后台 - 完善说明和示例

```jsx
{/* Step1采集 - 添加示例提示 */}
<div className="examples">
  <h4>💡 采集示例</h4>
  <button onClick={() => {
    setTitle('某主播直播间月流水突破3000万，创平台纪录')
    setText('据爆料，该主播最新公开的直播数据...')
  }}>示例1：主播爆料</button>
  <button onClick={() => {
    setTitle('知名游戏工作室CEO被曝性骚扰，官方启动调查')
    setText('据知情人士透露，该工作室CEO被多名女员工举报...')
  }}>示例2：游戏圈爆料</button>
</div>

{/* Step2生成 - 添加预期结果说明 */}
<div className="tips">
  <span>ℹ️ AI 将根据采集内容自动：</span>
  <ul>
    <li>生成吸引人的事件标题</li>
    <li>提炼核心信息的摘要</li>
    <li>整理事件时间线</li>
    <li>聚合多方观点</li>
  </ul>
</div>
```

---

### Phase 3: 用户体验优化 (Week 3-4)
**目标**: 让应用更直观、更容易使用

#### 3.1 搜索优化
```jsx
<div className="searchbar-enhanced">
  <input placeholder="搜索事件、主播、游戏、公司…" />
  <button>搜瓜</button>
  
  {/* 新增搜索提示 */}
  {!q && (
    <div className="search-tips">
      <strong>💡 搜索技巧:</strong>
      <p>输入关键词即可搜索，支持：人名 • 游戏名 • 公司名 • 事件关键词</p>
    </div>
  )}
</div>
```

#### 3.2 Empty States - 友好的空状态提示

```jsx
// 不只是 "暂无内容"，要解释为什么和怎么办
<div className="empty-state">
  <div className="icon">🌱</div>
  <h3>该子站还没有事件</h3>
  <p>这里汇聚的是最受关注的事件。<br/>
     一旦有新的事件发生，我们会第一时间更新。</p>
  <button>← 返回母站浏览其他子站</button>
</div>
```

#### 3.3 移动端优化
```css
/* 响应式调整 */
@media (max-width: 768px) {
  .hero h1 { font-size: 28px; }
  .detail-layout { grid-template-columns: 1fr; }
  .timeline { /* 调整为竖向显示 */ }
  .grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
}
```

#### 3.4 加载状态美化
```jsx
// 用骨架屏替代死板的 "加载中…"
<EventCardSkeleton count={6} />
```

---

### Phase 4: 页面增强功能 (Week 4-5)
**目标**: 增加交互性和实用功能

#### 4.1 Home页增强
- [ ] 快速搜索建议（热门搜索词）
- [ ] 子站浏览卡片上添加"查看更多"链接
- [ ] 最新热瓜列表添加"分类筛选"标签
- [ ] 添加"关于我们"页面链接

#### 4.2 SitePage增强
- [ ] 栏目分类切换（显示该类别下的事件）
- [ ] 事件排序（最新 / 最热 / 最多观点）
- [ ] 搜索该子站内的事件
- [ ] 子站订阅功能（简单的收藏）

#### 4.3 EventDetail增强
- [ ] 相关事件推荐（右侧小卡片）
- [ ] 观点点赞和讨论（为后续评论功能铺垫）
- [ ] 分享按钮（微博、微信、Twitter）
- [ ] 事件关键词标签

#### 4.4 Admin后台增强
- [ ] 历史采集队列显示
- [ ] 生成预览（在发布前看到AI整理的结果）
- [ ] 快速编辑/重新生成功能
- [ ] 生成成功的事件列表和统计

---

### Phase 5: 样式和设计 (Week 5-6)
**目标**: 让应用看起来专业和美观

#### 5.1 色彩和主题
- [ ] 深化"呀呀"主题的视觉语言（西瓜🍉 / 瓜皮🥒 配色）
- [ ] 四个子站各自的品牌色（游戏:紫 / AI:蓝 / 互联网:橙 / 主播:粉）
- [ ] Dark mode 支持

#### 5.2 排版和间距
- [ ] 统一的字号层级（H1/H2/H3/body/small）
- [ ] 卡片间距和阴影
- [ ] 列表项的visual hierarchy

#### 5.3 动效（可选但推荐）
- [ ] 页面切换过渡
- [ ] timeline 逐步显示
- [ ] hover 状态反馈
- [ ] 加载骨架屏淡入

#### 5.4 响应式测试
- [ ] 手机 (375px)
- [ ] 平板 (768px)
- [ ] 桌面 (1280px+)

---

## 📋 实施检查清单

### Week 1-2 (Mock数据)
- [ ] 创建 `frontend/src/mock.js`
- [ ] 添加 10+ 个详细的示范事件
- [ ] 各页面切换为使用 mock 数据
- [ ] 验证所有页面都有内容显示

### Week 2-3 (说明和引导)
- [ ] Home hero 增强（快速开始指引）
- [ ] SitePage 添加子站简介卡片
- [ ] EventDetail 增加快速了解卡片
- [ ] Admin 添加示例按钮和说明

### Week 3-4 (UX优化)
- [ ] 完善所有 empty states
- [ ] 搜索提示和建议
- [ ] 移动端响应式测试
- [ ] 加载骨架屏实现

### Week 4-5 (功能增强)
- [ ] 子站事件分类/排序
- [ ] 相关事件推荐
- [ ] 分享按钮
- [ ] Admin 预览和历史

### Week 5-6 (样式和打磨)
- [ ] 色彩方案定义
- [ ] 全局样式调整
- [ ] Dark mode 实现
- [ ] 移动端终测试

---

## 🎨 样式参考

### 现有 CSS 变量（保留）
```css
--melon: #f3a664     /* 主色 - 蜜瓜色 */
--rind: #9b7e4c      /* 次色 - 瓜皮色 */
--paper: #fefdfb      /* 背景 */
--ink: #1a1410        /* 文字 */
--muted: #7b7367      /* 灰色 */
--fermenting: #fcd34d /* 发酵中 */
--responded: #c7d2fe  /* 已回应 */
--ended: #c7d2fe      /* 已结束 */
```

### 新增配色（可选）
```css
--site-game: #8b5cf6    /* 紫 */
--site-ai: #3b82f6      /* 蓝 */
--site-tech: #f97316    /* 橙 */
--site-star: #ec4899    /* 粉 */

--success: #22c55e
--warning: #f59e0b
--error: #ef4444
--info: #0ea5e9
```

---

## 📊 成功指标

| 指标 | 目标 |
|------|------|
| 页面加载时间 | < 2s (mocked) |
| 移动端可用性 | 所有功能在手机上可用 |
| 说明清晰度 | 新用户 1min 内理解核心价值 |
| 样式一致性 | 全站配色/间距规范 |
| 响应式覆盖 | 375px/768px/1280px 都正常 |

---

## 💡 关键提示

1. **Mock 优先** - 不要等后端，用完整的 mock 数据跑逻辑
2. **减少文字** - 用符号（✅/⚠️/💡）和视觉层级替代长文本
3. **引导用户** - 每个页面都应该告诉用户"下一步做什么"
4. **测试真实** - 在手机上测试，不只是桌面
5. **保持一致** - 建立组件库思维，复用样式和组件

---

## 🚀 后续对接后端

一旦后端 API ready，替换步骤：
1. 删除 `frontend/src/mock.js` 引用
2. API 调用改为指向真实后端
3. 页面逻辑不变，直接使用真实数据

**预期**: 前端代码改动最小化，因为已经是真实数据结构

---

**Timeline**: 6 weeks  
**Team**: 1 Frontend + 1 Designer (可选)  
**Dependencies**: 无 - 完全独立的前端工作  
**Created**: 2026-05-30
