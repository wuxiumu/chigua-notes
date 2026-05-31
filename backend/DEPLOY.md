# 51呀呀 后端部署文档

## 项目概况

- **技术栈**: PHP 8.1+ / SQLite / 无框架
- **架构**: 单文件 PHP 路由入口 + Markdown 事件文件
- **数据源**: SQLite（子站/栏目） + Markdown 文件（事件详情）
- **AI 集成**: 通义千问 DashScope API（通过 OpenAI 兼容接口）
- **爬虫**: tophub 热榜采集（CLI 脚本 + cron）

---

## 一、服务器要求

| 依赖 | 版本 | 说明 |
|------|------|------|
| PHP | 8.1+ | 使用了 `str_starts_with` 等新函数 |
| PHP-SQLite3 | 同 PHP 版本 | 通常随 PHP 安装 |
| PHP-PDO | 同 PHP 版本 | SQLite 驱动 |
| Nginx | 任意稳定版 | 或 Apache，本文以 Nginx 为例 |
| Composer | 2.x | 仅需安装一次 |
| Git | — | 用于拉取代码 |

> **不需要 MySQL、Redis、Node.js**。

---

## 二、部署步骤

### 2.1 安装系统依赖

```bash
# Ubuntu / Debian
sudo apt update
sudo apt install -y php8.1 php8.1-fpm php8.1-sqlite3 php8.1-mbstring \
                     php8.1-curl php8.1-xml nginx git unzip curl

# CentOS / RHEL (使用 remi 源)
sudo yum install -y epel-release
sudo yum install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm
sudo dnf module install -y php:remi-8.1
sudo dnf install -y php-fpm php-sqlite3 php-mbstring php-curl php-xml \
                     nginx git unzip curl
```

验证：
```bash
php -v            # 确认 8.1+
php -m | grep sqlite3   # 确认有 sqlite3
```

### 2.2 拉取代码

```bash
sudo mkdir -p /var/www
cd /var/www
git clone <your-repo-url> chigua-backend
cd chigua-backend
```

### 2.3 安装 PHP 依赖

```bash
cd /var/www/chigua-backend
composer install --no-dev
```

### 2.4 配置环境变量

```bash
# 复制 .env.example 为 .env（如已有 .env 直接使用）
nano /var/www/chigua-backend/.env
```

`.env` 内容：
```ini
# ============ 通义千问 (DashScope) ============
DASHSCOPE_API_KEY=sk-xxxxxxxxxxxxxxxxxxxxxxxx
DASHSCOPE_MODEL=qwen-plus
DASHSCOPE_ENABLED=true

# ============ 管理后台账号 ============
ADMIN_USERNAME=your_username
ADMIN_PASSWORD=your_strong_password
```

> ⚠️ `.env` 文件**不会被 git 提交**，必须手动配置。

### 2.5 初始化数据库与目录

```bash
cd /var/www/chigua-backend

# 创建必要目录
mkdir -p data/events data/cache

# 如果 SQLite 数据库不存在，用 schema.sql 初始化
if [ ! -f data/chigua.sqlite ]; then
    sqlite3 data/chigua.sqlite < scripts/schema.sql
fi

# 初始化基础子站数据（首次部署）
php scripts/init.php

# 设置权限（www-data 是 Nginx/PHP-FPM 运行用户）
sudo chown -R www-data:www-data data/
sudo chmod 755 data/ data/events/ data/cache/
sudo chmod 644 data/chigua.sqlite
```

### 2.6 配置 Nginx

```bash
sudo nano /etc/nginx/sites-available/chigua
```

写入：
```nginx
server {
    listen 80;
    server_name your-domain.com;  # 替换为你的域名

    # 根目录指向 public/
    root /var/www/chigua-backend/public;
    index index.php;

    # API 路由
    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;  # CentOS 可能为 127.0.0.1:9000
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 禁止直接访问敏感目录
    location ~ ^/(data|config|src|scripts|scrapers|vendor|bootstrap\.php|\.env) {
        deny all;
        return 404;
    }

    # 日志
    access_log /var/log/nginx/chigua-access.log;
    error_log  /var/log/nginx/chigua-error.log;
}
```

