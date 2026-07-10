# 离线环境部署与升级 SOP

> 适用对象：气隙（无互联网）环境下的 HWT License / 互物通部署与版本升级（默认 PostgreSQL，兼容 MySQL 遗留栈）。

## 1. 角色与前置条件

| 环境 | 职责 |
|------|------|
| **联网构建机** | 导出离线安装包、可选 PG/MySQL 数据、制作增量更新包 |
| **离线目标机** | 校验完整性、部署、导入数据、应用更新、验证 |

**目标机最低要求**：Docker 20+、Docker Compose v2、bash、curl、zip/unzip、sha256sum。

---

## 2. 首次部署

### 2.1 联网侧：导出安装包

```bash
cd deploy/air-gapped

# 仅镜像 + 脚本（空库，首次 migrate）
bash export-offline-package.sh 20260709 pgsql

# 含生产数据（推荐用于迁移/克隆）
bash export-offline-package.sh 20260709 pgsql with-data

# MySQL 遗留栈（含数据）
bash export-offline-package.sh 20260709 mysql with-data
```

**Windows / phpEnv 单独导出数据**（若 bash 无客户端工具）：

```powershell
cd d:\phpEnv\www\88.huwutong.com
Remove-Item Env:DB_PASSWORD -ErrorAction SilentlyContinue

# PostgreSQL
php scripts/export-pgsql-data.php deploy/air-gapped/output/hwt-license-offline-XXX/data/pgsql

# MySQL 遗留（需 .env 中 DB_CONNECTION=mysql 或指向 MySQL 实例）
php scripts/export-mysql-data.php deploy/air-gapped/output/hwt-license-offline-XXX/data/mysql
```

将 `output/hwt-license-offline-*.zip` 拷贝至 U 盘。

### 2.2 离线侧：部署

```bash
unzip hwt-license-offline-20260709.zip
cd hwt-license-offline-20260709

bash scripts/check-integrity.sh
bash scripts/deploy-offline.sh                    # 默认 PostgreSQL
DEPLOY_STACK=mysql bash scripts/deploy-offline.sh # MySQL 遗留栈
```

**含 `data/pgsql/huwutong.sql.gz` 时**（PostgreSQL）：`deploy-offline.sh` 会自动：

1. 先启动 `postgres` + `redis`
2. 执行 `import-pgsql-data.sh` 恢复数据
3. 启动全栈并运行 `bootstrap-pgsql.sh`（`migrate --force` + 健康检查）

**含 `data/mysql/huwutong.sql.gz` 时**（MySQL 遗留）：`deploy-offline.sh` 会自动：

1. 先启动 `mysql` + `redis`
2. 执行 `import-mysql-data.sh` 恢复数据
3. 启动全栈并运行 `bootstrap-mysql.sh`（`migrate --force` + 健康检查）

跳过数据导入（仅空库）：

```bash
IMPORT_DB_DATA=skip bash scripts/deploy-offline.sh
```

### 2.3 部署后验证

```bash
curl -s http://localhost:8000/api/health/live
curl -s http://localhost:8000/api/health/ready
docker compose exec -T api php artisan tinker --execute="echo DB::connection()->getDriverName();"
```

期望：`live`/`ready` 返回 200，驱动为 `pgsql` 或 `mysql`（取决于栈）。

---

## 2A. MySQL 遗留栈补充说明

> 新部署请优先使用 PostgreSQL。MySQL 栈仅用于尚未迁移的遗留环境。

| 项目 | 路径/命令 |
|------|-----------|
| Compose | `docker-compose.mysql.yml` |
| 环境模板 | `.env.airgap.mysql` |
| 数据目录 | `data/mysql/huwutong.sql.gz` |
| 导出 | `bash scripts/export-mysql-data.sh` |
| 导入 | `bash scripts/import-mysql-data.sh` |
| 初始化 | `bash scripts/bootstrap-mysql.sh` |

导出前请确认 `.env` 指向 MySQL 源库（`DB_CONNECTION=mysql`，端口 3306）。

## 3. 版本升级（增量更新包）

### 3.1 联网侧：制作更新包

```bash
cd deploy/air-gapped
bash scripts/create-update-package.sh 1.2.0
# 输出: updates/hwt-update-v1.2.0.update.tar.gz
```

更新包内容：

- 新版本 `hwt-api` Docker 镜像
- `database/migrations/` 全量迁移文件
- `pre-update.sh`：升级前自动 `pg_dump` / `mysqldump` 备份
- `post-update.sh`：`migrate --force` + 健康检查

### 3.2 离线侧：应用更新

```bash
# 将更新包放入 updates/ 目录
bash scripts/apply-update.sh updates/hwt-update-v1.2.0.update.tar.gz
```

