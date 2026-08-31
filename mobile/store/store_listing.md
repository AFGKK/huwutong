# D-29: 应用商店上架资料
#
# 文件名约定:
#   - `{lang}` 替换为目标语言代码 (zh-Hans, en-US)
#   - 截图文件: screenshot-{screen}-{lang}-{index}.png
#     screen: dashboard, licenses, devices, approvals, notifications, profile, login

# ============================================
# 1. 应用基本信息
# ============================================

APP_NAME_ZH: "互物通 License"
APP_NAME_EN: "HWT License"

BUNDLE_ID: "com.huwutong.license"
PRIMARY_LANG: "zh-Hans"
SECONDARY_LANG: "en-US"

CATEGORY: "Business"
SUBCATEGORY: ""                          # iOS: Productivity, Android: Productivity

CONTENT_RATING:
  ZH: "4+"                              # 无成人/暴力内容
  EN: "4+"

PRICE_TIER: 0                            # 免费

# ============================================
# 2. 应用描述
# ============================================

# 简体中文
DESCRIPTION_ZH: |
  互物通 License 是企业授权管理的专业移动助手。随时随地管理您的软件授权、监控设备激活状态、处理审批请求。

  主要功能：
  • 仪表盘 — 实时查看 License 总数、活跃数、即将到期数
  • License 管理 — 查看详情、暂停、吊销操作
  • 设备管理 — 查看已激活设备、删除异常设备
  • 激活审批 — 快速处理设备激活请求（通过/拒绝）
  • 通知中心 — 接收授权过期告警、安全通知
  • 推送通知 — 实时推送重要通知到您的手机
  • 生物识别 — 指纹/Face ID 快捷安全登录
  
  互物通 License 将您的授权管理工作装进口袋，让您随时随地掌控授权状态。

# 英文
DESCRIPTION_EN: |
  HWT License is the ultimate mobile companion for enterprise license management. Manage your software licenses, monitor device activations, and handle approval requests wherever you are.
  
  Key Features:
  • Dashboard — Real-time overview of total, active, and expiring licenses
  • License Management — View details, suspend or revoke licenses
  • Device Management — Track activated devices, remove unauthorized ones
  • Activation Approvals — Quick approve/reject device activation requests
  • Notification Center — Receive expiry alerts and security notifications  
  • Push Notifications — Instant alerts delivered to your mobile device
  • Biometric Auth — Fingerprint / Face ID for fast and secure login
  
  HWT License puts enterprise license management in your pocket.

# ============================================
# 3. 关键词 (iOS 100 字符上限)
# ============================================

KEYWORDS_ZH: "License,授权管理,软件授权,互物通,激活码,HWT,企业授权"
KEYWORDS_EN: "license,software license,activation key,HWT,license management,enterprise software"

# ============================================
# 4. 促销文案 (App Store 170 字符上限)
# ============================================

PROMOTIONAL_TEXT_ZH: "企业授权管理的专业移动助手，随时随地管理 License。"
PROMOTIONAL_TEXT_EN: "Enterprise license management at your fingertips."

# ============================================
# 5. 隐私政策 URL
# ============================================

PRIVACY_URL_ZH: "https://huwutong.com/privacy"
PRIVACY_URL_EN: "https://huwutong.com/en/privacy"

SUPPORT_URL: "https://huwutong.com/support"
MARKETING_URL: "https://huwutong.com"

# ============================================
# 6. 截图占位说明
# ============================================
#
# Android (Google Play) 要求:
#   - 至少 2 张截图
#   - 格式: PNG (无透明通道), JPEG
#   - 尺寸: 最小 320px, 最大 3840px
#   - 推荐: 1080 × 1920 px (16:9)
#
# iOS (App Store) 要求:
#   - 6.5" 显示屏: 1242 × 2688 px (iPhone 11 Pro Max 等)
#   - 5.5" 显示屏: 1242 × 2208 px (iPhone 8 Plus 等)
#   - iPad: 2048 × 2732 px
#
# 截图列表 (需使用真机或模拟器截图):
#
#   screenshot-dashboard-zh-Hans-1.png   - 仪表盘概览
#   screenshot-licenses-zh-Hans-2.png    - License 列表
#   screenshot-devices-zh-Hans-3.png     - 设备管理
#   screenshot-approvals-zh-Hans-4.png   - 激活审批
#   screenshot-notifications-zh-Hans-5.png - 通知中心
#   screenshot-login-zh-Hans-6.png       - 登录页(含生物识别)
#
#   screenshot-dashboard-en-US-1.png     - Dashboard overview
#   screenshot-licenses-en-US-2.png      - License list
#   screenshot-devices-en-US-3.png       - Device management
#   screenshot-approvals-en-US-4.png     - Activation approvals
#   screenshot-notifications-en-US-5.png - Notification center
#   screenshot-login-en-US-6.png         - Login with biometric
