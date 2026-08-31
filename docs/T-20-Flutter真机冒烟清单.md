# T-20 Flutter 真机冒烟清单

> 依赖：真实 `google-services.json` + 后端 FCM 凭据（见 `docs/真机账号上架指南.md`）  
> 自检：`php scripts/verify-mobile-credentials.php` 须先通过 FCM 项

## 环境

- [ ] Firebase 项目已创建，包名 `com.huwutong.license`
- [ ] 已运行 `install-firebase-credentials.ps1` 安装 Android/服务账号
- [ ] `.env` 含 `FCM_PROJECT_ID` / `FCM_CREDENTIALS_PATH`
- [ ] `php artisan fcm:test-push <email> --dry-run` 通过
- [ ] 真机可访问 API：`--dart-define=API_BASE_URL=https://…/api`

## 冒烟步骤

| # | 操作 | 预期 | 结果 |
|:-:|------|------|:----:|
| 1 | `flutter run` 安装到真机 | 启动无崩溃 | ☐ |
| 2 | 邮箱密码登录 | 进入仪表盘 | ☐ |
| 3 | 打开 License 列表 | 数据加载 | ☐ |
| 4 | 打开审批 / 通知 | 页面正常 | ☐ |
| 5 | 登录后查库 `users.fcm_token` | 有 Token | ☐ |
| 6 | `php artisan fcm:test-push <email>` | 真机收到通知 | ☐ |
| 7 | 点击通知 | App 回到前台 | ☐ |
| 8 | 登出 | Token 清除 | ☐ |

## 记录

- 设备型号：
- 系统版本：
- API_BASE_URL：
- 测试人 / 日期：
- 阻塞问题：
