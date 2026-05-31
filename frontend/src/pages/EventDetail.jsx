import { useEffect, useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { api } from '../api'
import { EventCard, STATUS, TYPE } from '../components/EventCard'

// 极简 markdown 渲染（## 标题 + 段落），够用即可
function renderBody(md = '') {
  return md.split('\n').filter(Boolean).map((line, i) => {
    if (line.startsWith('## ')) return <h2 key={i}>{line.slice(3)}</h2>
    return <p key={i}>{line}</p>
  })
}

// 快速了解卡片
function QuickSummary({ e }) {
  const summaryPoints = e.summary?.split('。').filter(Boolean).slice(0, 3) || []

  return (
    <div className="aside-box" style={{ marginBottom: 20 }}>
      <h4>📋 快速了解</h4>
      <ul style={{ listStyle: 'none', fontSize: 13, lineHeight: 1.6, color: '#3a3026' }}>
        {summaryPoints.map((point, i) => (
          <li key={i} style={{ paddingBottom: 8 }}>
            <span style={{ color: 'var(--melon)', fontWeight: 700 }}>•</span> {point}。
          </li>
        ))}
        <li style={{ marginTop: 12, paddingTop: 12, borderTop: '1px dashed var(--line)', color: 'var(--muted)', fontSize: 12 }}>
          ⏰ 首次出现 {(e.first_seen || '').slice(0, 10)}
        </li>
      </ul>
    </div>
  )
}

// 分享按钮
function ShareBar({ slug }) {
  const [copied, setCopied] = useState(false)
  const url = window.location.origin + '/e/' + slug

  const handleCopy = () => {
    navigator.clipboard?.writeText(url).then(() => {
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    })
  }

  const handleWeibo = () => {
    window.open(`https://service.weibo.com/share/share.php?url=${encodeURIComponent(url)}`, '_blank')
  }

  return (
    <div className="share-bar">
      <button className="share-btn" onClick={handleCopy}>
        {copied ? '✅ 已复制' : '🔗 复制链接'}
      </button>
      <button className="share-btn" onClick={handleWeibo}>
        📢 分享到微博
      </button>
    </div>
  )
}

// 相关事件
function RelatedEvents({ currentEvent }) {
  const [related, setRelated] = useState([])

  useEffect(() => {
    api.events({ site: currentEvent.site_id ? undefined : undefined })
      .then(events => {
        const filtered = events
          .filter(e => e.slug !== currentEvent.slug)
          .slice(0, 3)
        setRelated(filtered)
      })
      .catch(() => setRelated([]))
  }, [currentEvent.slug])

  if (!related.length) return null

  return (
    <div className="aside-box">
      <h4>📎 相关事件</h4>
      {related.map(e => (
        <div className="related-event" key={e.id}>
          <Link to={'/e/' + e.slug} className="related-title">{e.title}</Link>
          <div className="related-meta">
            <span className={'badge ' + e.status}>{STATUS[e.status]}</span>
            <span className="related-views">👁 {e.views}</span>
          </div>
        </div>
      ))}
    </div>
  )
}

export default function EventDetail() {
  const { eventSlug } = useParams()
  const navigate = useNavigate()
  const [e, setE] = useState(null)
  const [err, setErr] = useState(false)

  useEffect(() => {
    setE(null); setErr(false)
    api.event(eventSlug).then(setE).catch(() => setErr(true))
  }, [eventSlug])

  if (err) return (
    <div className="empty-state">
      <div className="empty-icon">❌</div>
      <h3>事件不存在</h3>
      <p>该事件可能已被删除或 URL 有误</p>
      <button className="btn-secondary" onClick={() => navigate('/')}>← 返回母站</button>
    </div>
  )
  if (!e) return <div className="empty">加载中…</div>

  const sideLabel = { player: '玩家', official: '官方', media: '媒体' }
  const sideColor = { player: 'var(--rind)', official: 'var(--melon)', media: 'var(--gold)' }

  return (
    <>
      <Link to="/" className="back">← 返回</Link>
      <div className="detail-head">
        <div className="sub">
          <span className="badge site">{e.site_name}</span>
          <span className={'badge ' + e.status}>{STATUS[e.status]}</span>
          <span className="badge type">{TYPE[e.content_type]}</span>
        </div>
        <h1>{e.title}</h1>
        <div className="sub">
          <span>首次出现 {(e.first_seen || '').slice(0, 16)}</span>
          <span>· 更新 {(e.updated_at || '').slice(0, 16)}</span>
          <span>· 👁 {e.views.toLocaleString()}</span>
        </div>
      </div>

      <div className="detail-layout">
        <article>
          <p className="event-summary">{e.summary}</p>

          {e.timeline?.length > 0 && (
            <>
              <h2 className="section-heading">📍 事件时间线</h2>
              <ul className="timeline">
                {e.timeline.map(t => (
                  <li key={t.id}>
                    <div className="t-time">{t.happened_at}</div>
                    <div className="t-title">{t.title}</div>
                    {t.detail && <div className="t-detail">{t.detail}</div>}
                  </li>
                ))}
              </ul>
            </>
          )}

          <div className="body-md" style={{ marginTop: 24 }}>{renderBody(e.body)}</div>
        </article>

        <aside>
          <QuickSummary e={e} />

          <div className="aside-box">
            <h4>💬 各方观点</h4>
            {e.opinions?.length ? e.opinions.map(o => (
              <div className="opinion" key={o.id}>
                <div className="opinion-header">
                  <span className="opinion-side" style={{ color: sideColor[o.side] || '#666' }}>
                    {sideLabel[o.side] || o.side}
                  </span>
                  <span className="opinion-source">· {o.source}</span>
                </div>
                <div className="opinion-content">{o.content}</div>
              </div>
            )) : <div className="src">暂无</div>}
          </div>

          {e.persons?.length > 0 && (
            <div className="aside-box">
              <h4>👥 相关人物</h4>
              <div className="persons-grid">
                {e.persons.map(p => (
                  <div className="person-card" key={p.id}>
                    <div className="person-avatar">{p.name.charAt(0)}</div>
                    <div className="person-name">{p.name}</div>
                  </div>
                ))}
              </div>
            </div>
          )}

          <ShareBar slug={e.slug} />
          <RelatedEvents currentEvent={e} />

          <div className="aside-box" style={{ background: '#f9f7f4', borderTop: '3px solid var(--melon)' }}>
            <h4 style={{ fontSize: 13, color: '#666', margin: 0 }}>💡 了解更多</h4>
            <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 8 }}>
              <p>在本站查阅该事件的完整时间线和多方观点。</p>
              <Link to="/" style={{ color: 'var(--melon)', fontWeight: 700, fontSize: 12 }}>← 返回所有事件</Link>
            </div>
          </div>
        </aside>
      </div>
    </>
  )
}
