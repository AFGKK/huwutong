# 短信/邮件生产化指南（D-04）

> 目标：`SMS_DRIVER=aliyun`、`MAIL_MAILER=smtp` 后，验证码与通知可真实送达。

---

## 1. 邮件（SMTP）

### 环境变量

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@huwutong.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@huwutong.com
MAIL_FROM_NAME="HWT License"
MAIL_ADMIN_ADDRESS=admin@huwutong.com
```

### 冒烟测试

```bash
php artisan notifications:test --email=you@example.com
```

---

## 2. 短信（阿里云）

### 环境变量

```env
SMS_DRIVER=aliyun
ALIYUN_SMS_ACCESS_KEY_ID=LTAI...
ALIYUN_SMS_ACCESS_KEY_SECRET=...
ALIYUN_SMS_SIGN_NAME=互物通
ALIYUN_SMS_TEMPLATE_CODE=SMS_xxxxxx
# 可选：通知类短信模板
# ALIYUN_SMS_NOTIFICATION_TEMPLATE=SMS_yyyyyy
```

模板变量需包含 `code`（验证码场景）。

### 冒烟测试

```bash
php artisan notifications:test --phone=13800138000
```

---

## 3. 开发 vs 生产

| 环境 | MAIL_MAILER | SMS_DRIVER | 说明 |
|------|-------------|------------|------|
| 本地 | `log` | `log` | 写入 `storage/logs` |
| 生产 | `smtp` / `ses` | `aliyun` | 必须真实送达 |

生产环境若仍为 `log`，`notifications:test` 会警告。

---

## 4. 调用链

| 场景 | 入口 |
|------|------|
| 手机验证码登录 | `AuthController::sendPhoneCode` → `SmsService` |
| 邮箱验证码 | `VerifyCodeService::sendEmail` → `VerifyCodeMail` |
| 续费/告警通知 | `MultiChannelNotifier` → Mail + SMS |

---

## 5. 验收清单

- [ ] `php artisan notifications:test --email=...` 收到测试邮件
- [ ] `php artisan notifications:test --phone=...` 收到短信验证码
- [ ] 登录页手机验证码 API 返回 200
- [ ] 日志无 `SMS: aliyun failed` / `VerifyCode: email send failed`

---

*关联：D-04 · D-28 Flutter 推送 · T-06 电商通知*
