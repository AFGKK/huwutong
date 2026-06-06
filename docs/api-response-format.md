# API 统一响应格式规范

## 1. 响应最外层结构

### 成功响应

```json
{
  "success": true,
  "message": "操作成功",
  "data": { ... }
}
```

### 失败响应

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "错误描述",
    "details": { ... }
  }
}
```

---

## 2. 标准响应

### 2.1 GET 单个资源 / POST / PUT

```json
{
  "success": true,
  "message": "查询成功",
  "data": {
    "id": 1,
    "name": "...",
    ...
  }
}
```

### 2.2 GET 列表（分页）

```json
{
  "success": true,
  "message": "查询成功",
  "data": [
    { "id": 1, "name": "..." },
    { "id": 2, "name": "..." }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 42,
    "last_page": 3,
    "from": 1,
    "to": 15
  }
}
```

### 2.3 POST 创建（201）

```json
{
  "success": true,
  "message": "创建成功",
  "data": { "id": 1, ... }
}
```

### 2.4 DELETE 删除（200）

```json
{
  "success": true,
  "message": "删除成功",
  "data": null
}
```

### 2.5 无内容（204）

状态码 `204`，无 body。

---

## 3. 统一错误码

| HTTP | code | message |
|------|------|---------|
| 422 | VALIDATION_ERROR | 参数验证失败 |
| 401 | UNAUTHORIZED | 未授权访问 |
| 401 | AUTH_FAILED | 邮箱/手机号或密码错误 |
| 401 | AUTH_TOKEN_EXPIRED | Token 已过期 |
| 403 | FORBIDDEN | 权限不足 |
| 404 | NOT_FOUND | 资源不存在 |
| 404 | LICENSE_NOT_FOUND | License Key 不存在 |
| 422 | LICENSE_EXPIRED | License 已过期 |
| 422 | LICENSE_NOT_ACTIVATABLE | License 当前状态不允许操作 |
| 422 | LICENSE_INVALID_KEY | License Key 格式无效 |
| 422 | DEVICE_LIMIT_EXCEEDED | 设备数量已达上限 |
| 422 | TRIAL_NOT_ALLOWED | 不允许创建试用 |
| 422 | CONVERSION_FAILED | 转正失败 |
| 429 | TOO_MANY_REQUESTS | 请求过于频繁 |
| 500 | INTERNAL_ERROR | 服务器内部错误 |

完整错误码定义: `app/Enums/ApiErrorCode.php`

### 错误详情

字段级验证错误:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "验证失败",
    "details": {
      "email": ["邮箱格式不正确"],
      "password": ["密码至少需要8个字符"]
    }
  }
}
```

业务错误详情:

```json
{
  "success": false,
  "error": {
    "code": "DEVICE_LIMIT_EXCEEDED",
    "message": "设备数量已达上限 (3)",
    "details": {
      "max_devices": 3,
      "current_count": 3
    }
  }
}
```

---

## 4. 客户端 SDK 激活/验证接口（特殊格式）

激活和验证接口为客户端 SDK 设计，`data` 内直接暴露 license 状态:

```json
{
  "success": true,
  "message": "激活成功",
  "data": {
    "valid": true,
    "license_key": "HWT-STD-A3F2C8D1-1A2B",
    "status": "active",
    "expires_at": "2027-06-04T12:00:00Z",
    "activation_id": 123,
    "device_id": 456,
    "is_existing_device": false
  }
}
```

---

## 5. 请求参数规范

### 分页参数

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `per_page` | int | 15 | 每页条数（最大 100） |
| `page` | int | 1 | 页码 |

### 筛选参数

```
GET /api/licenses?filter[status]=active&filter[type]=standard
GET /api/licenses?filter[status]=active,pending  (多值)
```

### 排序参数

| 参数 | 说明 |
|------|------|
| `sort=created_at` | 升序 |
| `sort=-created_at` | 降序（推荐默认） |
| `sort=-status,created_at` | 多字段排序 |

### 搜索参数

```
GET /api/licenses?search=张三&search_fields=name,email
```
