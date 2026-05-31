// 统一 API 客户端
//
// 数据源说明：
// - 站点/栏目数据：优先走真实 API（SQLite），本地 mock 作为降级
// - 事件数据：全部走真实 API（PHP 解析 Markdown 文件）
// - 管理后台：全部走真实 API（缓存读取 + AI 生成 + 发布）
// - VITE_USE_MOCK=true 时，站点数据使用本地 mock，事件/管理走后端
//
// 环境变量：
//   VITE_USE_MOCK=true   站点用 mock，事件/管理走后端
//   VITE_USE_MOCK=false  全部走后端

import { getSites, getSiteBySlug } from '../mock.js'

const BASE = import.meta.env.VITE_API_BASE || '/api'
const USE_MOCK = import.meta.env.VITE_USE_MOCK !== 'false'

console.log(`🚀 API 模式: ${USE_MOCK ? '📱 站点Mock + 事件/管理后端' : '🔌 全部真实API'}`)

// ====== HTTP 工具 ======
async function get(path) {
  const r = await fetch(BASE + path, {
    credentials: 'include', // 携带 Cookie（登录态）
  })
  if (!r.ok) throw new Error('请求失败 ' + r.status)
  return r.json()
}

async function post(path, body) {
  const r = await fetch(BASE + path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include', // 携带 Cookie
    body: JSON.stringify(body || {})
  })
  if (!r.ok) throw new Error('请求失败 ' + r.status)
  return r.json()
}

// ====== 事件 API（始终走后端 — Markdown 数据源） ======
const eventApi = {
  events: (params = {}) => get('/events?' + new URLSearchParams(params)),
  event: (slug) => get('/events/' + slug),
}

// ====== 管理后台 API（始终走后端） ======
const adminApi = {
  // 获取有缓存的站点列表
  cacheSites: () => get('/admin/cache-sites'),

  // 获取所有有缓存的日期
  cacheDates: () => get('/admin/cache-dates'),

  // 获取缓存热榜列表
  // site 可选：不传 = 日历视图（该日期所有站点），传 = 分类视图（单站点）
  cache: (date, site = '', q = '', page = 1) => {
    const params = new URLSearchParams({ date, q, page: String(page) })
    if (site) params.set('site', site)
    return get(`/admin/cache?${params}`)
  },

  // AI 生成文章（预览）
  generate: (items, siteSlug, customPrompt = '') =>
    post('/admin/generate', { items, site_slug: siteSlug, custom_prompt: customPrompt }),

  // 确认发布
  publish: (data) => post('/admin/publish', data),

  // 缓存统计
  stats: () => get('/admin/stats'),

  // 获取指定日期各站点的缓存数量（日历视图分类统计）
  cacheDateCategories: (date) => {
    if (!date) return Promise.resolve([])
    return get('/admin/cache-date-categories?' + new URLSearchParams({ date }))
  },

  // 日历视图：按日期获取缓存热榜（site 可选，不传 = 所有站点）
  cacheByDate: (date, site = '', q = '', page = 1) => {
    const params = new URLSearchParams({ date, q, page: String(page) })
    if (site) params.set('site', site)
    return get(`/admin/cache-by-date?${params}`)
  },
}

// ====== Mock 实现（站点/栏目） ======
const mockApi = {
  sites: () => Promise.resolve(getSites()),
  site: (slug) => {
    const site = getSiteBySlug(slug)
    if (!site) throw new Error('子站不存在')
    return Promise.resolve(site)
  },
}

// ====== 真实 API ======
const realApi = {
  sites: () => get('/sites'),
  site: (slug) => get('/sites/' + slug),
}

// ====== 导出 ======
const siteApi = USE_MOCK ? mockApi : realApi

// ====== 认证 API（始终走后端） ======
const authApi = {
  // 获取验证码（带防锁定检查）
  captcha: () => get('/auth/captcha'),

  // 登录
  login: async (username, password, captchaAnswer, captchaToken, captchaSig) => {
    const r = await fetch(BASE + '/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        username, password,
        captcha_answer: captchaAnswer,
        captcha_token: captchaToken,
        captcha_sig: captchaSig,
      }),
      credentials: 'include', // 发送/接收 Cookie
    })
    const data = await r.json()
    if (!r.ok) {
      const err = new Error(data.error || '登录失败')
      err.response = r
      err.status = r.status
      throw err
    }
    return data
  },

  // 登出
  logout: async () => {
    const r = await fetch(BASE + '/auth/logout', {
      method: 'POST',
      credentials: 'include',
    })
    return r.json()
  },

  // 检查登录状态
  status: () => get('/auth/status'),
}

export const api = {
  ...siteApi,
  ...eventApi,
  admin: adminApi,
  auth: authApi,

  // 采集/生成始终走后端
  addFeed: (data) => post('/feeds', data),
  generate: (data) => post('/generate', data),
}