流程：解压 → SHA256 校验 → `docker load` → `pre-update` 备份 → 重启服务 → `post-update` 迁移 → 健康检查。

### 3.3 升级后验证清单

- [ ] `docker compose ps` 所有服务 `running`
- [ ] `/api/health/ready` 返回 200
- [ ] `php artisan migrate:status` 无 pending
- [ ] 关键业务页面/API 抽测通过
- [ ] `backups/pre-update-*.sql.gz` 已生成

---

## 4. 数据备份与恢复

### 4.1 日常备份（离线环境）

**PostgreSQL：**

```bash
mkdir -p backups
docker compose exec -T postgres \
  pg_dump -U postgres huwutong | gzip > backups/huwutong-$(date +%Y%m%d).sql.gz
```

**MySQL 遗留：**

```bash
mkdir -p backups
docker compose -f docker-compose.mysql.yml exec -T mysql \
  mysqldump -u root -p"${DB_PASSWORD}" --single-transaction huwutong \
  | gzip > backups/huwutong-$(date +%Y%m%d).sql.gz
```

或使用应用内 `BackupService`（已支持 `pg_dump` / `mysqldump`）。

### 4.2 从备份恢复

**PostgreSQL：**

```bash
docker compose stop api queue scheduler
gunzip -c backups/huwutong-20260709.sql.gz | \
  docker compose exec -T postgres psql -U postgres -d huwutong
docker compose up -d
```

**MySQL 遗留：**

```bash
docker compose -f docker-compose.mysql.yml stop api queue
gunzip -c backups/huwutong-20260709.sql.gz | \
  docker compose -f docker-compose.mysql.yml exec -T mysql \
  mysql -u root -p"${DB_PASSWORD}" huwutong
docker compose -f docker-compose.mysql.yml up -d
```

或使用专用脚本：

```bash
bash scripts/import-pgsql-data.sh backups/huwutong-20260709.sql.gz
bash scripts/import-mysql-data.sh backups/huwutong-20260709.sql.gz
```

---

## 5. 回滚流程

### 5.1 应用版本回滚

```bash
# 1. 加载旧版镜像（需提前保留旧 tar）
docker load -i docker-images/hwt-api-old.tar

# 2. 修改 compose 或 tag 指向旧版本
docker tag hwt-license-api:1.1.0 hwt-license-api:latest

# 3. 重启
docker compose up -d --force-recreate api
```

### 5.2 数据库回滚

```bash
docker compose stop api queue scheduler
bash scripts/import-pgsql-data.sh backups/pre-update-YYYYMMDD_HHMMSS.sql.gz
docker compose up -d
```

> **注意**：仅回滚数据库无法撤销已执行的 `migrate` 结构变更；重大升级前务必保留完整 `pg_dump`。

---

## 6. 常见问题

| 现象 | 处理 |
|------|------|
| `pg_dump` / `mysqldump` 找不到 | 安装对应客户端或将 `PG_DUMP_PATH` / `MYSQLDUMP_PATH` 加入 PATH |
| 导入报 `relation already exists` | PG dump 含 `--clean`；MySQL dump 含 `--add-drop-table` |
| migrate 与 dump 冲突 | 先导入数据，再 `migrate --force` 补齐新迁移 |
| 端口占用 | 修改 `.env` 中 `APP_PORT` / `DB_PORT` 或释放端口 |
| Meilisearch 未运行 | 离线可选；搜索降级为 DB 全文检索 |

---

## 7. 文件索引

| 脚本 | 用途 |
|------|------|
| `export-offline-package.sh` | 完整离线安装包 |
| `scripts/export-pgsql-data.sh` | Linux PG 数据导出 |
| `scripts/export-pgsql-data.php` | Windows PG 数据导出 |
| `scripts/export-mysql-data.sh` | Linux MySQL 数据导出 |
| `scripts/export-mysql-data.php` | Windows MySQL 数据导出 |
| `scripts/import-pgsql-data.sh` | 离线 PG 数据恢复 |
| `scripts/import-mysql-data.sh` | 离线 MySQL 数据恢复 |
| `scripts/deploy-offline.sh` | 离线首次部署 |
| `scripts/create-update-package.sh` | 增量更新包 |
| `scripts/apply-update.sh` | 应用增量更新 |
| `scripts/bootstrap-pgsql.sh` | PG 迁移 + 健康检查 |
| `scripts/bootstrap-mysql.sh` | MySQL 迁移 + 健康检查 |
| `scripts/check-integrity.sh` | 包完整性校验 |

---

## 8. 变更记录

| 日期 | 说明 |
|------|------|
| 2026-07-09 | 默认 PostgreSQL；新增 PG/MySQL 数据打包/恢复与离线升级 SOP |
