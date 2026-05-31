import { useEffect, useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'

const PAGE_SIZE = 20
const VIEW_CALENDAR = 'calendar'
const VIEW_CATEGORY = 'category'
const WEEKDAYS = ['一', '二', '三', '四', '五', '六', '日']

// ===== 日历组件 =====
function MiniCalendar({ dates, selected, onSelect }) {
  const dateSet = new Set(dates)
  const today = new Date()
  const [year, setYear] = useState(today.getFullYear())
  const [month, setMonth] = useState(today.getMonth())

  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const firstDay = new Date(year, month, 1).getDay()
  const offset = firstDay === 0 ? 6 : firstDay - 1

  const prevMonth = () => {
    if (month === 0) { setYear(y => y - 1); setMonth(11) }
    else setMonth(m => m - 1)
  }
  const nextMonth = () => {
    if (month === 11) { setYear(y => y + 1); setMonth(0) }
    else setMonth(m => m + 1)
  }

  const cells = []
  for (let i = 0; i < offset; i++) cells.push(<div key={`e${i}`} className="cal-cell cal-empty" />)

  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    const hasData = dateSet.has(dateStr)
    const isActive = dateStr === selected
    cells.push(
      <button key={dateStr}
        className={`cal-cell ${hasData ? 'cal-has' : 'cal-none'} ${isActive ? 'cal-active' : ''}`}
        disabled={!hasData}
        onClick={() => hasData && onSelect(dateStr)}>
        {d}
      </button>
    )
  }

  return (
    <div className="mini-calendar">
      <div className="cal-nav">
        <button className="cal-nav-btn" onClick={prevMonth}>◀</button>
        <span className="cal-month-label">{year} 年 {month + 1} 月</span>
        <button className="cal-nav-btn" onClick={nextMonth}>▶</button>
      </div>
      <div className="cal-weekdays">{WEEKDAYS.map(w => <span key={w}>{w}</span>)}</div>
      <div className="cal-grid">{cells}</div>
      <button className="cal-clear-btn" onClick={() => onSelect('')}>全部日期</button>
    </div>
  )
}

