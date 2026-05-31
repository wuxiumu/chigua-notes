import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      // 开发时把 /api 转发到 PHP 后端
      '/api': 'http://localhost:8000'
    }
  },
  build: {
    outDir: '../dist' // 输出到 frontend 同级的 dist，不再嵌套
  }
})
