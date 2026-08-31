# D-32 Electron 管理壳

> WebView 加载 `/build` 管理后台 + 系统托盘 + 自动更新

## 目录

```
desktop/electron/
├── package.json       # Electron + electron-builder
├── config.js          # HWT_ADMIN_URL 等配置
├── src/
│   ├── main.js        # 主进程、托盘、单实例
│   ├── preload.js     # window.hwtDesktop API
│   └── updater.js     # electron-updater
└── assets/            # icon.png / icon.ico / icon.icns
```

## 快速启动

```bash
# 1. 启动 Laravel 后端
php artisan serve --port=8000

# 2. 开发模式启动 Electron
powershell -File scripts/electron-dev.ps1          # Windows
bash scripts/electron-dev.sh                       # Linux/macOS

# 3. 环境检查
php artisan electron:env-check
```

## 环境变量

| 变量 | 默认 | 说明 |
|------|------|------|
| `HWT_ADMIN_URL` | `http://127.0.0.1:8000/build` | 管理后台 SPA 地址 |
| `HWT_ELECTRON_UPDATE_URL` | — | 打包后 auto-update manifest 根 URL |
| `HWT_ELECTRON_AUTO_UPDATE` | `true` | 是否启用自动更新 |

## 打包

```bash
cd desktop/electron
npm install
npm run dist:win    # Windows NSIS 安装包
npm run dist:mac    # macOS DMG
```

产物输出至 `desktop/electron/dist/`。

## 功能

- **WebView**：加载 `/build/login`，与 PWA 共用同一 SPA
- **系统托盘**：双击显示、右键菜单（显示/仪表盘/检查更新/退出）
- **单实例**：重复启动聚焦已有窗口
- **自动更新**：打包后通过 `electron-updater` + generic provider
- **preload API**：`window.hwtDesktop.isDesktop` 供前端识别桌面环境

## 相关任务

- **D-02**：PWA manifest `/build/login`（共用入口）
- **D-33**：Tauri 轻量版 — 见 [desktop-tauri.md](./desktop-tauri.md)
