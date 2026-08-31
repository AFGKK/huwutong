# 小程序 CI（miniprogram-ci）

> 上传体验版 / 生成预览码，无需每次打开微信开发者工具。

## 一次性准备

1. 微信公众平台 → **开发管理 → 开发设置 → 小程序代码上传** → 生成并下载密钥  
2. 保存为 `miniprogram/ci/private.key`（勿提交 Git）  
3. 安装依赖：

```bash
cd miniprogram
npm install
```

## 本地命令

```bash
# 冒烟：校验密钥与项目结构
npm run ci:pack

# 生成预览二维码 → ci/preview-qrcode.jpg
npm run ci:preview

# 上传体验版（版本号默认当天日期）
npm run ci:upload

# 指定版本与备注
set MP_CI_VERSION=1.0.0
set MP_CI_DESC=B6 支付引导
npm run ci:upload
```

（Linux/macOS 用 `export MP_CI_VERSION=1.0.0`）

## GitHub Actions

工作流：`.github/workflows/miniprogram-ci.yml`  

仓库 Secrets：

| Secret | 说明 |
|--------|------|
| `MP_CI_PRIVATE_KEY` | 上传密钥 pem 全文（换行用 `\n`） |
| `MP_CI_APPID` | 可选，默认读 `project.config.json` |

手动触发：Actions → Miniprogram CI → Run workflow → 选 `preview` 或 `upload`。

## 安全

- `ci/private.key`、`ci/.private.key.tmp`、`ci/preview-qrcode.jpg` 已 gitignore  
- 密钥泄露后请在公众平台立即重置上传密钥  
