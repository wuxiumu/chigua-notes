import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { api } from '../api'
import { EventCard } from '../components/EventCard'

export default function SitePage() {
  const { siteSlug } = useParams()
  const [site, setSite] = useState(null)
  const [events, setEvents] = useState([])
  const [loading, setLoading] = useState(true)
  const [selectedCategory, setSelectedCategory] = useState('all')

  useEffect(() => {
    setLoading(true)
    Promise.all([
      api.site(siteSlug).then(setSite).catch(() => setSite(null)),
      api.events({ site: siteSlug }).then(setEvents).catch(() => setEvents([]))
    ]).finally(() => setLoading(false))
  }, [siteSlug])

  useEffect(() => {
    if (site) {
      document.title = `${site.name} · 51呀呀`
    }
  }, [site])

  if (!site && !loading) {
    return <div className="empty-state">
      <div className="empty-icon">❌</div>
      <h3>子站不存在</h3>
      <p>该子站尚未初始化，请返回母站选择其他子站</p>
      <Link to="/" className="btn-secondary">← 返回母站</Link>
    </div>
  }

  if (!site) return <div className="empty">加载中…</div>

  // 统计数据
  const totalEvents = events.length
  const categories = site.categories || []

  return (
    <>
      <Link to="/" className="back">← 返回母站</Link>
      <section className="hero">
        <span className="label">{site.subdomain}</span>
        <h1>{site.name}</h1>
        <p>{site.description}</p>
      </section>

      {/* 子站简介卡片 */}
      <div className="site-intro-card">
        <h3>关于 {site.name}</h3>
        <p>{site.fullDescription || site.description}</p>
        <div className="site-stats">
          <div>
            <div className="stat-number">{totalEvents}</div>
            <div className="stat-label">个事件</div>
          </div>
          <div>
            <div className="stat-number">{categories.length}</div>
            <div className="stat-label">个栏目</div>
          </div>
          <div>
            <div className="stat-number">每日更新</div>
            <div className="stat-label">持续更新</div>
          </div>
        </div>
      </div>

      {/* 核心栏目 */}
      {categories.length > 0 && (
        <>
          <div className="section-title">📚 核心栏目</div>
          <div className="grid" style={{ gridTemplateColumns: 'repeat(auto-fill,minmax(160px,1fr))' }}>
            {categories.map(c => (
              <div key={c.id} className="card category-card" onClick={() => setSelectedCategory(c.id)} style={{
                cursor: 'pointer',
                background: selectedCategory === c.id ? 'var(--melon)' : 'var(--paper)',
                color: selectedCategory === c.id ? '#fff' : 'var(--ink)',
                border: selectedCategory === c.id ? '2px solid var(--melon)' : '2px solid var(--ink)'
              }}>
                <h3 style={{ fontSize: 15 }}>{c.name}</h3>
              </div>
            ))}
          </div>
        </>
      )}

      <div className="section-title">🔥 {site.name} · 全部事件</div>
      {loading ? (
        <div className="grid">
          {[1, 2, 3, 4].map(i => (
            <div key={i} className="card skeleton">
              <div style={{ height: '20px', background: '#f0f0f0', marginBottom: '10px', borderRadius: '4px' }} />
              <div style={{ height: '60px', background: '#f0f0f0', borderRadius: '4px' }} />
            </div>
          ))}
        </div>
      ) : events.length ? (
        <div className="grid">{events.map(e => <EventCard key={e.id} e={e} />)}</div>
      ) : (
        <div className="empty-state">
          <div className="empty-icon">🌱</div>
          <h3>该子站暂无事件</h3>
          <p>这里汇聚的是最受关注的事件。<br/>一旦有新的事件发生，我们会第一时间更新。</p>
          <Link to="/admin" className="btn-secondary">🚀 去后台采集</Link>
        </div>
      )}
    </>
  )
}
