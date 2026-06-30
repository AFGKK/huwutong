# HWT License 移动端 App

> M3-67: 原生移动端 App（Flutter 跨平台）

## 概述

Flutter 跨平台 iOS/Android App，提供管理后台核心功能移动化：

- 📊 **仪表盘** — 今日激活数/过期数/收入概览
- ✅ **激活审批** — 移动端快速审批设备激活请求
- 🔔 **告警推送** — 实时推送安全告警/过期提醒
- 🔐 **生物识别登录** — 指纹/Face ID 快捷登录

## 技术栈

| 技术 | 用途 |
|:----|:------|
| **Flutter 3.x** | 跨平台 UI 框架 |
| **Dio** | HTTP 客户端 |
| **Provider** | 状态管理 |
| **local_auth** | 生物识别（指纹/FaceID） |
| **firebase_messaging** | 推送通知 |
| **flutter_local_notifications** | 本地通知 |
| **flutter_secure_storage** | 安全令牌存储 |
| **fl_chart** | 图表展示 |
| **shimmer** | 骨架屏加载动画 |

## 快速开始

```bash
# 1. 进入移动端目录
cd mobile

# 2. 获取依赖
flutter pub get

# 3. 生成代码（JSON 序列化）
dart run build_runner build

# 4. 运行（Android）
flutter run

# 5. 运行（iOS）
flutter run -d ios
```

## 环境配置

通过 `--dart-define` 传入：

```bash
flutter run --dart-define=API_BASE_URL=http://your-server.com/api
```

默认值指向 `http://10.0.2.2:8000/api`（Android 模拟器的宿主机）。

## 项目结构

```
mobile/
├── lib/
│   ├── main.dart                 # 入口 + Provider 注册
│   ├── config/
│   │   ├── api_config.dart       # API 地址/超时配置
│   │   ├── theme.dart            # 主题/颜色常量
│   │   └── push_config.dart      # 推送通知配置
│   ├── models/
│   │   └── models.dart           # 数据模型
│   ├── services/
│   │   ├── api_service.dart      # API 客户端（Dio）
│   │   └── push_service.dart     # 推送通知服务（FCM + 本地通知）
│   ├── providers/
│   │   ├── auth_provider.dart    # 认证状态
│   │   └── app_providers.dart    # Dashboard/License/Notification/Approval/Device
│   ├── screens/
│   │   ├── login_screen.dart     # 登录页（含生物识别）
│   │   ├── home_screen.dart      # 主页（底部导航）
│   │   ├── dashboard_screen.dart # 仪表盘
│   │   ├── licenses_screen.dart  # License 列表
│   │   ├── devices_screen.dart   # 设备管理
│   │   ├── approvals_screen.dart # 激活审批
│   │   ├── notifications_screen.dart # 通知中心
│   │   └── profile_screen.dart   # 个人中心
│   └── widgets/
│       └── common_widgets.dart   # 复用组件
├── assets/
│   ├── images/                   # 图片资源
│   └── icons/                    # 图标资源
├── android/                      # Android 原生配置
├── ios/                          # iOS 原生配置
├── analysis_options.yaml
├── pubspec.yaml
└── README.md
```

## 构建发布

```bash
# Android APK
flutter build apk --release

# Android App Bundle
flutter build appbundle --release

# iOS
flutter build ios --release
```

## API 端点对照

| App 功能 | API 端点 | 方法 |
|---------|---------|:----:|
| 登录 | `/api/login` | POST |
| 仪表盘 | `/api/licenses/stats` | GET |
| License 列表 | `/api/licenses` | GET |
| License 详情 | `/api/licenses/{id}` | GET |
| 吊销 License | `/api/licenses/{id}/revoke` | POST |
| 暂停 License | `/api/licenses/{id}/suspend` | POST |
| 设备列表 | `/api/devices` | GET |
| 删除设备 | `/api/devices/{id}` | DELETE |
| 通知列表 | `/api/notifications` | GET |
| 未读数 | `/api/notifications/unread-count` | GET |
| 标记已读 | `/api/notifications/{id}/read` | POST |
| 审批列表 | `/api/approvals/pending` | GET |
| 通过审批 | `/api/approvals/{id}/approve` | POST |
| 拒绝审批 | `/api/approvals/{id}/reject` | POST |
