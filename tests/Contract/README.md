# Pact 契约测试框架（M1.4-60）

## 概述

基于 [Pact](https://docs.pact.io/) 规范的消费者驱动契约测试框架，确保 API 提供者（Laravel）与消费者（各语言 SDK）之间的接口兼容性。

## 目录结构

```
tests/Contract/
├── ContractTestCase.php      # 契约测试基类
├── PactContract.php          # Pact 契约核心（生成/加载/验证/匹配器）
├── Consumer/
│   └── PhpSdkContractTest.php    # PHP SDK 消费者契约测试
└── Provider/
    └── LicenseApiProviderContractTest.php  # API 提供者验证测试

pacts/                         # Pact 契约文件（JSON 格式）
└── PHP_SDK-HWT_License_API.json

.ci/contract-test.sh           # CI 集成脚本
```

## 使用方法

### 1️⃣ 生成消费者契约（SDK 维护者）

```bash
# 方式 A: 运行消费者测试（自动生成 pact 文件）
php artisan test --filter=PhpSdkContractTest

# 方式 B: 使用命令
php artisan contract:generate
```

生成的 Pact 文件保存在 `pacts/PHP_SDK-HWT_License_API.json`。

### 2️⃣ 验证提供者契约（API 维护者）

```bash
# 运行提供者验证测试
php artisan test --filter=LicenseApiProviderContractTest

# 或使用命令
php artisan contract:verify
```

### 3️⃣ 管理契约

```bash
php artisan contract:list       # 列出所有契约
php artisan contract:diff       # 比较契约变更（需 git）
```

### 4️⃣ CI 集成

```bash
bash .ci/contract-test.sh
```

可在 GitHub Actions / GitLab CI 中添加此步骤作为质量门禁。

## 契约规范

### Pact 兼容格式

生成的契约文件遵循 [Pact Specification v3](https://github.com/pact-foundation/pact-specification/tree/version-3)：

```json
{
  "consumer": { "name": "PHP SDK" },
  "provider": { "name": "HWT License API" },
  "interactions": [
    {
      "description": "成功激活 License",
      "providerState": "存在有效的 License 和未绑定设备",
      "request": { "method": "POST", "path": "/api/license/activate", ... },
      "response": { "status": 200, "body": { ... } }
    }
  ]
}
```

### 支持的类型匹配器

| 匹配器 | 说明 | 示例 |
|--------|------|------|
| `type` | 仅验证类型 | `{"pact:matcher:type": "type", "value": 1}` |
| `regex` | 正则匹配 | `{"pact:matcher:type": "regex", "regex": "^HWT-"}` |
| `timestamp` | ISO 8601 时间戳 | `{"pact:matcher:type": "timestamp"}` |
| `uuid` | UUID v4 格式 | `{"pact:matcher:type": "uuid"}` |
| `email` | 邮箱格式 | `{"pact:matcher:type": "email"}` |
| `ipAddress` | IP 地址 | `{"pact:matcher:type": "ipAddress"}` |

## 扩展指南

### 添加新的消费者契约

1. 在 `tests/Contract/Consumer/` 下新建测试类
2. 定义 `$interactions` 数组（请求 + 期望响应）
3. 调用 `PactContract::generate()` 和 `PactContract::saveToFile()`
4. 运行 `php artisan test --filter=YourNewTest`

### 添加新的提供者验证

1. 在 `tests/Contract/Provider/` 下新建测试类
2. 加载目标 Pact 文件
3. 创建测试数据并调用实际 API
4. 使用 `PactContract::verifyResponse()` 验证

## 变更通知

当契约发生变更时（`git diff pacts/` 有变化），CI 应自动通知所有 SDK 维护者：

- 新增端点 → 各 SDK 需添加对应方法
- 修改响应结构 → 各 SDK 需更新解析逻辑
- 废弃端点 → 各 SDK 需标记废弃并准备迁移
