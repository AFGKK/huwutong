# 真实支付接入指南（D-03）

> 目标：`PAYMENT_DRIVER` 切换 alipay/stripe 后，下单 → 支付 → Webhook 验签 → 订单/发票状态闭环。

---

## 1. 环境变量

| 驱动 | 必填变量 | Webhook URL |
|------|---------|-------------|
| `mock` | 无 | — |
| `alipay` | `ALIPAY_APP_ID`, `ALIPAY_PRIVATE_KEY`, `ALIPAY_PUBLIC_KEY` | `{APP_URL}/api/payment/alipay/webhook` |
| `stripe` | `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | `{APP_URL}/api/payment/stripe/webhook` |

生产示例见 `.env.production.example`；切换驱动：

```env
PAYMENT_DRIVER=alipay
ALIPAY_ENABLED=true
ALIPAY_SANDBOX=false
```

验证配置：

```bash
php artisan config:cache
php artisan test --filter=PaymentFlow
```

---

## 2. 支付流程

```
用户下单 → OrderService 创建 Invoice(pending)
         → PaymentManager.charge() 返回 redirect/form/client_secret
         → 【异步】等待网关 Webhook
         → BillingService.markInvoicePaid()
         → InvoicePaymentSettlementService 联动 Order.markPaid + 发货
```

**同步网关**（仅 `mock`、预付余额）：`charge` 后立即标记已支付。

**异步网关**（alipay/stripe/wechat/paypal）：发票保持 `pending`，直到 Webhook 验签通过。

---

## 3. 支付宝

1. 开放平台创建应用，配置 RSA2 密钥
2. 设置异步通知 URL：`https://你的域名/api/payment/alipay/webhook`
3. 沙箱：`ALIPAY_SANDBOX=true`，网关自动指向 `openapi-sandbox.dl.alipaydev.com`
4. 回调验签：使用 `ALIPAY_PUBLIC_KEY`（RSA2）

---

## 4. Stripe

1. Dashboard 获取 `STRIPE_SECRET` / `STRIPE_KEY`
2. Webhook 端点：`/api/payment/stripe/webhook`，订阅 `payment_intent.succeeded`
3. 将 `STRIPE_WEBHOOK_SECRET` 写入 `.env`
4. 前端使用 `client_secret` 调 Stripe.js 完成支付

---

## 5. 验收清单

| 步骤 | 预期 |
|------|------|
| Mock 支付 | 发票/订单立即 `paid` |
| Alipay charge | 返回 `payment_form`，发票仍 `pending` |
| Alipay Webhook | 响应 `success`，发票+订单 `paid` |
| Stripe Webhook | `payment_intent.succeeded` 更新发票 |
| 重复 Webhook | 幂等，不重复发货 |

---

*关联：D-03 · D-15 电商闭环 · T-06/T-07*
