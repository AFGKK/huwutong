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
│    │   ├── mysql-8.0.tar              │
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

## 快速开始

### 步骤 1: 在联网环境打包

```bash
cd deploy/air-gapped

# 导出离线安装包
bash export-offline-package.sh

# 输出: hwt-license-offline-v1.0.0.zip
```

### 步骤 2: 传输到目标环境

通过 **U 盘** / **光盘** / **单向网闸** 传输到目标环境。

### 步骤 3: 在目标环境部署

```bash
# 解压离线包
unzip hwt-license-offline-v1.0.0.zip
cd hwt-license-offline-v1.0.0

# 部署所有 Docker 镜像
bash scripts/deploy-offline.sh

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
├── export-offline-package.sh       # 导出离线包脚本
├── docker-compose.yml              # Docker Compose 配置
├── .env.airgap                     # 离线环境变量
├── config/
│   ├── php.ini                     # PHP 配置
│   └── nginx.conf                  # Nginx 配置
├── scripts/
│   ├── deploy-offline.sh           # 部署脚本
│   ├── import-license.sh           # License 导入
│   ├── check-integrity.sh          # 完整性校验
│   └── apply-update.sh             # 离线更新
└── updates/                        # 离线更新包目录
    └── README.md
```