启用站点：
```bash
sudo ln -s /etc/nginx/sites-available/chigua /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 2.7 配置 HTTPS（推荐）

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

---

## 三、验证部署

```bash
# 测试 API 是否正常
curl https://your-domain.com/api/sites
# 预期: [{"id":1,"slug":"game","name":"游戏呀呀"}, ...]

# 测试 AI 健康
curl https://your-domain.com/api/ai/health
# 预期: {"ok": true, ...}

# 测试事件列表
curl https://your-domain.com/api/events
# 预期: 事件数组
```

---

## 四、定时任务（热榜爬虫）

```bash
sudo crontab -e
```

添加（每 4 小时采集一次）：
```cron
0 */4 * * * cd /var/www/chigua-backend && /usr/bin/php scrapers/tophub-scraper.php >> /var/log/chigua-scraper.log 2>&1
```

查看采集日志：
```bash
tail -f /var/log/chigua-scraper.log
```

手动测试爬虫：
```bash
php scrapers/tophub-scraper.php --stats
```

---

## 五、目录结构

```
backend/
├── bootstrap.php          # 启动引导（加载 .env + autoload）
├── public/
│   └── index.php          # 唯一入口（所有 API 路由）
├── src/
│   ├── DB.php             # SQLite 封装
│   ├── AiClient.php       # 通义千问 API 客户端
│   ├── AiSummarizer.php   # AI 摘要生成（mock / 真实）
│   ├── MarkdownEventReader.php  # Markdown 事件读取器
│   ├── EventPublisher.php # Markdown 事件发布器
│   ├── CacheReader.php    # 热榜缓存读取器
│   └── Auth.php           # 认证（登录/验证码/锁）
├── config/
│   ├── config.php         # 基础配置
│   └── ai.php             # AI 配置
├── data/
│   ├── chigua.sqlite      # SQLite 数据库
│   ├── events/            # Markdown 事件文件（*.md）
│   └── cache/             # 热榜缓存 JSON
├── scrapers/
│   └── tophub-scraper.php # 热榜爬虫
├── scripts/
│   ├── init.php           # 数据库初始化脚本
│   └── schema.sql         # 数据库建表 SQL
└── .env                   # 环境变量（不提交 git）
```

---

## 六、数据源说明

| 数据 | 存储位置 | 读写接口 |
|------|----------|----------|
| 子站/栏目 | SQLite `sites` / `categories` 表 | `GET /api/sites` |
| 事件详情 | `data/events/*.md` 文件 | `GET /api/events` |
| 热榜缓存 | `data/cache/tophub/` JSON 文件 | `GET /api/admin/cache` |
| 原始采集 | SQLite `raw_feeds` 表 | `POST /api/feeds` |
| 用户/会话 | SQLite + 文件锁 | `/api/auth/*` |

---

## 七、常见问题

### Q: CORS 报错，前端无法调 API
A: 编辑 `config/config.php`，把 `cors_origin` 改为前端域名：
```php
'cors_origin' => 'https://frontend-domain.com',
```

### Q: AI 调用失败
A: 检查 `.env` 中的 `DASHSCOPE_API_KEY` 是否正确，然后访问 `/api/ai/health` 排查。

### Q: 数据库文件权限错误
A: `sudo chown www-data:www-data data/chigua.sqlite && sudo chmod 664 data/chigua.sqlite`

### Q: Nginx 报 502 Bad Gateway
A: 检查 PHP-FPM 是否在运行：`sudo systemctl status php8.1-fpm`

### Q: 爬虫跑不动
A: 检查网络连通性 `curl -I https://api.meiyoufan.com`，部分服务器可能需要代理。

---

## 八、备份建议

定期备份以下文件：
```bash
# 创建备份脚本
cat > /var/www/chigua-backend/backup.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/var/backups/chigua/$(date +%Y%m%d)"
mkdir -p "$BACKUP_DIR"
cp data/chigua.sqlite "$BACKUP_DIR/"
cp -r data/events "$BACKUP_DIR/"
cp -r data/cache "$BACKUP_DIR/"
cp .env "$BACKUP_DIR/"
find "$BACKUP_DIR" -type f -name "*.json" -mtime +7 -delete  # 清理 7 天前缓存
echo "Backup done: $BACKUP_DIR"
EOF
chmod +x backup.sh

# 加入 crontab，每天凌晨 3 点备份
echo "0 3 * * * /var/www/chigua-backend/backup.sh" | sudo crontab -
```
