# 生产 HTTPS 部署指南（D-02）

> 目标：TLS 证书、Sanctum Cookie、Reverb WebSocket (wss) 在生产环境可用；iOS Safari 可访问 IM / WebRTC。

---

## 1. 环境变量清单

复制 `.env.production.example` 到服务器 `.env`，或使用脚本：

```powershell
# Windows 开发机写入 .env（部署前）
powershell -ExecutionPolicy Bypass -File scripts/apply-production-https.ps1 -Domain 88.huwutong.com
```

| 变量 | 生产值示例 | 说明 |
|------|-----------|------|
| `APP_URL` | `https://88.huwutong.com` | 必须 https |
| `FORCE_HTTPS` | `true` | 生成 URL 强制 HTTPS |
| `TRUSTED_PROXIES` | `*` | Nginx 反代后识别 X-Forwarded-Proto |
| `SANCTUM_STATEFUL_DOMAINS` | `88.huwutong.com,www.huwutong.com,...` | **无协议**，逗号分隔 |
| `SESSION_SECURE_COOKIE` | `true` | HTTPS Cookie |
| `SESSION_DOMAIN` | `.huwutong.com` | 多子域共享（可选） |
| `REVERB_HOST` | `88.huwutong.com` | 与公网域名一致 |
| `REVERB_PORT` | `443` | Nginx 终止 TLS |
| `REVERB_SCHEME` | `https` | |
| `VITE_REVERB_*` | 同上 | **修改后必须** `npm run build` |

验证：

```bash
php scripts/verify-https-config.php
php artisan config:cache
```

---

## 2. Nginx + 证书

### 2.1 配置文件

使用 `deploy/nginx/production-https.conf`，替换域名与证书路径后启用：

```bash
sudo cp deploy/nginx/production-https.conf /etc/nginx/sites-available/huwutong
sudo ln -sf /etc/nginx/sites-available/huwutong /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

要点：

- `443` 终止 TLS，PHP-FPM 处理 Laravel
- `/app` 反代到 `127.0.0.1:8080`（Reverb）
- `fastcgi_param HTTPS on` + `X-Forwarded-Proto` 供 Laravel 识别 HTTPS

### 2.2 Let's Encrypt

```bash
sudo certbot certonly --nginx -d 88.huwutong.com -d www.huwutong.com
sudo certbot renew --dry-run
```

---

## 3. Reverb 进程

Supervisor 示例见 `deploy/supervisor/reverb.conf`：

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

生产由 Supervisor/systemd 守护，**不对外暴露 8080**，仅 Nginx `/app` 反代。

---

## 4. 前端构建

```bash
npm ci
npm run build
```

构建产物注入 `VITE_REVERB_SCHEME=https`，浏览器连接 `wss://88.huwutong.com/app/{key}`。

---

## 5. 验收标准

| 检查项 | 预期 |
|--------|------|
| 浏览器访问 | `https://88.huwutong.com` 无证书警告 |
| HTTP 跳转 | `http://` 自动 301 → `https://` |
| 登录 / API | Cookie + Bearer Token 正常 |
| Echo 控制台 | `[Echo] WebSocket 已连接` |
| iOS Safari | 可打开 IM；麦克风/WebRTC 可用（需 HTTPS） |
| 验证脚本 | `php scripts/verify-https-config.php` 通过 |

---

## 6. 常见问题

**WebSocket 连接失败**

- 确认 Nginx `location /app` 已配置 Upgrade 头
- 确认 `REVERB_HOST` / `VITE_REVERB_HOST` 为公网域名
- 防火墙仅开放 443，不开放 8080

**Sanctum 401 / CSRF**

- `SANCTUM_STATEFUL_DOMAINS` 须包含浏览器地址栏 host（含端口）
- 前后端同域时无需跨域 Cookie；跨子域需 `SESSION_DOMAIN=.huwutong.com`

**仍显示 http:// 链接**

- 设置 `FORCE_HTTPS=true` 并 `php artisan config:cache`

---

*关联任务：D-02 · 总方案见 [`docs/生产部署方案.md`](./生产部署方案.md) · 计划表 `docs/测试规划与开发任务计划表.md`*
