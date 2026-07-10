# 气隙部署 (Air-Gapped Deployment)

> M3-61: 面向军工/政府/银行内网的完全离线部署方案
> 零外网依赖运行 + Docker 镜像离线包 + License 文件 U 盘导入

## 适用场景

| 场景 | 说明 |
|:----|:------|
| 🏛️ **军工单位** | 物理隔离网络，严禁连接互联网 |
| 🏦 **银行内网** | 业务系统与互联网物理隔离 |
| 🏢 **政务内网** | 政务云/电子政务外网 |
| 🔒 **涉密环境** | 等保三级/三级以上要求 |

## 架构

```
┌────────── 开发环境（可联网）──────────┐
│                                        │
│  bash export-offline-package.sh        │
│                                        │
│  输出: hwt-license-offline-v1.0.0.zip  │
│    ├── docker-images/                  │
│    │   ├── hwt-api.tar                │
│    │   ├── hwt-admin.tar              │
│    │   ├── pgvector-pg16.tar           │
│    │   ├── mysql-8.0.tar (可选遗留)   │
│    │   ├── redis-7.tar                │
│    │   └── hwt-reverb.tar             │
│    ├── scripts/                        │
│    │   ├── deploy-offline.sh          │
│    │   ├── import-license.sh          │
│    │   └── check-integrity.sh         │
│    ├── config/                         │
│    │   ├── docker-compose.yml          │
│    │   └── .env.airgap                │
│    ├── updates/                        │
│    │   └── (离线更新包目录)            │
│    └── MANIFEST                        │
└────────── U 盘/光盘 ──────────────────┘
                    │
                    ▼
┌── 目标环境（完全离线）──┐
│                          │
│  1. mount USB / mount CD │
│  2. docker load < *.tar  │
│  3. docker compose up    │
│  4. USB 导入 License     │
│                          │
│  ✅ 系统正常运行          │
└──────────────────────────┘
```

## 数据库栈

| 栈 | Compose 文件 | 环境变量 | 说明 |
|:---|:-------------|:---------|:-----|
| **PostgreSQL**（**默认**） | `docker-compose.yml` 或 `docker-compose.pgsql.yml` | `.env.airgap` | PG 16 + pgvector，与生产一致 |
| MySQL（遗留） | `docker-compose.mysql.yml` | `.env.airgap.mysql` | 旧版离线环境兼容 |

切换栈：

```bash
bash scripts/use-stack.sh pgsql   # 默认推荐
bash scripts/use-stack.sh mysql   # 遗留环境
```

## 快速开始

### 步骤 1: 在联网环境打包

```bash
cd deploy/air-gapped

# 导出离线安装包（默认仅 PostgreSQL 镜像，体积更小）
bash export-offline-package.sh

# 含 PG + MySQL 双栈镜像（兼容遗留）
bash export-offline-package.sh "" both

# 仅 MySQL 遗留栈
bash export-offline-package.sh "" mysql
```

### 步骤 2: 传输到目标环境

通过 **U 盘** / **光盘** / **单向网闸** 传输到目标环境。

### 步骤 3: 在目标环境部署

```bash
# 解压离线包
unzip hwt-license-offline-v1.0.0.zip
cd hwt-license-offline-v1.0.0

# 部署（默认 PostgreSQL）
bash scripts/deploy-offline.sh

# 或显式指定栈
DEPLOY_STACK=pgsql bash scripts/deploy-offline.sh
DEPLOY_STACK=mysql bash scripts/deploy-offline.sh

# PG 迁移/验证（可选，deploy 会自动尝试）
bash scripts/bootstrap-pgsql.sh

# 验证完整性
bash scripts/check-integrity.sh

# 访问: http://localhost:8000
```

### 步骤 4: 导入 License

```bash
# 将 License 文件放到 U 盘
# 在目标环境执行:
bash scripts/import-license.sh /mnt/usb/license.lic
```

## 离线目录结构

```
deploy/air-gapped/
├── README.md                       # 本文档
├── STACK.default                   # 包默认栈标记 (pgsql)
├── export-offline-package.sh       # 导出离线包（默认 pgsql，可选 mysql/both）
├── docker-compose.yml              # 默认 PostgreSQL 栈
├── docker-compose.pgsql.yml        # 同 docker-compose.yml（显式 PG 命名）
├── docker-compose.mysql.yml        # MySQL 遗留栈
├── .env.airgap                     # PG 环境变量（默认）
├── .env.airgap.pgsql               # PG 环境变量副本
├── .env.airgap.mysql               # MySQL 环境变量
├── config/
│   ├── php.ini
│   ├── nginx.conf
│   ├── pgsql-init/01-extensions.sql
│   └── mysql-init/                 # MySQL 遗留
├── scripts/
│   ├── deploy-offline.sh
│   ├── use-stack.sh                # 切换 pgsql/mysql 栈
│   ├── bootstrap-pgsql.sh          # PG 迁移与验证
│   ├── import-license.sh
│   ├── check-integrity.sh
│   └── apply-update.sh
└── updates/
    └── README.md
```
