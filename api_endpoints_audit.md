# 客户门户前端 API 端点完整审计

> 生成日期：2026-06-17
> 来源：`resources/js/views/portal/*.vue` + `resources/js/api/*.js`
> 对照：`routes/api.php`

---

## 一、各门户页面使用的 API

### 1. `Index.vue` — 仪表盘

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/licenses/stats` | `licenseApi.stats()` | ✅ 已存在 |
| GET | `/licenses` | `licenseApi.list()` (with `per_page`, `expiring`, `sort`) | ✅ 已存在 |
| GET | `/devices/stats` | `deviceApi.stats()` | ✅ 已存在 |
| GET | `/devices` | `deviceApi.list()` | ✅ 已存在 |

### 2. `Licenses.vue` — License 列表

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/licenses` | `licenseApi.list()` | ✅ 已存在 |
| GET | `/licenses/stats` | `licenseApi.stats()` | ✅ 已存在 |

### 3. `LicenseDetail.vue` — License 详情

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/licenses/{id}` | `licenseApi.show(id)` | ✅ 已存在 |
| GET | `/devices` (with `license_id`) | `deviceApi.list({license_id})` | ✅ 已存在 |
| PUT | `/licenses/{id}` | `licenseApi.update(id, {type:'enterprise'})` (升级) | ✅ 已存在 |
| POST | `/licenses/{id}/refund` | `licenseApi.refund(id)` | ✅ 已存在 |
| POST | `/devices/{id}/deactivate` | `deviceApi.deactivate(dev.id)` | ✅ 已存在 |
| POST | `/billing/subscriptions/*` | `billingApi.manualRenew(id)` | ⚠️ `manualRenew` 未找到 — 需要确认 |

### 4. `LicenseHealth.vue` — License 健康评分

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/portal/license-health/dashboard` | `licenseHealth.dashboard()` | ❌ **缺失** |
| GET | `/portal/license-health` | `licenseHealth.list()` | ❌ **缺失** |

### 5. `Orders.vue` — 订单

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/orders` | `shopApi.listOrders()` | ❌ **缺失** |
| GET | `/orders/{id}` | `shopApi.getOrder(id)` | ❌ **缺失** |
| POST | `/orders/{id}/cancel` | `shopApi.cancelOrder(id)` | ❌ **缺失** |

### 6. `Billing.vue` — 账单与发票

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/billing/subscriptions` | `billingApi.subscriptions()` | ✅ 已存在 |
| GET | `/billing/invoices` | `billingApi.invoices()` | ✅ 已存在 |
| POST | `/billing/invoices/{id}/pay` | `billingApi.payInvoice(id, method)` | ✅ 已存在 |
| GET | `/billing/invoices/{id}/payment-status` | `billingApi.paymentStatus(id)` | ✅ 已存在 |

### 7. `PaymentMethods.vue` — 支付方式

| 方法 | URL | 调用方式 | 状态 |
|------|-----|---------|------|
| GET | `/billing/payment-methods` | `apiClient.get()` | ❌ **缺失** |
| POST | `/billing/payment-methods` | `apiClient.post()` | ❌ **缺失** |
| POST | `/billing/payment-methods/{id}/default` | `apiClient.post()` | ❌ **缺失** |
| DELETE | `/billing/payment-methods/{id}` | `apiClient.delete()` | ❌ **缺失** |
| GET | `/billing/auto-renewal` | `apiClient.get()` | ❌ **缺失** |
| POST | `/billing/auto-renewal` | `apiClient.post()` | ❌ **缺失** |

### 8. `Devices.vue` — 设备管理

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/devices` | `deviceApi.list()` | ✅ 已存在 |
| GET | `/devices/stats` | `deviceApi.stats()` | ✅ 已存在 |
| POST | `/devices/{id}/deactivate` | `deviceApi.deactivate(id)` | ✅ 已存在 |

### 9. `Transfers.vue` — License 转移

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/portal/transfers` | `transfer.myRequests()` | ❌ **缺失** |
| GET | `/portal/transfers/licenses` | `transfer.transferableLicenses()` | ❌ **缺失** |
| POST | `/portal/transfers` | ⟶ uses `transfer.create()` → `POST /transfers` | ❌ **缺失** |
| POST | `/portal/transfers/{id}/cancel` | `transfer.myCancel(id)` | ❌ **缺失** |

注意：`Transfers.vue` 调用了 `transfer.create()` 即 `POST /transfers`（管理端），**同一个方法被门户页面使用**，需要确认权限控制。

### 10. `Earnings.vue` — 收益账户

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/portal/earnings/dashboard` | `earningsPortalApi.dashboard()` | ❌ **缺失** |
| GET | `/portal/earnings/commissions` | `earningsPortalApi.commissions()` | ❌ **缺失** |
| GET | `/portal/earnings/commissions/export` | `earningsPortalApi.exportCommissions()` | ❌ **缺失** |
| GET | `/portal/earnings/tax-info` | `earningsPortalApi.getTaxInfo()` | ❌ **缺失** |
| POST | `/portal/earnings/tax-info` | `earningsPortalApi.saveTaxInfo()` | ❌ **缺失** |
| GET | `/portal/earnings/settlement-calendar` | `earningsPortalApi.settlementCalendar()` | ❌ **缺失** |
| GET | `/withdrawals/channels` | `withdrawalApi.userChannels()` | ❌ **缺失** |
| POST | `/withdrawals` | `withdrawalApi.requestWithdrawal()` | ❌ **缺失** |
| POST | `/withdrawals/{id}/cancel` | `withdrawalApi.cancelWithdrawal(id)` | ❌ **缺失** |

### 11. `Promotions.vue` — 优惠与促销

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/portal/promotions/active` | `promotions.activePromotions()` | ❌ **缺失** |
| GET | `/portal/promotions/coupons` | `promotions.customerCoupons()` | ❌ **缺失** |

### 12. `Tickets.vue` — 工单列表

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/tickets/categories` | `ticketApi.categories()` | ✅ 已存在 |
| GET | `/tickets/my` | `ticketApi.myTickets()` | ✅ 已存在 |
| POST | `/tickets` | `ticketApi.create()` | ✅ 已存在 |
| GET | `/tickets/stats` | `ticketApi.stats()` | ✅ 已存在 |

### 13. `TicketDetail.vue` — 工单详情

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/tickets/{id}` | `ticketApi.show(id)` | ✅ 已存在 |
| POST | `/tickets/{id}/reply` | `ticketApi.reply(id, data)` | ✅ 已存在 |
| POST | `/tickets/{id}/resolve` | `ticketApi.resolve(id)` | ✅ 已存在 |
| POST | `/tickets/{id}/close` | `ticketApi.close(id)` | ✅ 已存在 |
| POST | `/tickets/{id}/reopen` | `ticketApi.reopen(id)` | ✅ 已存在 |
| POST | `/tickets/{id}/satisfaction` | `ticketApi.satisfaction(id, rating)` | ✅ 已存在 |

### 14. `Settings.vue` — 个人设置

| 方法 | URL | 调用方式 | 状态 |
|------|-----|---------|------|
| GET | `/user` | `apiClient.get('/user')` | ✅ 已存在 |
| POST | `/password/change` | `apiClient.post('/password/change', data)` | ✅ 已存在 |
| POST | `/api/gdpr/requests` | `apiClient.post('/api/gdpr/requests', data)` | ❌ **缺失** (注意前缀 `/api`) |
| GET | `/api/account/deletion/check` | `deletionApi.checkDeletability()` | ❌ **缺失** (前缀 `/api`) |
| GET | `/api/account/deletion/reasons` | `deletionApi.getCancellationReasons()` | ❌ **缺失** (前缀 `/api`) |
| POST | `/api/account/deletion` | `deletionApi.requestDeletion(data)` | ❌ **缺失** (前缀 `/api`) |

### 15. `DataExport.vue` — 数据导出

| 方法 | URL | API 导出函数 | 状态 |
|------|-----|------------|------|
| GET | `/portal/data-exports/types` | `getExportTypes()` | ❌ **缺失** |
| POST | `/portal/data-exports` | `createExport()` | ❌ **缺失** |
| GET | `/portal/data-exports` | `getMyExports()` | ❌ **缺失** |
| GET | `/portal/data-exports/{id}/download` | `downloadExport(id)` | ❌ **缺失** |
| DELETE | `/portal/data-exports/{id}` | `deleteExport(id)` | ❌ **缺失** |

### 16. `MyFeedback.vue` — 我的反馈

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/portal/feedback` | `feedback.myFeedback()` | ❌ **缺失** |
| POST | `/portal/feedback` | `feedback.create()` | ❌ **缺失** |

### 17. `NotificationPreferences.vue` — 通知偏好

| 方法 | URL | API 导出函数 | 状态 |
|------|-----|------------|------|
| GET | `/portal/notification-preferences` | `getMyNotificationPreferences()` | ❌ **缺失** |
| PUT | `/portal/notification-preferences` | `updateMyNotificationPreferences()` | ❌ **缺失** |
| POST | `/portal/notification-preferences/initialize` | `initializeNotificationPreferences()` | ❌ **缺失** |
| PATCH | `/portal/notification-preferences/general` | `updateGeneralSettings()` | ❌ **缺失** |

### 18. `Team.vue` — 团队协作

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/api/team` | `tenantTeamApi.overview()` | ❌ **缺失** (前缀 `/api`) |
| GET | `/api/team/members` | `tenantTeamApi.members()` | ❌ **缺失** (前缀 `/api`) |
| POST | `/api/team/invite` | `tenantTeamApi.invite()` | ❌ **缺失** (前缀 `/api`) |
| GET | `/api/team/invitations/pending` | `tenantTeamApi.pendingInvitations()` | ❌ **缺失** (前缀 `/api`) |
| PUT | `/api/team/members/{id}/role` | `tenantTeamApi.updateMemberRole(id, data)` | ❌ **缺失** (前缀 `/api`) |
| DELETE | `/api/team/members/{id}` | `tenantTeamApi.removeMember(id)` | ❌ **缺失** (前缀 `/api`) |
| POST | `/api/team/invitations/{id}/resend` | `tenantTeamApi.resendInvitation(id)` | ❌ **缺失** (前缀 `/api`) |
| POST | `/api/team/invitations/{id}/cancel` | `tenantTeamApi.cancelInvitation(id)` | ❌ **缺失** (前缀 `/api`) |

### 19. `ApiKeys.vue` — API Keys

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/customer-api-keys/dashboard` | `getDashboard()` | ❌ **缺失** |
| GET | `/customer-api-keys/abilities` | `getAbilities()` | ❌ **缺失** |
| GET | `/api-keys` | `apiKeyApi.list()` | ✅ 已存在 |
| POST | `/api-keys` | `apiKeyApi.create()` | ✅ 已存在 |

注意：页面混用了 `apiKeyApi`（admin API Keys）和 `customerApiKey`（客户 API Keys）两个客户端。

### 20. `AuditLog.vue` — 审计日志

| 方法 | URL | 调用方式 | 状态 |
|------|-----|---------|------|
| GET | `/audit-logs` | `apiClient.get('/audit-logs')` | ✅ 已存在 |
| GET | `/audit-logs/stats` | `apiClient.get('/audit-logs/stats')` | ✅ 已存在 |

### 21. `KnowledgeBase.vue` — 帮助中心

| 方法 | URL | API 客户端 | 状态 |
|------|-----|-----------|------|
| GET | `/kb/categories` | `kbApi.categories()` | ✅ 已存在 |
| GET | `/kb/search` | `kbApi.search()` | ✅ 已存在 |
| GET | `/kb/articles/{id}` | `kbApi.getArticle(id)` | ✅ 已存在 |
| POST | `/kb/articles/{id}/feedback` | `kbApi.submitFeedback(id, data)` | ✅ 已存在 |

### 22. `Usage.vue` — 用量看板

| 方法 | URL | 调用方式 | 状态 |
|------|-----|---------|------|
| GET | `/usage/api-calls` | `apiClient.get('/usage/api-calls')` | ❌ **缺失** |
| GET | `/usage/endpoint-stats` | `apiClient.get('/usage/endpoint-stats')` | ❌ **缺失** |
| GET | `/usage/features` | `apiClient.get('/usage/features')` | ❌ **缺失** |
| GET | `/devices` | `deviceApi.list()` | ✅ 已存在 |
| GET | `/devices/stats` | `deviceApi.stats()` | ✅ 已存在 |
| GET | `/licenses` | `licenseApi.list()` | ✅ 已存在 |

### 23. `PersonalizedSection.vue` — 个性化推荐

| 方法 | URL | API 导出函数 | 状态 |
|------|-----|------------|------|
| GET | `/admin/personalization/homepage` | `getPersonalizedHomepage()` | ❌ **缺失** (admin 前缀) |
| POST | `/admin/personalization/recommendations/{id}/click` | `clickRecommendation(id)` | ❌ **缺失** (admin 前缀) |

---

## 二、API 客户端文件完整清单

### `resources/js/api/shop.js`

```javascript
GET    /products                           // 商品搜索/列表
GET    /products/search-suggest            // 搜索建议
GET    /products/hot-search-terms          // 热搜词
GET    /products/search-history            // 搜索历史
DELETE /products/search-history            // 清除搜索历史
GET    /products/filter-tags               // 筛选标签
GET    /product-categories/public          // 商品公开分类
GET    /products/{id}                      // 商品详情
GET    /products/{id}/skus                 // 商品 SKU
GET    /skus                               // SKU 查询
GET    /cart                               // 获取购物车
GET    /cart/summary                       // 购物车摘要
POST   /cart/add                           // 加入购物车
PUT    /cart/update                        // 更新购物车项
DELETE /cart/remove                        // 移除购物车项
POST   /cart/clear                         // 清空购物车
POST   /cart/apply-coupon                  // 应用优惠券
DELETE /cart/coupon                        // 移除优惠券
POST   /cart/merge                         // 合并购物车
POST   /cart/validate-checkout             // 验证结账
POST   /cart/checkout                      // 结账
POST   /cart/quick-buy                     // 快速购买
GET    /orders                             // 订单列表
GET    /orders/{id}                        // 订单详情
POST   /orders/{id}/cancel                 // 取消订单
POST   /orders/{id}/pay                    // 发起支付
GET    /orders/{id}/payment-status         // 支付状态
GET    /wishlist/my/product-ids            // 我的收藏 ID 列表
POST   /wishlist/toggle                    // 切换收藏
GET    /wishlist/check/{productId}         // 检查是否已收藏
GET    /products/{id}/reviews              // 商品评论
GET    /products/{id}/reviews/stats        // 评论统计
POST   /product-reviews                    // 提交评论
```

**所有端点均缺失**（除 `/orders` 相关路由外，订单路由也缺失）。

### `resources/js/api/transfer.js`

```javascript
GET    /transfers                          // 管理端列表
GET    /transfers/{id}                     // 管理端详情
POST   /transfers                          // 创建转移
POST   /transfers/{id}/approve             // 审批通过
POST   /transfers/{id}/reject              // 审批拒绝
POST   /transfers/{id}/cancel              // 取消
GET    /transfers/stats                    // 统计
GET    /portal/transfers                   // 门户端列表
GET    /portal/transfers/{id}              // 门户端详情
POST   /portal/transfers/{id}/cancel       // 门户端取消
GET    /portal/transfers/licenses          // 可转移的 License
```

**所有 portal 前缀端点缺失**。管理端 `/transfers` 端点也全部缺失。

### `resources/js/api/earningsPortal.js`

```javascript
GET    /portal/earnings/dashboard
GET    /portal/earnings/commissions
GET    /portal/earnings/channels
POST   /portal/earnings/channels/account
DELETE /portal/earnings/channels/account/{channel}
GET    /portal/earnings/preferences
PUT    /portal/earnings/preferences
GET    /portal/earnings/tax-info
POST   /portal/earnings/tax-info
GET    /portal/earnings/settlement-calendar
GET    /portal/earnings/commissions/export
```

**全部缺失**。

### `resources/js/api/promotions.js`

```javascript
GET    /promotions                         // 管理端列表
GET    /promotions/{id}                    // 管理端详情
POST   /promotions                        // 创建
PUT    /promotions/{id}                    // 更新
POST   /promotions/{id}/publish           // 发布
POST   /promotions/{id}/pause             // 暂停
GET    /promotions/stats                  // 统计
GET    /contracts                         // 合同列表
GET    /contracts/{id}                    // 合同详情
POST   /contracts                         // 创建合同
PUT    /contracts/{id}                    // 更新合同
POST   /contracts/{id}/approve            // 审批合同
GET    /contracts/stats                   // 合同统计
GET    /coupons                           // 优惠券列表
POST   /coupons                           // 创建优惠券
GET    /portal/promotions/active          // 门户端活跃促销
GET    /portal/promotions/coupons         // 门户端优惠券
```

**所有端点均缺失**（管理端和门户端）。

### `resources/js/api/dataExport.js`

```javascript
GET    /portal/data-exports/types
POST   /portal/data-exports
GET    /portal/data-exports
GET    /portal/data-exports/{id}/download
DELETE /portal/data-exports/{id}
GET    /admin/data-exports                // 管理端
GET    /admin/data-exports/stats
POST   /admin/data-exports
```

**全部缺失**。

### `resources/js/api/feedback.js`

```javascript
GET    /feedback                          // 管理端列表
GET    /feedback/{id}                     // 管理端详情
PUT    /feedback/{id}                     // 更新
POST   /feedback/{id}/assign              // 分配处理人
POST   /feedback/{id}/reply               // 回复
POST   /feedback/{id}/resolve             // 解决
DELETE /feedback/{id}                     // 删除
GET    /feedback/stats                    // 统计
GET    /feedback/vote-stats               // 投票统计
GET    /feedback/tags                     // 标签列表
POST   /feedback/tags                     // 创建标签
POST   /feedback/{id}/vote                // 投票
GET    /portal/feedback                   // 门户端列表
GET    /portal/feedback/{id}              // 门户端详情
POST   /portal/feedback                   // 门户端创建
```

**全部缺失**。

### `resources/js/api/team.js`

```javascript
GET    /team                              // 概览
GET    /team/members                      // 成员列表
POST   /team/invite                       // 邀请
POST   /team/invitations/accept           // 接受邀请
POST   /team/invitations/decline          // 拒绝邀请
GET    /team/invitations/pending          // 待处理邀请
POST   /team/invitations/{id}/cancel      // 取消邀请
POST   /team/invitations/{id}/resend      // 重发邀请
PUT    /team/members/{id}/role            // 更新角色
DELETE /team/members/{id}                 // 移除成员
POST   /team/transfer-admin              // 转让管理员
POST   /team/leave                        // 退出团队
```

**全部缺失**。

### `resources/js/api/tenantTeam.js` (门户实际使用的)

```javascript
GET    /api/team                          // (注意 /api 前缀)
GET    /api/team/members
POST   /api/team/invite
POST   /api/team/invitations/accept
POST   /api/team/invitations/decline
POST   /api/team/invitations/{id}/cancel
POST   /api/team/invitations/{id}/resend
GET    /api/team/invitations/pending
PUT    /api/team/members/{id}/role
DELETE /api/team/members/{id}
POST   /api/team/transfer-admin
POST   /api/team/leave
```

**全部缺失**（注意前端 axios baseURL 已是 `/api`，所以实际发往 `/api/api/team` — 可能是 BUG！）

### `resources/js/api/deletion.js`

```javascript
GET    /api/account/deletion/check         // (前缀 /api 通过 request 拦截器)
POST   /api/account/deletion
GET    /api/account/deletion/reasons
GET    /admin/deletion/records
GET    /admin/deletion/stats
POST   /admin/deletion/admin/anonymize
```

`/api/account/deletion/check`、`/api/account/deletion`、`/api/account/deletion/reasons` — **缺失**（注意路径带 `/api` 前缀）。
`/account/deletions/*` 在 api.php 中已有保留，但路径不一致。

### `resources/js/api/gdprCompliance.js`

```javascript
POST   /gdpr/requests
GET    /gdpr/my-requests
GET    /gdpr/requests/{id}/download
GET    /gdpr/requests                     // 管理端
GET    /gdpr/requests/{id}
POST   /gdpr/requests/{id}/process
POST   /gdpr/requests/{id}/review
GET    /gdpr/stats
GET    /gdpr/dpa
POST   /gdpr/dpa
PUT    /gdpr/dpa/{id}
POST   /gdpr/dpa/{id}/publish
POST   /gdpr/dpa/{id}/sign
GET    /gdpr/dpa/my-status
```

**全部缺失**。

### `resources/js/api/branding.js`

```javascript
GET    /branding                          // 公开品牌数据
GET    /admin/portal-branding             // 管理端品牌配置
PUT    /admin/portal-branding
POST   /admin/portal-branding/reset
GET    /admin/portal-branding/theme-templates
POST   /admin/portal-branding/apply-theme
```

**全部缺失**。

### `resources/js/api/billing.js`

```javascript
GET    /billing/subscriptions
POST   /billing/subscriptions
GET    /billing/subscriptions/{id}
PUT    /billing/subscriptions/{id}/plan
POST   /billing/subscriptions/{id}/cancel
POST   /billing/subscriptions/{id}/resume
POST   /billing/subscriptions/{id}/renew
POST   /billing/subscriptions/{id}/suspend
GET    /billing/invoices
GET    /billing/invoices/{id}
POST   /billing/invoices/{id}/mark-paid
POST   /billing/invoices/{id}/pay
GET    /billing/invoices/{id}/payment-status
GET    /billing/stats
GET    /billing/invoice-stats
GET    /billing/plans/public
GET    /billing/plans
POST   /billing/plans
PUT    /billing/plans/{id}
DELETE /billing/plans/{id}
GET    /billing/coupons
POST   /billing/coupons
PUT    /billing/coupons/{id}
POST   /billing/coupons/validate
GET    /billing/coupons/stats
GET    /billing/coupons/{id}/redemptions
```

订阅和发票相关 **✅ 已存在**。套餐(plans)和优惠券(coupons)相关 **❌ 缺失**。

### `resources/js/api/dataAnonymization.js`

```javascript
POST   /api/data-anonymization/export
POST   /api/data-anonymization/preview
GET    /api/data-anonymization/tables
GET    /api/data-anonymization/tasks
GET    /api/data-anonymization/tasks/{id}
POST   /api/data-anonymization/tasks/{id}/retry
GET    /api/data-anonymization/rules
POST   /api/data-anonymization/rules
DELETE /api/data-anonymization/rules/{id}
```

**全部缺失**（前缀 `/api` 由于 request 拦截器会产生 `/api/api/data-anonymization/...` — 可能是 BUG）。

### `resources/js/api/customerSmtp.js`

```javascript
GET    /admin/customer-smtp/dashboard
GET    /admin/customer-smtp
POST   /admin/customer-smtp
PUT    /admin/customer-smtp/{id}
DELETE /admin/customer-smtp/{id}
POST   /admin/customer-smtp/{id}/test
POST   /admin/customer-smtp/{id}/set-primary
POST   /admin/customer-smtp/{id}/send-test
GET    /admin/customer-smtp/logs/list
POST   /admin/customer-smtp/recover
GET    /admin/customer-smtp/providers/list
```

**全部缺失**。

### `resources/js/api/notificationPreference.js`

```javascript
GET    /portal/notification-preferences
PUT    /portal/notification-preferences
POST   /portal/notification-preferences/initialize
GET    /portal/notification-preferences/check
PATCH  /portal/notification-preferences/general
GET    /portal/notification-preferences/resolve-channels
GET    /admin/notification-preferences
GET    /admin/notification-preferences/stats
GET    /admin/notification-preferences/users/{userId}
POST   /admin/notification-preferences/batch-update
PATCH  /admin/notification-preferences/users/{userId}/general
POST   /admin/notification-preferences/users/{userId}/initialize
```

**全部缺失**。

### `resources/js/api/auth.js`

```javascript
POST   /login
POST   /logout
GET    /user
POST   /register
POST   /token/refresh
```

**✅ 全部已存在**。

### `resources/js/api/account-deletion.js` (独立文件)

```javascript
POST   /api/account/deletion              // (前缀 /api)
POST   /api/account/deletion/cancel
GET    /api/account/deletion/status
GET    /api/account/deletion/check
GET    /api/account/deletion/reasons
GET    /account/deletions/pending         // 管理端
GET    /account/deletions/history
POST   /account/deletions/approve
POST   /account/deletions/reject
GET    /account/deletions/stats
GET    /admin/deletion/records
GET    /admin/deletion/stats
POST   /admin/deletion/admin/anonymize
```

管理端 `/account/deletions/*` 在 routes 中部分存在（但前缀是父路由组，实际路径可能为 `/api/account/deletions/*`）。

### `resources/js/api/licenseHealth.js`

```javascript
GET    /portal/license-health/dashboard
GET    /portal/license-health
GET    /portal/license-health/{licenseId}
```

**全部缺失**。

### `resources/js/api/personalization.js`

```javascript
POST   /admin/personalization/behaviors
GET    /admin/personalization/behavior-stats
GET    /admin/personalization/preferences
GET    /admin/personalization/preferences/{key}
POST   /admin/personalization/preferences
POST   /admin/personalization/recommendations/generate
GET    /admin/personalization/recommendations
POST   /admin/personalization/recommendations/refresh-all
POST   /admin/personalization/recommendations/{id}/dismiss
POST   /admin/personalization/recommendations/{id}/click
GET    /admin/personalization/homepage
GET    /admin/personalization/admin-dashboard
GET    /admin/personalization/event-types
```

**全部缺失**。

### `resources/js/api/customerApiKey.js`

```javascript
GET    /customer-api-keys/dashboard
GET    /customer-api-keys
POST   /customer-api-keys
PUT    /customer-api-keys/{id}
DELETE /customer-api-keys/{id}
POST   /customer-api-keys/{id}/toggle
GET    /customer-api-keys/abilities
GET    /admin/customer-api-keys/dashboard
GET    /admin/customer-api-keys
DELETE /admin/customer-api-keys/{id}
POST   /admin/customer-api-keys/{id}/toggle
```

**全部缺失**。

### `resources/js/api/withdrawal.js`

```javascript
GET    /admin/withdrawals                 // 管理端
GET    /admin/withdrawals/{id}
POST   /admin/withdrawals/{id}/review
POST   /admin/withdrawals/{id}/completed
POST   /admin/withdrawals/{id}/failed
POST   /admin/withdrawals/{id}/proof
GET    /admin/withdrawals/stats
GET    /admin/withdrawals/batches
GET    /admin/withdrawals/batches/{id}
POST   /admin/withdrawals/batches
POST   /admin/withdrawals/batches/{id}/complete
GET    /admin/withdrawals/channels
POST   /admin/withdrawals/{id}/retry
POST   /admin/withdrawals/batch-retry
POST   /admin/withdrawals/release-pending
GET    /admin/withdrawals/risk-check
GET    /withdrawals                       // 用户端
GET    /withdrawals/stats
GET    /withdrawals/channels
POST   /withdrawals
POST   /withdrawals/{id}/cancel
```

**全部缺失**。

### `resources/js/api/kb.js`

```javascript
GET    /kb/categories                     ✅ 已存在
GET    /kb/search                         ✅ 已存在
GET    /kb/articles/{id}                  ✅ 已存在
POST   /kb/articles/{id}/feedback         ✅ 已存在
GET    /kb/articles                       ✅ 已存在 (admin)
POST   /kb/articles                       ✅ 已存在
PUT    /kb/articles/{id}                  ✅ 已存在
POST   /kb/articles/{id}/publish          ✅ 已存在
POST   /kb/articles/{id}/archive          ✅ 已存在
DELETE /kb/articles/{id}                  ✅ 已存在
GET    /kb/articles/{id}/versions         ✅ 已存在
POST   /kb/categories                     ✅ 已存在
PUT    /kb/categories/{id}                ✅ 已存在
DELETE /kb/categories/{id}                ✅ 已存在
POST   /kb/batch/delete                   ❌ 缺失
POST   /kb/batch/publish                  ❌ 缺失
POST   /kb/batch/archive                  ❌ 缺失
GET    /kb/export/markdown                ❌ 缺失
```

### `resources/js/api/ticket.js`

```javascript
GET    /tickets                           ✅ 已存在
GET    /tickets/{id}                      ✅ 已存在
GET    /tickets/stats                     ✅ 已存在
POST   /tickets/{id}/assign               ✅ 已存在
POST   /tickets/{id}/resolve              ✅ 已存在
POST   /tickets/{id}/close                ✅ 已存在
POST   /tickets/{id}/reopen               ✅ 已存在
GET    /tickets/my                        ✅ 已存在
POST   /tickets                           ✅ 已存在
POST   /tickets/{id}/reply                ✅ 已存在
POST   /tickets/{id}/satisfaction         ✅ 已存在
GET    /tickets/categories                ✅ 已存在
POST   /tickets/categories                ✅ 已存在
DELETE /tickets/categories/{id}           ✅ 已存在
POST   /tickets/check-sla                 ✅ 已存在
POST   /tickets/batch/close               ❌ 缺失
POST   /tickets/batch/assign              ❌ 缺失
POST   /tickets/batch/delete              ❌ 缺失
GET    /tickets/export/csv                ❌ 缺失
```

### `resources/js/api/apiKey.js`

```javascript
GET    /api-keys                          ✅ 已存在
GET    /api-keys/{id}                     ✅ 已存在
POST   /api-keys                          ✅ 已存在
PUT    /api-keys/{id}                     ✅ 已存在
DELETE /api-keys/{id}                     ✅ 已存在
POST   /api-keys/{id}/regenerate          ✅ 已存在
POST   /api-keys/{id}/toggle              ✅ 已存在
GET    /api-keys/{id}/audit-logs          ✅ 已存在
GET    /api-keys/{id}/usage-stats         ✅ 已存在
GET    /api-keys/stats/overview           ✅ 已存在
GET    /api-keys/audit-logs/all           ✅ 已存在
GET    /api-keys/config/tiers             ✅ 已存在
```

### `resources/js/api/license.js`

```javascript
GET    /licenses                          ✅ 已存在
GET    /licenses/{id}                     ✅ 已存在
POST   /licenses                          ✅ 已存在
PUT    /licenses/{id}                     ✅ 已存在
DELETE /licenses/{id}                     ✅ 已存在
POST   /licenses/{id}/restore (from trash)✅ 已存在
GET    /licenses/stats                    ✅ 已存在
POST   /licenses/{id}/revoke              ✅ 已存在
POST   /licenses/{id}/suspend             ✅ 已存在
POST   /licenses/{id}/freeze              ✅ 已存在
POST   /licenses/{id}/restore             ✅ 已存在
POST   /licenses/{id}/blacklist           ✅ 已存在
POST   /licenses/{id}/refund              ✅ 已存在
POST   /licenses/batch                    ✅ 已存在
POST   /licenses/batch/operation          ❌ 缺失
POST   /licenses/lookup                   ✅ 已存在
GET    /licenses/export                   ❌ 缺失
POST   /licenses/import                   ❌ 缺失
GET    /licenses/{id}/pool/status         ❌ 缺失
GET    /licenses/{id}/pool/assignments    ❌ 缺失
GET    /licenses/{id}/pool/queue          ❌ 缺失
POST   /licenses/{id}/pool/assign         ❌ 缺失
POST   /licenses/{id}/pool/release        ❌ 缺失
POST   /licenses/{id}/pool/heartbeat      ❌ 缺失
POST   /licenses/{id}/pool/cancel-queue   ❌ 缺失
PUT    /licenses/{id}/pool/config         ❌ 缺失
POST   /licenses/pool/batch-release-expired ❌ 缺失
```

### `resources/js/api/device.js`

```javascript
GET    /devices                           ✅ 已存在
GET    /devices/{id}                      ✅ 已存在
POST   /devices/{id}/deactivate           ✅ 已存在
GET    /devices/stats                     ✅ 已存在
POST   /devices/batch                     ✅ 已存在
GET    /devices/{id}/profile              ❌ 缺失
GET    /devices/profile-stats             ❌ 缺失
GET    /devices/{id}/timeline             ❌ 缺失
GET    /devices/{id}/lifecycle-events     ❌ 缺失
POST   /devices/{id}/adjust-trust         ❌ 缺失
POST   /devices/{id}/mark-suspicious      ❌ 缺失
POST   /devices/{id}/retire               ❌ 缺失
```

---

## 三、总结：缺失端点按模块分组

### 🔴 严重缺失（门户核心功能无法工作）

| 模块 | 缺失端点 |
|------|---------|
| **订单 (shop)** | `GET /orders`, `GET /orders/{id}`, `POST /orders/{id}/cancel`, `POST /orders/{id}/pay`, `GET /orders/{id}/payment-status`, 购物车所有端点, 商品所有端点, Wishlist, Reviews |
| **支付方式 (billing)** | `GET /billing/payment-methods`, `POST /billing/payment-methods`, `POST /billing/payment-methods/{id}/default`, `DELETE /billing/payment-methods/{id}`, `GET /billing/auto-renewal`, `POST /billing/auto-renewal`, `GET /billing/plans/*`, `POST /billing/plans`, `PUT /billing/plans/{id}`, `DELETE /billing/plans/{id}`, `GET /billing/coupons`, `POST /billing/coupons`, `PUT /billing/coupons/{id}`, `POST /billing/coupons/validate`, `GET /billing/coupons/stats`, `GET /billing/coupons/{id}/redemptions` |
| **转移 (transfer)** | `GET /portal/transfers`, `GET /portal/transfers/{id}`, `POST /portal/transfers/{id}/cancel`, `GET /portal/transfers/licenses`, `GET /transfers`, `GET /transfers/{id}`, `POST /transfers`, `POST /transfers/{id}/approve`, `POST /transfers/{id}/reject`, `POST /transfers/{id}/cancel`, `GET /transfers/stats` |
| **收益 (earnings)** | `GET /portal/earnings/dashboard`, `GET /portal/earnings/commissions`, `GET /portal/earnings/channels`, `POST /portal/earnings/channels/account`, `DELETE /portal/earnings/channels/account/{channel}`, `GET /portal/earnings/preferences`, `PUT /portal/earnings/preferences`, `GET /portal/earnings/tax-info`, `POST /portal/earnings/tax-info`, `GET /portal/earnings/settlement-calendar`, `GET /portal/earnings/commissions/export` |
| **提现 (withdrawal)** | `GET /withdrawals`, `GET /withdrawals/stats`, `GET /withdrawals/channels`, `POST /withdrawals`, `POST /withdrawals/{id}/cancel` |
| **促销 (promotions)** | `GET /portal/promotions/active`, `GET /portal/promotions/coupons`, `GET /promotions*`, `GET /contracts*`, `GET /coupons*` |
| **团队 (team)** | `GET /team`, `GET /team/members`, `POST /team/invite`, `POST /team/invitations/accept`, `POST /team/invitations/decline`, `GET /team/invitations/pending`, `POST /team/invitations/{id}/cancel`, `POST /team/invitations/{id}/resend`, `PUT /team/members/{id}/role`, `DELETE /team/members/{id}`, `POST /team/transfer-admin`, `POST /team/leave` |
| **数据导出 (dataExport)** | `GET /portal/data-exports/types`, `POST /portal/data-exports`, `GET /portal/data-exports`, `GET /portal/data-exports/{id}/download`, `DELETE /portal/data-exports/{id}` |
| **通知偏好 (notification)** | `GET /portal/notification-preferences`, `PUT /portal/notification-preferences`, `POST /portal/notification-preferences/initialize`, `PATCH /portal/notification-preferences/general` |
| **反馈 (feedback)** | `GET /portal/feedback`, `POST /portal/feedback`, `GET /portal/feedback/{id}`, `GET /feedback*` |
| **健康评分 (licenseHealth)** | `GET /portal/license-health/dashboard`, `GET /portal/license-health`, `GET /portal/license-health/{id}` |
| **GDPR** | `GET /gdpr/*`, `POST /gdpr/*`, `GET /gdpr/dpa/*`, `POST /gdpr/dpa/*` |
| **客户 API Keys** | `GET /customer-api-keys/*`, `POST /customer-api-keys/*` |
| **用量看板** | `GET /usage/api-calls`, `GET /usage/endpoint-stats`, `GET /usage/features` |

### ⚠️ 路径前缀不匹配

- `tenantTeam.js` 使用 `/api/team`（但 axios baseURL 已是 `/api`，实际会请求 `/api/api/team`）
- `deletion.js` 使用 `/api/account/deletion/*`（但 routes 中定义的是 `/account/deletion/*`）
- `account-deletion.js` 同样混合使用 `/api/account/deletion/*` 和 `/account/deletions/*`
- `dataAnonymization.js` 使用 `/api/data-anonymization/*`（可能导致 `/api/api/data-anonymization/*`）

### ✅ 已存在且正常工作的模块

- 认证 (`/login`, `/register`, `/logout`, `/user`, `/token/refresh`)
- License 管理 (`/licenses*`)
- 设备管理 (`/devices*`)
- 票据/工单 (`/tickets*`)
- 知识库 (`/kb/*`)
- 审计日志 (`/audit-logs*`)
- API Keys (`/api-keys*` — 管理端)
- 计费订阅和发票 (`/billing/subscriptions*`, `/billing/invoices*`)
