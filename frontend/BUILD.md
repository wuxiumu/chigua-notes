# 前端线上打包说明

## 项目概况

- **框架**: React 18 + Vite 5
- **入口**: `src/` 目录
- **打包命令**: `npm run build`（执行 `vite build`）
- **产物目录**: `dist/`

## 打包命令

```bash
cd frontend
npm run build        # 生产构建，输出到 dist/
npm run preview      # 本地预览构建产物
```

## 环境配置

打包时使用的生产环境变量定义在 `.env.production` 中：

- **`VITE_API_BASE`** — 后端 API 地址，当前设为 `https://note-api.51chigua.com`
- 如果前后端部署在同一域名下，可改为相对路径 `/api`（无域名前缀）

开发环境 `.env.development` 中该变量留空，由 Vite dev server 的 proxy 将 `/api` 代理到 `http://localhost:8000`。

## 构建产物

`vite build` 会将 React 代码打包为：

- `dist/index.html` — 入口 HTML
- `dist/assets/` — 压缩后的 JS/CSS 文件（含内容哈希，支持长期缓存）

产物可直接用 Nginx 等静态服务器托管，注意将非静态资源请求全部回退到 `index.html`（SPA 路由支持）。
