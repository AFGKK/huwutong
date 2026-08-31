# D-33 Tauri 轻量 License 查看器

> 基于 `sdk/tauri` · 安装包目标 &lt;30MB · 基础 License 查询

## 与 D-32 Electron 对比

| 项目 | Electron (D-32) | Tauri (D-33) |
|------|-----------------|--------------|
| 用途 | 完整 `/build` 管理后台 | License 查询/验证 |
| 体积 | ~100MB+ | 目标 &lt;30MB |
| 技术 | Node + Chromium | Rust + 系统 WebView |
| SDK | — | `sdk/tauri` HwtClient |

## 目录

```
desktop/tauri/
├── package.json
├── src/                 # 轻量 HTML UI
│   ├── index.html
│   ├── main.js
│   └── styles.css
└── src-tauri/
    ├── Cargo.toml       # 依赖 huwutong-sdk
    ├── tauri.conf.json
    └── src/lib.rs       # lookup_license / validate_license
```

## 快速启动

```bash
# 1. 安装 Rust: https://rustup.rs
# 2. 启动后端
php artisan serve --port=8000

# 3. 开发模式
powershell -File scripts/tauri-dev.ps1
bash scripts/tauri-dev.sh

# 4. 环境检查
php artisan tauri:env-check
```

## Tauri 命令

| 命令 | 说明 |
|------|------|
| `lookup_license` | POST `/api/license/public-lookup`（无需 API Key） |
| `validate_license` | 通过 `sdk/tauri` HwtClient 验证 |

## 打包

```bash
cd desktop/tauri
npm install
npm run build          # 产出在 src-tauri/target/release/bundle/
```

Release profile 已启用 `opt-level = "z"` + LTO 以控制体积。

## 图标

```bash
cd desktop/tauri
npm run tauri icon path/to/source.png
```

## 相关

- [desktop-electron.md](./desktop-electron.md) — 完整管理壳
- [sdk/tauri/README.md](../sdk/tauri/README.md) — Rust SDK
