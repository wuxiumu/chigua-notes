import { Outlet, Link, useParams, useLocation } from 'react-router-dom'
import { useEffect, useState, useCallback } from 'react'
import { api } from '../api'

export default function Layout() {
  const [sites, setSites] = useState([])
  const [isAdmin, setIsAdmin] = useState(false)
  const loc = useLocation()
  useEffect(() => { api.sites().then(setSites).catch(() => {}) }, [])
  useEffect(() => {
    api.auth.status().then(res => setIsAdmin(res.logged_in)).catch(() => setIsAdmin(false))
  }, [])

  const handleLogout = useCallback(async () => {
    try { await api.auth.logout() } catch {}
    setIsAdmin(false)
    window.location.href = '/'
  }, [])

  const activeSlug = loc.pathname.startsWith('/s/') ? loc.pathname.split('/')[2] : null

  return (
    <>
      <header className="topbar">
        <div className="topbar-inner">
          <Link to="/" className="logo">51<span className="dot">·</span>呀呀</Link>
          <span className="tagline">泛娱乐呀呀母站 · 事件时间线与观点聚合</span>
          <nav className="subnav">
            <Link to="/hot" className={loc.pathname === '/hot' ? 'active' : ''}>🔥 热榜</Link>
            {sites.map(s => (
              <Link key={s.slug} to={'/s/' + s.slug}
                className={activeSlug === s.slug ? 'active' : ''}>{s.name}</Link>
            ))}
            {isAdmin ? (
              <>
                <Link to="/admin" className={loc.pathname === '/admin' ? 'active' : ''}>⚙ 后台</Link>
                <button className="subnav-logout" onClick={handleLogout}>🚪 退出</button>
              </>
            ) : (
              <Link to="/login" className="ghost">🔐 后台</Link>
            )}
          </nav>
        </div>
      </header>
      <main className="wrap"><Outlet /></main>
      <footer>
        51呀呀 © {new Date().getFullYear()} · 内容仅供参考，中立整理 · 母站架构 demo
      </footer>
    </>
  )
}
