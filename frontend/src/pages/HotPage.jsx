import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'
import { STATUS, TYPE } from '../components/EventCard'

// 热度趋势标记
function TrendIcon({ views, rank, prevRank }) {
  if (prevRank && rank < prevRank) return <span className="trend-up" title="热度上升">🔥 上升</span>
  if (prevRank && rank > prevRank) return <span className="trend-down" title="热度下降">📉 下降</span>
  return <span className="trend-stable" title="热度持平">⏳ 持平</span>
}

// 排名徽章
function RankBadge({ rank }) {
  const colors = { 1: '#e8453c', 2: '#d9a441', 3: '#2f7d3a' }
  const color = colors[rank] || 'var(--muted)'
  return (
    <span className="rank-badge" style={{ background: color, color: rank <= 3 ? '#fff' : 'var(--muted)' }}>
      {rank}
    </span>
  )
}

export default function HotPage() {
  const [events, setEvents] = useState([])
  const [loading, setLoading] = useState(true)
  const [filter, setFilter] = useState('all')
  const [timeRange, setTimeRange] = useState('24h')

  useEffect(() => {
    document.title = '🔥 热榜 · 51呀呀'
  }, [])

  useEffect(() => {
    setLoading(true)
    api.events({})
      .then(all => {
        // 按浏览量排序
        const sorted = [...all].sort((a, b) => b.views - a.views)
        setEvents(sorted)
      })
      .catch(err => {
        console.error('加载热榜失败:', err)
        setEvents([])
      })
      .finally(() => setLoading(false))
  }, [])

  // 过滤
  const filtered = filter === 'all'
    ? events
    : events.filter(e => e.status === filter)

  return (
    <>
      <section className="hero">
        <span className="label">热榜</span>
        <h1>🔥 全网热瓜榜</h1>
        <p>按浏览量实时排序，看看大家都在关注什么事件。<br/>
           数据每 10 分钟更新一次，反映当前最受关注的事件。</p>
      </section>

      {/* 过滤栏 */}
      <div className="hot-filters">
        <div className="filter-group">
          <span className="filter-label">状态：</span>
          {[
            { key: 'all', label: '全部' },
            { key: 'fermenting', label: '🔴 发酵中' },
            { key: 'responded', label: '🟡 已回应' },
            { key: 'ended', label: '⚪ 已结束' },
          ].map(f => (
            <button
              key={f.key}
              className={`filter-btn ${filter === f.key ? 'active' : ''}`}
              onClick={() => setFilter(f.key)}
            >
              {f.label}
            </button>
          ))}
        </div>
      </div>

      {/* 热榜列表 */}
      {loading ? (
        <div className="hot-list">
          {[1, 2, 3, 4, 5].map(i => (
            <div key={i} className="hot-item skeleton">
              <div style={{ width: '40px', height: '40px', background: '#f0f0f0', borderRadius: '50%' }} />
              <div style={{ flex: 1 }}>
                <div style={{ height: '18px', width: '60%', background: '#f0f0f0', marginBottom: '8px' }} />
                <div style={{ height: '14px', width: '40%', background: '#f0f0f0' }} />
              </div>
            </div>
          ))}
        </div>
      ) : filtered.length ? (
        <div className="hot-list">
          {filtered.map((e, idx) => (
            <Link to={'/e/' + e.slug} className="hot-item" key={e.id}>
              <div className="hot-rank">
                <RankBadge rank={idx + 1} />
              </div>
              <div className="hot-content">
                <div className="hot-header">
                  <h3 className="hot-title">{e.title}</h3>
                  <span className="badge site" style={{ flexShrink: 0 }}>{e.site_name}</span>
                </div>
                <p className="hot-summary">{e.summary}</p>
                <div className="hot-footer">
                  <span className={'badge ' + e.status}>{STATUS[e.status]}</span>
                  <span className="badge type">{TYPE[e.content_type]}</span>
                  <span className="hot-stat">👁 {e.views.toLocaleString()}</span>
                  <span className="hot-stat">⏰ {(e.first_seen || '').slice(0, 10)}</span>
                </div>
              </div>
              <div className="hot-arrow">→</div>
            </Link>
          ))}
        </div>
      ) : (
        <div className="empty-state">
          <div className="empty-icon">📭</div>
          <h3>暂无符合条件的事件</h3>
          <p>试试其他筛选条件，或浏览全部事件</p>
          <button className="btn-secondary" onClick={() => setFilter('all')}>查看全部</button>
        </div>
      )}
    </>
  )
}