export default function Admin() {
  const [viewMode, setViewMode] = useState(VIEW_CALENDAR)

  // 分类视图
  const [cacheSites, setCacheSites] = useState([])
  const [selectedSite, setSelectedSite] = useState('')

  // 日历视图
  const [cacheDates, setCacheDates] = useState([])
  const [calendarDate, setCalendarDate] = useState('')
  const [calendarFilterSite, setCalendarFilterSite] = useState('')
  const [calendarCategories, setCalendarCategories] = useState([])
  const [calendarCategory, setCalendarCategory] = useState('') // 空 = 全部

  // 共享
  const [selectedDate, setSelectedDate] = useState('')  // 分类视图选中的日期，空=全部
  const [showDatePicker, setShowDatePicker] = useState(false)
  const [items, setItems] = useState([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [searchQ, setSearchQ] = useState('')
  const [loading, setLoading] = useState(false)
  const [selectedItems, setSelectedItems] = useState([])
  const [targetSite, setTargetSite] = useState('ai')
  const [stats, setStats] = useState(null)
  const [generating, setGenerating] = useState(false)
  const [preview, setPreview] = useState(null)
  const [publishing, setPublishing] = useState(false)
  const [editMode, setEditMode] = useState(false)

  useEffect(() => {
    api.admin.cacheSites().then(setCacheSites).catch(() => setCacheSites([]))
    api.admin.cacheDates().then(setCacheDates).catch(() => setCacheDates([]))
    api.admin.stats().then(setStats).catch(() => {})
    setCalendarDate(new Date().toISOString().slice(0, 10))
  }, [])

  // 日历视图：日期变化时加载分类统计
  useEffect(() => {
    if (viewMode !== VIEW_CALENDAR || !calendarDate) return
    api.admin.cacheDateCategories(calendarDate)
      .then(res => {
        setCalendarCategories(res || [])
        setCalendarCategory('') // 默认全部
      })
      .catch(() => setCalendarCategories([]))
  }, [viewMode, calendarDate])

  const loadData = useCallback(() => {
    let date, site
    if (viewMode === VIEW_CALENDAR) {
      date = calendarDate
      site = calendarCategory // 分类过滤（空 = 全部站点）
    } else {
      date = selectedDate  // 空 = 全部日期
      site = selectedSite
    }

    if (viewMode === VIEW_CALENDAR) {
      if (!date) { setItems([]); setTotal(0); return }
      setLoading(true)
      api.admin.cacheByDate(date, site, searchQ, page)
        .then(res => { setItems(res.items || []); setTotal(res.total || 0) })
        .catch(() => { setItems([]); setTotal(0) })
        .finally(() => setLoading(false))
    } else {
      if (!site) { setItems([]); setTotal(0); return }
      setLoading(true)
      api.admin.cache(date, site, searchQ, page)
        .then(res => { setItems(res.items || []); setTotal(res.total || 0) })
        .catch(() => { setItems([]); setTotal(0) })
        .finally(() => setLoading(false))
    }
  }, [viewMode, calendarDate, calendarCategory, calendarFilterSite, selectedSite, selectedDate, searchQ, page])

  useEffect(() => { loadData() }, [loadData])

  const handleSiteChange = useCallback((site) => {
    setSelectedSite(site)
    setSelectedDate('')  // 默认全部日期
    setPage(1)
    setSelectedItems([])
    setPreview(null)
  }, [])

  const toggleSelect = useCallback((item) => {
    setSelectedItems(prev => {
      const exists = prev.find(i => i.md5 === item.md5)
      if (exists) return prev.filter(i => i.md5 !== item.md5)
      return [...prev, item]
    })
  }, [])

  const isSelected = useCallback((md5) => selectedItems.some(i => i.md5 === md5), [selectedItems])

  const handleGenerate = async () => {
    if (selectedItems.length === 0) return
    setGenerating(true)
    try {
      const result = await api.admin.generate(selectedItems, targetSite)
      setPreview(result); setEditMode(false)
    } catch (err) { alert('AI 生成失败: ' + (err.message || '未知错误')) }
    finally { setGenerating(false) }
  }

  const handlePublish = async () => {
    if (!preview) return
    setPublishing(true)
    try {
      const result = await api.admin.publish({
        title: preview.title, summary: preview.summary, body: preview.body,
        timeline: preview.timeline, opinions: preview.opinions,
        persons: preview.persons, site_slug: targetSite,
        source_md5s: selectedItems.map(i => i.md5),
      })
      alert(`✅ 发布成功！\n文件: ${result.file}\nSlug: ${result.slug}`)
      setPreview(null); setSelectedItems([]); setEditMode(false)
      api.admin.stats().then(setStats).catch(() => {})
    } catch (err) { alert('发布失败: ' + (err.message || '未知错误')) }
    finally { setPublishing(false) }
  }

  const switchView = (mode) => {
    setViewMode(mode)
    setPage(1); setSearchQ('')
    setSelectedItems([]); setPreview(null); setEditMode(false)
  }

  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE))
  const siteDates = selectedSite ? (cacheSites.find(s => s.site === selectedSite)?.dates || []) : []

  // 标题：日历视图显示"日期 · 分类或全部"，分类视图显示"站点 · 日期或全部"
  const viewTitle = viewMode === VIEW_CALENDAR
    ? `📅 ${calendarDate}${calendarCategory ? ' · ' + calendarCategory : ' · 全部'}`
    : `📂 ${selectedSite}${selectedDate ? ' · ' + selectedDate : ' · 全部'}`

  return (
    <>
      <Link to="/" className="back">← 返回母站</Link>
      <section className="hero">
        <span className="label">后台</span>
        <h1>📰 内容管理</h1>
        <p>浏览缓存热榜 → 勾选目标 → AI 生成文章 → 发布为事件</p>
      </section>

      {/* 顶部 */}
      <div className="admin-topbar">
        <div className="admin-view-switch">
          <button className={`view-btn ${viewMode === VIEW_CALENDAR ? 'active' : ''}`}
            onClick={() => switchView(VIEW_CALENDAR)}>📅 日历视图</button>
          <button className={`view-btn ${viewMode === VIEW_CATEGORY ? 'active' : ''}`}
            onClick={() => switchView(VIEW_CATEGORY)}>📂 分类视图</button>
        </div>
        {stats && (
          <div className="admin-stats-bar">
            <span><strong>{stats.cache.total_items}</strong> 条缓存</span>
            <span><strong>{stats.cache.sites}</strong> 个站点</span>
            <span><strong>{stats.events.total}</strong> 个事件</span>
          </div>
        )}
      </div>

      <div className="admin-layout">
        {/* 左侧 */}
        <aside className="admin-sidebar">
          {viewMode === VIEW_CALENDAR ? (
            <>
              <h3>📅 选择日期</h3>
              <div className="admin-date-list">
                {cacheDates.map(d => (
                  <button key={d.date}
                    className={`admin-date-item ${calendarDate === d.date ? 'active' : ''}`}
                    onClick={() => { setCalendarDate(d.date); setPage(1) }}>
                    <span className="date-label">{d.date}</span>
                    <span className="date-count">{d.total}</span>
                  </button>
                ))}
              </div>
              <div style={{ marginTop: 16 }}>
                <label style={{ fontSize: 12, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>筛选站点</label>
                <select className="admin-filter-select"
                  value={calendarFilterSite}
                  onChange={e => { setCalendarFilterSite(e.target.value); setPage(1) }}>
                  <option value="">全部站点</option>
                  {cacheSites.map(s => (
                    <option key={s.site} value={s.site}>{s.site} ({s.total})</option>
                  ))}
                </select>
              </div>
            </>
          ) : (
            <>
              <h3>📂 分类导航</h3>
              <div className="admin-site-tree">
                {cacheSites.map(s => (
                  <button key={s.site}
                    className={`admin-site-btn ${selectedSite === s.site ? 'active' : ''}`}
                    onClick={() => handleSiteChange(s.site)}>
                    {s.site} <span className="count">({s.total})</span>
                  </button>
                ))}
              </div>
            </>
          )}
        </aside>

        {/* 中间 */}
        <div className="admin-main">
          <div className="admin-toolbar">
            <div className="admin-site-title">{viewTitle}</div>

            {/* 日历视图：内嵌日历（类似弹窗样式） */}
            {viewMode === VIEW_CALENDAR && (
              <div className="date-picker-wrap">
                <button className={`cal-toggle-btn ${showDatePicker ? 'active' : ''}`}
                  onClick={() => setShowDatePicker(v => !v)}>
                  📅 切换日期
                </button>
                {showDatePicker && (
                  <div className="date-picker-popup">
                    <div className="date-picker-inner" onClick={e => e.stopPropagation()}>
                      <MiniCalendar dates={cacheDates.map(d => d.date)} selected={calendarDate}
                        onSelect={(d) => { if (d) { setCalendarDate(d); setPage(1) } setShowDatePicker(false) }} />
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* 分类视图：日期弹窗按钮 */}
            {viewMode === VIEW_CATEGORY && selectedSite && (
              <div className="date-picker-wrap">
                <button className={`cal-toggle-btn ${showDatePicker ? 'active' : ''}`}
                  onClick={() => setShowDatePicker(v => !v)}>
                  📅 {selectedDate || '全部'}
                </button>
                {showDatePicker && (
                  <div className="date-picker-popup">
                    <div className="date-picker-inner" onClick={e => e.stopPropagation()}>
                      <MiniCalendar dates={siteDates} selected={selectedDate}
                        onSelect={(d) => { setSelectedDate(d); setShowDatePicker(false); setPage(1) }} />
                    </div>
                  </div>
                )}
              </div>
            )}

            <input className="admin-search" value={searchQ}
              onChange={e => { setSearchQ(e.target.value); setPage(1) }}
              placeholder="搜索标题..." />
          </div>

          {/* 日历视图：分类筛选按钮 */}
          {viewMode === VIEW_CALENDAR && calendarCategories.length > 0 && (
            <div className="calendar-category-bar">
              {calendarCategories.map(cat => (
                <button key={cat.site || '__all__'}
                  className={`calendar-cat-btn ${calendarCategory === cat.site ? 'active' : ''}`}
                  onClick={() => { setCalendarCategory(cat.site); setPage(1) }}>
                  {cat.site || '📋 全部'}
                  <span className="calendar-cat-count">{cat.count}</span>
                </button>
              ))}
            </div>
          )}

          {showDatePicker && (
            <div className="date-picker-overlay" onClick={() => setShowDatePicker(false)} />
          )}

          {loading ? (
            <div className="admin-loading">加载中...</div>
          ) : items.length === 0 ? (
            <div className="empty-state">
              <div className="empty-icon">📭</div>
              <h3>暂无数据</h3>
              <p>{viewMode === VIEW_CALENDAR ? '请先选择日期' : '请先选择分类'}</p>
            </div>
          ) : (
            <>
              <div className="admin-count">{total} 条热榜</div>
              <div className="admin-item-list">
                {items.map(item => (
                  <div key={item.md5}
                    className={`admin-item ${isSelected(item.md5) ? 'selected' : ''}`}
                    onClick={() => toggleSelect(item)}>
                    <div className="admin-item-check">{isSelected(item.md5) ? '☑' : '☐'}</div>
                    <div className="admin-item-content">
                      <div className="admin-item-title">{item.title}</div>
                      <div className="admin-item-meta">
                        <span className="admin-item-site">[{item.sitename}]</span>
                        <span>{item.extra || item.views}</span>
                        <a href={item.url} target="_blank" rel="noopener noreferrer"
                          className="admin-item-link" onClick={e => e.stopPropagation()}>原文 ↗</a>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
              {totalPages > 1 && (
                <div className="admin-pagination">
                  <button disabled={page <= 1} onClick={() => setPage(p => p - 1)}>← 上一页</button>
                  <span className="page-info">{page} / {totalPages}</span>
                  <button disabled={page >= totalPages} onClick={() => setPage(p => p + 1)}>下一页 →</button>
                </div>
              )}
            </>
          )}
        </div>

        {/* 右侧 */}
        <aside className="admin-panel">
          <h3>📋 已选清单</h3>
          <div className="admin-selected-count">已选 <strong>{selectedItems.length}</strong> 条</div>
          {selectedItems.length > 0 ? (
            <>
              <div className="admin-selected-list">
                {selectedItems.map(item => (
                  <div key={item.md5} className="admin-selected-item">
                    <span className="remove-btn" onClick={() => toggleSelect(item)}>×</span>
                    <div className="admin-selected-title">{item.title}</div>
                    <div className="admin-selected-source">[{item.sitename}]</div>
                  </div>
                ))}
              </div>
              <div className="admin-target-site">
                <label>发布到：</label>
                <select value={targetSite} onChange={e => setTargetSite(e.target.value)}>
                  {[{ slug: 'game', label: '🎮 游戏呀呀' }, { slug: 'ai', label: '🤖 AI呀呀' },
                    { slug: 'tech', label: '💻 互联网呀呀' }, { slug: 'star', label: '⭐ 主播呀呀' }]
                    .map(s => <option key={s.slug} value={s.slug}>{s.label}</option>)}
                </select>
              </div>
              <button className="admin-generate-btn" onClick={handleGenerate} disabled={generating}>
                {generating ? '⏳ AI 生成中...' : ' AI 生成文章'}
              </button>
            </>
          ) : (
            <div className="admin-hint">点击热榜条目，添加到已选清单</div>
          )}
        </aside>
      </div>

      {/* 预览弹窗 */}
      {preview && (
        <div className="admin-preview-overlay" onClick={() => !editMode && setPreview(null)}>
          <div className="admin-preview" onClick={e => e.stopPropagation()}>
            <div className="admin-preview-header">
              <h3>{editMode ? '✏️ 编辑文章' : '🤖 AI 生成预览'}</h3>
              <button className="admin-close-btn" onClick={() => { setPreview(null); setEditMode(false) }}>×</button>
            </div>
            <div className="admin-preview-body">
              <div className="admin-preview-field">
                <label>标题</label>
                {editMode ? (
                  <input className="admin-edit-input" value={preview.title}
                    onChange={e => setPreview({ ...preview, title: e.target.value })} />
                ) : <div className="admin-preview-value">{preview.title}</div>}
              </div>
              <div className="admin-preview-field">
                <label>摘要</label>
                {editMode ? (
                  <input className="admin-edit-input" value={preview.summary}
                    onChange={e => setPreview({ ...preview, summary: e.target.value })} />
                ) : <div className="admin-preview-value">{preview.summary}</div>}
              </div>
              <div className="admin-preview-field">
                <label>正文</label>
                {editMode ? (
                  <textarea className="admin-edit-textarea" rows={15} value={preview.body}
                    onChange={e => setPreview({ ...preview, body: e.target.value })} />
                ) : <pre className="admin-preview-text">{preview.body}</pre>}
              </div>
              <div className="admin-preview-section">
                <label>📍 时间线 ({preview.timeline?.length || 0})</label>
                {preview.timeline?.map((t, i) => (
                  <div key={i} className="admin-preview-timeline">
                    <span>{t.happened_at}</span>
                    {editMode ? (
                      <div>
                        <input className="admin-edit-input" value={t.title}
                          onChange={e => { const n = [...preview.timeline]; n[i] = { ...t, title: e.target.value }; setPreview({ ...preview, timeline: n }) }} />
                        <input className="admin-edit-input" value={t.detail}
                          onChange={e => { const n = [...preview.timeline]; n[i] = { ...t, detail: e.target.value }; setPreview({ ...preview, timeline: n }) }} />
                      </div>
                    ) : <span><strong>{t.title}</strong> — {t.detail}</span>}
                  </div>
                ))}
              </div>
              <div className="admin-preview-section">
                <label>💬 观点 ({preview.opinions?.length || 0})</label>
                {preview.opinions?.map((o, i) => (
                  <div key={i} className="admin-preview-opinion">
                    <span className="opinion-badge">{o.side}</span>
                    {editMode ? (
                      <div>
                        <input className="admin-edit-input" value={o.source}
                          onChange={e => { const n = [...preview.opinions]; n[i] = { ...o, source: e.target.value }; setPreview({ ...preview, opinions: n }) }} />
                        <input className="admin-edit-input" value={o.content}
                          onChange={e => { const n = [...preview.opinions]; n[i] = { ...o, content: e.target.value }; setPreview({ ...preview, opinions: n }) }} />
                      </div>
                    ) : <span><strong>{o.source}</strong>：{o.content}</span>}
                  </div>
                ))}
              </div>
              {preview.usage && (
                <div className="admin-preview-meta">
                  Token: {preview.usage.total_tokens} | 延迟: {preview.latency_ms}ms
                </div>
              )}
            </div>
            <div className="admin-preview-footer">
              <button className="admin-regenerate-btn" onClick={handleGenerate} disabled={generating}>
                {generating ? '⏳ 重新生成中...' : '🔄 重新生成'}
              </button>
              <button className="admin-edit-btn" onClick={() => setEditMode(!editMode)}>
                {editMode ? '👁 预览' : '✏️ 编辑'}
              </button>
              <button className="admin-publish-btn" onClick={handlePublish} disabled={publishing}>
                {publishing ? '📤 发布中...' : '✅ 发布'}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
