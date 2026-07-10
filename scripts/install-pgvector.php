<?php
/**
 * 安装 PostgreSQL pgvector 扩展
 *
 * 用法: php scripts/install-pgvector.php
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (DB::connection()->getDriverName() !== 'pgsql') {
    fwrite(STDERR, "当前驱动非 pgsql，跳过。\n");
    exit(0);
}

echo "=== 安装 pgvector 扩展 ===\n";

try {
    $available = DB::selectOne(
        "SELECT COUNT(*)::int AS cnt FROM pg_available_extensions WHERE name = 'vector'"
    );
    if (($available->cnt ?? 0) === 0) {
        echo "❌ 当前 PostgreSQL 未安装 pgvector 二进制扩展\n";
        echo "\nWindows (PostgreSQL 16) 安装步骤:\n";
        echo "  1. 下载 pgvector 发布包: https://github.com/pgvector/pgvector/releases\n";
        echo "  2. 将 vector.dll 复制到 C:\\Program Files\\PostgreSQL\\16\\lib\\\n";
        echo "  3. 将 vector.control / vector--*.sql 复制到 ...\\share\\extension\\\n";
        echo "  4. 重启 PostgreSQL 服务后重新运行本脚本\n";
        exit(1);
    }

    DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    $ext = DB::selectOne("SELECT extname, extversion FROM pg_extension WHERE extname = 'vector'");
    if ($ext) {
        echo "✅ pgvector {$ext->extversion} 已安装\n";
        exit(0);
    }
    echo "⚠️  CREATE EXTENSION 执行成功但未检测到 vector\n";
    exit(1);
} catch (Throwable $e) {
    echo "❌ 安装失败: {$e->getMessage()}\n";
    echo "\n常见原因:\n";
    echo "  1. PostgreSQL 未安装 pgvector 扩展文件\n";
    echo "  2. Windows: 需将 vector.dll 放入 PostgreSQL lib 目录\n";
    echo "  3. Linux: apt install postgresql-XX-pgvector 或源码编译\n";
    echo "  4. 以超级用户执行: psql -U postgres -d huwutong -c \"CREATE EXTENSION vector;\"\n";
    exit(1);
}
