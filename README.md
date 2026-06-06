# 互物通 (88.huwutong.com) — License 管理系统

企业级软件许可证管理平台，支持 License 全生命周期管理、订阅计费、设备绑定、工单系统、AI 知识库（RAG）等功能。

## 技术栈

| 层级 | 技术 |
|------|------|
| **后端** | Laravel 11 + PHP 8.2+ |
| **前端** | Vue 3 (Composition API) + Element Plus + Pinia + Vite |
| **数据库** | MySQL 8.4 |
| **缓存/队列** | Redis |
| **测试** | PHPUnit 10 (后端), Vitest (前端), Playwright (E2E) |

## 快速开始（Docker Sail）

```bash
# 1. 克隆项目
git clone <repo-url> && cd 88.huwutong.com

# 2. 复制环境变量
cp .env.example .env

# 3. 启动 Docker 容器
./vendor/bin/sail up -d

# 4. 安装 PHP 依赖
./vendor/bin/sail composer install

# 5. 安装 Node 依赖 & 构建
./vendor/bin/sail npm install
./vendor/bin/sail npm run build

# 6. 生成密钥 & 运行迁移
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed

# 7. 访问 http://localhost
```

### 可用的 Docker 服务

| 服务 | 容器名 | 主机端口 |
|------|--------|----------|
| **应用** | laravel.test | 80 |
| **MySQL** | mysql | 3306 |
| **Redis** | redis | 6379 |
| **Mailpit** (SMTP + Web UI) | mailpit | 1025 / 8025 |

### Sail 常用命令

```bash
./vendor/bin/sail up -d            # 后台启动所有容器
./vendor/bin/sail stop             # 停止容器
./vendor/bin/sail down             # 停止并删除容器
./vendor/bin/sail artisan ...      # 运行 Artisan 命令
./vendor/bin/sail npm run dev      # 启动 Vite 开发服务器
./vendor/bin/sail composer ...     # 运行 Composer
./vendor/bin/sail test             # 运行 PHPUnit 测试
./vendor/bin/sail npm test         # 运行 Vitest 前端测试
```

## 本地开发（无 Docker）

### 环境要求

- PHP 8.2+
- Composer 2.x
- Node.js 20+
- MySQL 8.0+ / MariaDB
- Redis 7+

### 安装步骤

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
```

> **注意**：本地开发时，需要将 `.env` 中的 `DB_HOST` 改为 `127.0.0.1`，`REDIS_HOST` 改为 `127.0.0.1`，`MAIL_HOST` 改为 `127.0.0.1`。

## 运行测试

```bash
# 后端测试
composer test                 # 全部 PHPUnit 测试
composer test:feature         # 仅 Feature 测试

# 前端测试
npm test                      # Vitest 单元测试
npm run test:e2e              # Playwright E2E 测试

# 在 Docker 中运行
./vendor/bin/sail test
./vendor/bin/sail npm test
```

## 项目结构

```
app/
├── Http/Controllers/Api/     # API 控制器（43 个）
├── Models/                    # Eloquent 模型
├── Services/                  # 业务逻辑层
├── Policies/                  # 授权策略
└── Console/Kernel.php         # 定时任务
database/
├── factories/                 # 模型工厂
├── migrations/                # 数据库迁移
└── seeders/                   # 数据填充
resources/js/
├── views/                     # Vue 页面组件
├── api/                       # API 客户端模块
├── stores/                    # Pinia 状态管理
└── tests/                     # Vitest 前端测试
tests/
├── Feature/Api/               # API 集成测试
└── Unit/                      # 单元测试
```

## 许可证

本项目基于 MIT 许可证发布。
