# 上线运维就绪清单

本地 / 预发日常检查：

```bash
php artisan ops:readiness
```

生产门禁（警告与可选能力缺失均失败）：

```bash
php artisan ops:readiness --strict
```

## 检查范围

| 段 | 内容 | 默认 | `--strict` |
|----|------|------|------------|
| 品牌与备案 | Logo、主题色、联系邮箱、ICP/公安 | 演示备案 = 警告 | 警告计失败 |
| OAuth | 仅检查「已启用」渠道的凭据 | 未启用跳过 | 同左 |
| 微信小程序 | AppId / Secret / CI key | 缺失 = 警告 | 缺失 = 失败 |
| 支付/短信/邮件 | mock / log / 真实驱动 | 开发模式 = 警告 | 警告计失败 |
| 静态资源 | favicon、PWA 图标 | 缺失 = 失败 | 同左 |

## 需人工填入的密钥

- 真实 ICP / 公安备案号（后台品牌设置）
- 小程序 AppId、AppSecret、`miniprogram/ci/private.key`
- SMTP 账号（将 `mail_driver` / `MAIL_MAILER` 从 `log` 改为 `smtp`）
- 支付网关（将 `payment_driver` 从 `mock` 改为真实渠道并填密钥）
- 短信（`sms_driver=aliyun` 并配置 AccessKey）

页脚已通过 `site_beian_public()` 隐藏演示备案号，不影响公开站展示。
