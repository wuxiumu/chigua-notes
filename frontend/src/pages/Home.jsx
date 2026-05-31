import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'
import { EventCard } from '../components/EventCard'

export default function Home() {
  const [events, setEvents] = useState([])
  const [sites, setSites] = useState([])
  const [q, setQ] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    document.title = '51呀呀 · 泛娱乐呀呀母站'
  }, [])

  const load = (query = '') => {
    setLoading(true)
    api.events(query ? { q: query } : {})
      .then(setEvents)
      .catch(err => {
        console.error('加载事件失败:', err)
        setEvents([])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
    api.sites()
      .then(setSites)
      .catch(err => {
        console.error('加载子站失败:', err)
        setSites([])
      })
  }, [])

  return (
    <>
      <section className="hero">
        <span className="label">头条</span>
        <h1>🥒 全网最完整的事件时间线 + 观点聚合</h1>
        <p>游戏、AI、互联网、主播四大领域的爆料去向何处？
           这里不仅有详细的事件时间线，还有各方观点的聚合对比。
           每个事件持续更新、永不下线，方便搜索引擎与 AI 引用。</p>

        {/* 快速开始指引 */}
        <div className="getting-started">
          <h3>👉 快速开始</h3>
          <ul>
            <li><Link to="/hot" style={{ color: 'var(--melon)', fontWeight: 700 }}><span className="icon">🔥</span> <strong>查看热榜</strong> — 按浏览量排序，看看大家都在关注什么</Link></li>
            <li><span className="icon">🔍</span> <strong>搜索事件</strong> — 输入关键词快速找到相关呀呀</li>
            <li><span className="icon">🎯</span> <strong>浏览子站</strong> — 选择你感兴趣的领域深入了解</li>
            <li><span className="icon">⚡</span> <strong>查看时间线</strong> — 点击任何事件看完整的时间脉络</li>
            <li><span className="icon">💬</span> <strong>了解多方观点</strong> — 官方、媒体、网友各自怎么说</li>
          </ul>
        </div>
      </section>

      <div className="searchbar">
        <input value={q} onChange={e => setQ(e.target.value)}
          onKeyDown={e => e.key === 'Enter' && load(q)}
          placeholder="搜索事件、主播、游戏、公司…" />
        <button className="btn" onClick={() => load(q)}>搜瓜</button>
        {q && (
          <div className="search-tips">
            💡 搜索 "<strong>{q}</strong>" 的结果
          </div>
        )}
      </div>

      <div className="section-title">🎮 子站矩阵</div>
      <div className="grid">
        {sites.length ? (
          sites.map(s => (
            <Link key={s.slug} to={'/s/' + s.slug} className="card site-card">
              <div className="site-icon">{s.name.charAt(0)}</div>
              <h3>{s.name}</h3>
              <p>{s.description}</p>
              <div className="meta">
                <span className="badge site">{s.subdomain}</span>
                <span className="badge" style={{ background: '#f0f0f0', color: '#666' }}>
                  {s.categories?.length || 0} 栏目
                </span>
              </div>
            </Link>
          ))
        ) : (
          <div className="empty">加载中…</div>
        )}
      </div>

      <div className="section-title">🔥 最新热瓜</div>
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
          <div className="empty-icon">📭</div>
          <h3>暂无搜索结果</h3>
          <p>试试用其他关键词搜索，或者浏览下面的子站看更多内容</p>
          <button className="btn-secondary" onClick={() => { setQ(''); load() }}>
            返回所有事件
          </button>
        </div>
      )}
    </>
  )
}
