import { useState, useEffect, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../api'

export default function Login() {
  const navigate = useNavigate()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [captchaAnswer, setCaptchaAnswer] = useState('')
  const [captcha, setCaptcha] = useState(null) // { question, token, sig }
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [locked, setLocked] = useState(false)
  const [lockRemaining, setLockRemaining] = useState(0)
  const [attempts, setAttempts] = useState(0)

  useEffect(() => {
    document.title = '管理员登录 · 51呀呀'
  }, [])

  const loadCaptcha = useCallback(() => {
    api.auth.captcha()
      .then(res => {
        if (res.locked) {
          setLocked(true)
          setLockRemaining(res.remaining)
          return
        }
        setCaptcha(res)
        setCaptchaAnswer('')
        setError('')
      })
      .catch(() => {})
  }, [])

  useEffect(() => {
    loadCaptcha()
  }, [loadCaptcha])

  // 倒计时解锁
  useEffect(() => {
    if (!locked || lockRemaining <= 0) return
    const timer = setInterval(() => {
      setLockRemaining(prev => {
        if (prev <= 1) {
          setLocked(false)
          loadCaptcha()
          return 0
        }
        return prev - 1
      })
    }, 1000)
    return () => clearInterval(timer)
  }, [locked, lockRemaining, loadCaptcha])

  // 已登录直接跳转
  useEffect(() => {
    api.auth.status().then(res => {
      if (res.logged_in) navigate('/admin', { replace: true })
    }).catch(() => {})
  }, [navigate])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')

    if (locked) return
    if (!username || !password || !captchaAnswer) {
      setError('请填写完整')
      return
    }

    setLoading(true)
    try {
      const res = await api.auth.login(username, password, parseInt(captchaAnswer), captcha.token, captcha.sig)
      if (res.ok) {
        navigate('/admin', { replace: true })
      }
    } catch (err) {
      try {
        const data = await err.response?.json?.()
        if (data?.locked) {
          setLocked(true)
          setLockRemaining(data.remaining)
          setError('IP 已被锁定，请 ' + Math.ceil(data.remaining / 60) + ' 分钟后再试')
        } else {
          setError(data?.error || '登录失败')
          setAttempts(prev => prev + 1)
          // 刷新验证码
          loadCaptcha()
        }
      } catch {
        setError('网络错误，请稍后重试')
      }
    } finally {
      setLoading(false)
    }
  }

  const formatTime = (s) => {
    const m = Math.floor(s / 60)
    const sec = s % 60
    return `${m}:${String(sec).padStart(2, '0')}`
  }

  return (
    <div className="login-wrap">
      <div className="login-card">
        <div className="login-header">
          <div className="login-logo kuaile">51呀呀<span className="dot">.</span></div>
          <h1>🔐 管理员登录</h1>
          <p>内容管理后台，需要管理员凭据</p>
        </div>

        {locked ? (
          <div className="login-locked">
            <div className="lock-icon">🔒</div>
            <h3>访问已被锁定</h3>
            <p>登录失败次数过多，请在 {formatTime(lockRemaining)} 后重试</p>
            <div className="lock-timer">{formatTime(lockRemaining)}</div>
          </div>
        ) : (
          <form className="login-form" onSubmit={handleSubmit}>
            <div className="login-field">
              <label>用户名</label>
              <input type="text" value={username}
                onChange={e => setUsername(e.target.value)}
                placeholder="admin" autoComplete="username" />
            </div>

            <div className="login-field">
              <label>密码</label>
              <input type="password" value={password}
                onChange={e => setPassword(e.target.value)}
                placeholder="••••••••" autoComplete="current-password" />
            </div>

            <div className="login-field">
              <label>验证码</label>
              <div className="captcha-row">
                <span className="captcha-question">{captcha?.question || '加载中...'}</span>
                <input type="text" inputMode="numeric" pattern="[0-9]*" value={captchaAnswer}
                  onChange={e => setCaptchaAnswer(e.target.value.replace(/\D/g, ''))}
                  placeholder="?" className="captcha-input" autoComplete="off" />
              </div>
              <span className="captcha-hint">每分钟自动刷新</span>
            </div>

            {error && <div className="login-error">❌ {error}</div>}
            {attempts > 0 && !error && (
              <div className="login-hint">已连续失败 {attempts} 次，5 次后将锁定 IP</div>
            )}

            <button type="submit" className="login-btn" disabled={loading}>
              {loading ? '登录中...' : '登 录'}
            </button>
          </form>
        )}

        <div className="login-footer">
          <a href="/" className="login-back-link">← 返回首页</a>
        </div>
      </div>
    </div>
  )
}
