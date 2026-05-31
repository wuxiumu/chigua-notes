import { Link } from 'react-router-dom'

export const STATUS = {
  fermenting: '发酵中',
  responded:  '已回应',
  ended:      '已结束',
}
export const TYPE = {
  news:     '快讯',
  analysis: '解读',
  wiki:     '百科',
}

export function EventCard({ e }) {
  return (
    <Link to={'/e/' + e.slug} className="card">
      <div className="meta">
        <span className="badge site">{e.site_name}</span>
        <span className={'badge ' + e.status}>{STATUS[e.status] || e.status}</span>
        <span className="badge type">{TYPE[e.content_type] || e.content_type}</span>
      </div>
      <h3>{e.title}</h3>
      <p>{e.summary}</p>
      <div className="meta" style={{ color: 'var(--muted)' }}>
        <span>👁 {e.views}</span><span>·</span><span>{(e.updated_at || '').slice(0, 16)}</span>
      </div>
    </Link>
  )
}
