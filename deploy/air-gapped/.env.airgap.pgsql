# ===================================================
# .env.airgap.pgsql — PostgreSQL 离线环境变量（推荐）
# 复制为 .env 后使用 docker-compose.pgsql.yml 启动
# ===================================================
APP_NAME="HWT License (Air-Gapped PG)"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8000

# PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=huwutong
DB_USERNAME=postgres
DB_PASSWORD=HwtRoot2024!

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=HwtRedis2024!
REDIS_PORT=6379

# 队列 (离线环境使用 sync)
QUEUE_CONNECTION=sync

# 广播 (离线环境禁用)
BROADCAST_DRIVER=log

# 缓存/会话 (离线环境使用 file)
CACHE_STORE=file
SESSION_DRIVER=file

# License 配置
LICENSE_IMPORT_DIR=/app/licenses
LICENSE_AUTO_VERIFY=true

# 安全配置 (离线环境不强制 HTTPS)
SANCTUM_STATEFUL_DOMAINS=localhost:8000
SESSION_SECURE_COOKIE=false

# 离线模式
AIR_GAPPED_MODE=true
DISABLE_TELEMETRY=true
DISABLE_UPDATE_CHECK=true
OFFLINE_LICENSE_PATH=

# 备份（容器内 pg_dump）
PG_DUMP_PATH=pg_dump
PSQL_PATH=psql
