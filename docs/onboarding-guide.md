# 新开发者 Onboarding 指南

> **文档版本**: 1.0 | **对应任务**: M0-10 | **最后更新**: 2026-06-15

---

## 目录

1. [本地环境搭建](#1-本地环境搭建)
2. [项目结构与代码规范](#2-项目结构与代码规范)
3. [开发工作流](#3-开发工作流)
4. [提交 PR 流程](#4-提交-pr-流程)
5. [常用命令速查](#5-常用命令速查)
6. [常见问题 FAQ](#6-常见问题-faq)

---

## 1. 本地环境搭建

### 1.1 环境要求

| 工具 | 版本要求 | 用途 |
|:----|:-------:|:----:|
| Git | ≥ 2.40 | 版本管理 |
| Docker Desktop | ≥ 24.0 | 容器化开发环境（推荐） |
| PHP | ≥ 8.2 | 后端运行（无 Docker 时） |
| Composer | ≥ 2.7 | PHP 依赖管理 |
| Node.js | ≥ 20 LTS | 前端构建 |
| MySQL | ≥ 8.0 | 数据库 |
| Redis | ≥ 7.0 | 缓存/队列 |

### 1.2 方式一：Docker Sail（推荐）

```bash
# 1. 克隆仓库
git clone <repo-url> huwutong
cd huwutong

# 2. 复制环境变量
cp .env.example .env

# 3. 安装 Composer 依赖
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php83-composer:latest \
    composer install --ignore-platform-reqs

# 4. 启动 Sail 容器
./vendor/bin/sail up -d

# 5. 生成应用密钥
./vendor/bin/sail artisan key:generate

# 6. 运行数据库迁移 + 种子数据
./vendor/bin/sail artisan migrate --seed

# 7. 安装前端依赖并构建
./vendor/bin/sail npm install
./vendor/bin/sail npm run build

# 8. 访问 http://localhost
```

> **首次启动耗时**：约 3-5 分钟（依网络而定）

### 1.3 方式二：Dev Containers（VS Code）

项目已配置 `.devcontainer/devcontainer.json`，自动包含 PHP 8.3 + Node 20 + 18 个扩展。

```bash
# 在 VS Code 中打开项目文件夹
# 按 F1 → "Reopen in Container"
# 容器启动后自动执行 post-create.sh 完成初始化
```

### 1.4 方式三：纯本地开发（无 Docker）

```bash
# 1. 确保本地已安装 PHP 8.2+ / Composer / Node 20+ / MySQL 8+ / Redis 7+
# 2. 安装依赖
composer install
npm install

# 3. 配置环境
cp .env.example .env
# 修改 .env：将 DB_HOST=127.0.0.1, REDIS_HOST=127.0.0.1, MAIL_HOST=127.0.0.1

# 4. 初始化
php artisan key:generate
php artisan migrate --seed

# 5. 启动开发服务
php artisan serve          # 后端 → http://localhost:8000
npm run dev                # 前端 → http://localhost:5173
```

### 1.5 可用的 Docker 服务

```yaml
# 见 compose.yaml
服务        端口                    用途
laravel.test  80                    Laravel 应用
mysql         3306                  数据库
redis         6379                  缓存/队列
mailpit       1025 (SMTP) / 8025   邮件捕获/管理面板
meilisearch   7700                  搜索引擎（M2-156）
```

---

## 2. 项目结构与代码规范

### 2.1 目录结构总览

```
huwutong/
├── app/                          # Laravel 后端
│   ├── Console/                  # 自定义 Artisan 命令
│   │   └── Commands/
│   ├── Contracts/                # 接口契约
│   ├── Enums/                    # PHP Enums
│   ├── Events/                   # 事件类
│   ├── Exceptions/               # 自定义异常
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/              # API 控制器（~80+ 个）
│   │   │   └── Portal/           # 客户门户控制器
│   │   ├── Middleware/            # 中间件（限流/鉴权/租户等）
│   │   └── Requests/             # 表单请求验证
│   ├── Jobs/                     # 队列 Job
│   ├── Listeners/                # 事件监听器
│   ├── Mail/                     # 邮件类
│   ├── Models/                   # Eloquent 模型（~60+ 个）
│   ├── Notifications/            # 通知类
│   ├── Observers/                # 模型观察者
│   ├── Policies/                 # 授权策略
│   ├── Providers/                # 服务提供者
│   ├── Services/                 # 业务逻辑层（~80+ 个）
│   └── Workflows/                # Temporal 工作流
├── bootstrap/                    # 框架引导
├── config/                       # 配置文件（~100+ 个）
├── database/
│   ├── factories/                # 模型工厂
│   ├── migrations/               # 数据库迁移（~120+ 个）
│   └── seeders/                  # 数据填充
├── docs/                         # 项目文档
│   ├── adr/                      # 架构决策记录
│   └── ...                       # 更多文档
├── lang/                         # 多语言文件
├── resources/
│   └── js/                       # Vue 3 前端
│       ├── api/                  # API 客户端模块（~80+ 个文件）
│       ├── components/           # 公共组件
│       ├── composables/          # 组合式 API
│       ├── config/               # 前端配置
│       ├── layouts/              # 布局组件
│       ├── router/               # Vue Router 路由
│       ├── stores/               # Pinia 状态管理
│       ├── utils/                # 工具函数
│       ├── views/                # 页面组件（~200+ 个目录）
│       └── tests/                # Vitest 前端测试
├── routes/
│   ├── api.php                   # API 路由（~200+ 条）
│   ├── web.php                   # Web 路由
│   └── channels.php              # WebSocket 频道
├── tests/                        # PHPUnit 测试
│   ├── Feature/                  # 集成测试
│   ├── Unit/                     # 单元测试
│   └── Contract/                 # 契约测试
├── mobile/                       # Flutter 移动端（M3-67）
├── .devcontainer/                # Dev Containers 配置
├── deploy/                       # Docker 部署
├── .github/workflows/            # CI/CD（4 个 workflow）
└── e2e/                          # Playwright E2E 测试
```

### 2.2 后端开发规范

#### 命名约定

```
控制器      → LicenseController.php        (单数, PascalCase)
模型        → License.php                  (单数, PascalCase)
服务        → LicenseService.php           (单数, PascalCase)
迁移        → 2026_01_01_000001_create_licenses_table.php
路由        → licenses.index / licenses.store  (复数, kebab-case)
API URL     → /api/admin/licenses          (复数, kebab-case)
中间件      → kebab-case: tenant.context    (kebab-case)
```

#### API 响应格式

所有 API 必须使用 `api_response()` 辅助函数，统一格式：

```json
{
    "success": true,
    "message": "操作成功",
    "data": { ... },
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "last_page": 10,
        "total": 150
    }
}
```

错误响应：

```json
{
    "success": false,
    "message": "License 已过期",
    "error": {
        "code": "LICENSE_EXPIRED",
        "details": { ... }
    }
}
```

#### 路由模式

```php
// 管理后台 API（需 auth:sanctum + 管理员权限）
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])
    ->prefix('admin/licenses')
    ->group(function () {
        Route::get('/', [LicenseController::class, 'index'])->name('licenses.index');
        Route::post('/', [LicenseController::class, 'store'])->name('licenses.store');
        Route::get('/{license}', [LicenseController::class, 'show'])->name('licenses.show');
        Route::put('/{license}', [LicenseController::class, 'update'])->name('licenses.update');
        Route::delete('/{license}', [LicenseController::class, 'destroy'])->name('licenses.destroy');
    });

// 公开 API（无需鉴权）
Route::post('/license/activate', [ActivateController::class, 'activate']);
Route::post('/license/validate', [ActivateController::class, 'validate']);
```

#### 关键约束

- **所有模型**涉及多租户的必须包含 `tenant_id` 字段并自动注入
- **控制器**保持精简（< 20 行），业务逻辑放入 Service 层
- **枚举类**使用 PHP 8.1+ `enum`，如 `LicenseStatus::Active`
- **配置**统一在 `config/` 目录管理，使用 `config()` 辅助函数读取
- **异常**统一通过 `App\Exceptions\Handler` 处理

### 2.3 前端开发规范

#### 目录结构

```
resources/js/
├── api/license.js              # API 模块（一个文件对应一个资源）
├── views/licenses/Index.vue    # 页面组件
├── components/                  # 公共组件
├── stores/license.js           # Pinia Store
└── router/index.js             # 路由定义
```

#### API 模块模式

```javascript
// resources/js/api/license.js
import client from '@/api/client';

export const licenseApi = {
    list(params)     { return client.get('/admin/licenses', { params }); },
    detail(id)      { return client.get(`/admin/licenses/${id}`); },
    create(data)    { return client.post('/admin/licenses', data); },
    update(id, data){ return client.put(`/admin/licenses/${id}`, data); },
    delete(id)      { return client.delete(`/admin/licenses/${id}`); },
};
```

#### 路由注册（懒加载）

```javascript
// resources/js/router/index.js
{
    path: 'licenses',
    name: 'Licenses',
    component: () => import('@/views/licenses/Index.vue'),
    meta: { title: 'License 管理', icon: 'Key' },
}
```

#### Vue 组件规范

- 使用 **Composition API + `<script setup>`**
- 组件名 **PascalCase**，如 `LicenseTable.vue`
- 引入 Element Plus 图标需同时注册到 `AdminLayout.vue`
- 新增页面需同时注册路由、侧边栏菜单条目

#### 侧边栏菜单注册

在 `AdminLayout.vue` 的菜单数组或 `el-menu-item` 块中添加：

```vue
<el-menu-item index="/licenses">
    <el-icon><Key /></el-icon>
    <template #title>License 管理</template>
</el-menu-item>
```

### 2.4 数据库规范

- **迁移文件**：时间戳前缀 + 描述性名称
- **索引**：所有外键加索引，查询频繁字段加复合索引
- **迁移策略**：零停机迁移（大表使用 `pt-online-schema-change`）
- **软删除**：使用 `SoftDeletes` trait

### 2.5 代码质量门禁

| 工具 | 命令 | 用途 |
|:----|:----|:----|
| PHPStan (Level 6) | `./vendor/bin/phpstan analyse` | 静态分析 |
| Laravel Pint | `./vendor/bin/pint` | 代码格式化 |
| Rector | `./vendor/bin/rector process` | 自动升级 |
| PHPUnit | `composer test` | 单元/集成测试 |
| Vitest | `npm test` | 前端测试 |
| Playwright | `npm run test:e2e` | E2E 测试 |

> 代码质量门禁在 CI 中自动执行，不合格代码无法合入主分支。

---

## 3. 开发工作流

### 3.1 分支策略

```
main          → 生产就绪代码（受保护，需 PR 合入）
develop       → 开发主线（受保护，需 PR 合入）
feature/*     → 新功能分支（从 develop 拉出）
fix/*         → 修复分支
release/*     → 发布分支
```

### 3.2 日常开发流程

```bash
# 1. 确保在 develop 分支并同步最新代码
git checkout develop
git pull origin develop

# 2. 创建功能分支
git checkout -b feature/M1.2-05-key-generator

# 3. 编写代码 + 测试
# ... coding ...

# 4. 运行代码质量和测试
./vendor/bin/pint              # 格式化
./vendor/bin/phpstan analyse   # 静态分析
composer test                  # 运行测试

# 5. 提交
git add .
git commit -m "feat: 实现 License Key Ed25519 签名生成算法 (#M1.2-05)"

# 6. 推送并创建 PR
git push origin feature/M1.2-05-key-generator
# 在 GitHub 创建 Pull Request → develop
```

### 3.3 Commit Message 规范

```
格式: <type>: <简短描述> (#<任务编号>)

类型: feat    → 新功能
      fix     → 修复
      docs    → 文档
      refactor→ 重构
      test    → 测试
      chore   → 杂项
      style   → 格式

示例:
  feat: 实现 Ed25519 License Key 签名算法 (#M1.2-05)
  fix: 修复设备激活并发超卖问题 (#M1.2-10)
  docs: 更新 API 限流配置文档 (#M1.3-08)
  test: 添加 License 状态机单元测试 (#M1.2-14)
```

### 3.4 开发环境端口

```
:80      Laravel 应用（Docker Sail）
:5173    Vite 前端开发服务器（HMR 热更新）
:8000    php artisan serve（直连模式）
:3306    MySQL
:6379    Redis
:8080    Laravel Reverb WebSocket
:1025    Mailpit SMTP（开发邮件捕获）
:8025    Mailpit 管理面板（查看邮件）
:7700    Meilisearch 搜索引擎
```

---

## 4. 提交 PR 流程

### 4.1 PR Checklist

创建 Pull Request 前，请逐项确认：

- [ ] 代码已通过 `./vendor/bin/pint` 格式化
- [ ] PHPStan Level 6 无报错
- [ ] 新增/修改的 API 有对应的 Feature Test
- [ ] Vue 组件已注册路由和菜单
- [ ] 数据库迁移可回滚（有 `down()` 方法）
- [ ] 未引入新的安全漏洞（敏感信息无硬编码）
- [ ] 多语言翻译已更新（`lang/` 目录）
- [ ] 任务编号标注在 commit 消息中

### 4.2 PR 模板

```markdown
## 关联任务
M1.2-05: License Key 生成算法

## 变更内容
- 实现 Ed25519 签名算法（使用 sodium_crypto_sign_*）
- 添加 RSA-2048 兼容模式
- 添加可读前缀格式：HWT-ENT-xxx

## 测试覆盖
- [x] 单元测试：Ed25519 签名/验签
- [x] 单元测试：RSA 兼容模式
- [x] Feature Test：License 生成 API
- [x] E2E：激活→验证流程

## 部署注意
- 需要执行 `php artisan migrate`
- 需要更新 .env 新增 `LICENSE_KEY_ALGORITHM=ed25519`

## 截图
<!-- 如果有 UI 变更，附上截图 -->
```

### 4.3 CI 自动检查

PR 提交后，CI 自动执行以下检查（~5-8 分钟）：

```
1. ✅ PHPStan 静态分析
2. ✅ Laravel Pint 代码格式
3. ✅ PHPUnit 测试套件
4. ✅ Vitest 前端测试
5. ✅ Playwright E2E 测试
6. ✅ Trivy 安全扫描
7. ✅ Dependabot 依赖检查
```

> ❌ 任一检查失败 → PR 自动标记为 ❌，需要修复后重新触发。

### 4.4 Code Review 指引

- **至少 1 人 Review** 通过后方可合并
- **P0 模块**（License 核心/支付/安全）至少 2 人 Review
- Review 关注点：安全性、租户隔离、性能、测试覆盖、错误处理

---

## 5. 常用命令速查

### 后端

```bash
# Laravel
sail artisan make:model License -mfsc    # 创建模型+迁移+工厂+控制器
sail artisan make:controller Api/LicenseController
sail artisan make:migration create_xxx_table
sail artisan make:service LicenseService
sail artisan route:list                   # 查看路由
sail artisan queue:work                   # 处理队列

# 数据库
sail artisan migrate                      # 运行迁移
sail artisan migrate:fresh --seed         # 重置数据库+种子
sail artisan db:seed --class=LicenseSeeder

# 测试
composer test                             # 全部测试
composer test:feature                     # 仅 Feature 测试
composer test:unit                        # 仅单元测试
./vendor/bin/phpunit tests/Feature/Api/LicenseTest.php

# 代码质量
./vendor/bin/pint                         # 格式化代码
./vendor/bin/pint --test                  # 检查格式（不修改）
./vendor/bin/phpstan analyse              # 静态分析
./vendor/bin/phpstan analyse app/Services/LicenseService.php

# 缓存
sail artisan optimize                     # 优化 Laravel
sail artisan config:cache                 # 配置缓存
sail artisan route:cache                  # 路由缓存
```

### 前端

```bash
npm run dev                               # 启动 Vite HMR 开发服务器
npm run build                             # 生产构建
npm test                                  # Vitest 测试
npm run test:watch                        # 测试监听模式
npm run test:e2e                          # Playwright E2E
npm run test:e2e:ui                       # Playwright UI 模式
```

### Docker

```bash
./vendor/bin/sail up -d                   # 后台启动
./vendor/bin/sail stop                    # 停止
./vendor/bin/sail down                    # 停止并删除容器
./vendor/bin/sail restart                 # 重启
./vendor/bin/sail logs -f                 # 查看日志
./vendor/bin/sail ps                      # 查看容器状态
```

---

## 6. 常见问题 FAQ

### Q1: `sail` 命令找不到？

```bash
# 确保已安装 Composer 依赖
docker run --rm -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php83-composer:latest composer install

# 或使用完整路径
./vendor/bin/sail up -d
```

### Q2: 数据库连接失败？

```
检查 .env 配置:
- DB_HOST=mysql（Docker Sail 内使用服务名）
- DB_HOST=127.0.0.1（本地开发无 Docker）
- 确保 MySQL 容器已启动：sail ps
```

### Q3: Vite HMR 不生效？

```bash
# 确保 Vite 端口不冲突
# 检查 .env 的 VITE_PORT=5173
# 检查 SANCTUM_STATEFUL_DOMAINS 是否包含 localhost:5173

sail npm run dev
# 访问 http://localhost:5173
```

### Q4: 邮件发送失败？

```bash
# 开发环境默认使用 Mailpit
# 查看邮件：http://localhost:8025
# 如使用 log 驱动，邮件写入 storage/logs/laravel.log

# 配置 .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

### Q5: 如何新增一个完整的模块？

```bash
# 后端
sail artisan make:model ProductSku -mfsc
# 编辑迁移 → 运行 sail artisan migrate
# 创建 Service → app/Services/ProductSkuService.php
# 创建 Controller → app/Http/Controllers/Api/ProductSkuController.php
# 添加路由 → routes/api.php

# 前端
# 创建 API 模块 → resources/js/api/productSku.js
# 创建页面 → resources/js/views/product-sku/Index.vue
# 注册路由 → resources/js/router/index.js
# 添加菜单 → resources/js/layouts/AdminLayout.vue
```

### Q6: 前端构建报错？

```bash
# 清缓存重试
rm -rf node_modules
npm cache clean --force
npm install
npm run build

# 常见原因：Element Plus 图标未注册、Vite 插件版本不兼容
```

### Q7: Redis 连接失败？

```bash
# Docker 环境
REDIS_HOST=redis

# 本地环境
REDIS_HOST=127.0.0.1

# 检查 Redis 运行状态
sail exec redis redis-cli ping
# 应返回 PONG
```

### Q8: PHPStan 报大量错误？

```bash
# 首次运行可能会报 Eloquent 动态方法错误，已配置忽略规则
# 见 phpstan.neon 中的 ignoreErrors

# 增量修复建议：
# 1. 先关注 app/Services/ 目录下的业务逻辑
# 2. 类型声明：为所有方法参数和返回值添加类型
# 3. 避免使用 mixed
```

### Q9: MFA 或 OAuth 配置后无法登录？

```bash
# 紧急后门：修改 .env 临时关闭 MFA
# MFA_REQUIRED=false

# 检查 OAuth 回调 URL 配置
# 确保 redirect_uri 与 OAuth Provider 配置一致
```

### Q10: 如何重置开发数据库？

```bash
sail artisan migrate:fresh --seed
# 此操作会删除所有数据并重新填充种子数据
```

### Q11: 提交 PR 后 CI 失败？

```bash
# 常见原因及处理：
# 1. PHPStan 报错 → ./vendor/bin/phpstan analyse 本地修复
# 2. 测试失败 → composer test 本地复现
# 3. Pint 格式问题 → ./vendor/bin/pint 自动修复后重新提交
# 4. E2E 测试失败 → npm run test:e2e 本地检查

# 强制清空 CI 缓存：在 PR 评论中 @dependabot rebase
```

### Q12: 项目中有多少模块？

项目共 **409 项任务**，覆盖 M0~M3 四个里程碑：

| 里程碑 | 范围 | 任务数 |
|:-----|:----|:-----:|
| M0 | 项目准备期 | 12 项 |
| M1 | P0 核心功能 | 147 项 |
| M2 | P1 运营必备 | 160 项 |
| M3 | P2 商业进阶 | 90 项 |

---

> **维护者**: 架构组 | **最后更新**: 2026-06-15 | **相关文档**: `docs/adr/`, `docs/deployment-architecture.md`, `docs/ci-cd-pipeline.md`, `互物通_开发任务拆解表.md`
