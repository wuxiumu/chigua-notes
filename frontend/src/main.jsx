import React, { useState, useEffect } from 'react'
import ReactDOM from 'react-dom/client'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import './styles.css'
import Layout from './components/Layout.jsx'
import Home from './pages/Home.jsx'
import SitePage from './pages/SitePage.jsx'
import EventDetail from './pages/EventDetail.jsx'
import HotPage from './pages/HotPage.jsx'
import Admin from './pages/Admin.jsx'
import Login from './pages/Login.jsx'
import { api } from './api'

function AdminGuard() {
  const [checking, setChecking] = useState(true)
  const [loggedIn, setLoggedIn] = useState(false)

  useEffect(() => {
    api.auth.status()
      .then(res => setLoggedIn(res.logged_in))
      .catch(() => setLoggedIn(false))
      .finally(() => setChecking(false))
  }, [])

  if (checking) return <div className="auth-checking">验证中...</div>
  if (!loggedIn) return <Navigate to="/login" replace />
  return <Admin />
}

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route element={<Layout />}>
          <Route path="/" element={<Home />} />
          <Route path="/s/:siteSlug" element={<SitePage />} />
          <Route path="/e/:eventSlug" element={<EventDetail />} />
          <Route path="/hot" element={<HotPage />} />
          <Route path="/admin" element={<AdminGuard />} />
        </Route>
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  </React.StrictMode>
)
